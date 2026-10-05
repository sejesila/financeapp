<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('contact')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'normalized_name']);
        });

        Schema::table('loans_given', function (Blueprint $table) {
            $table->foreignId('borrower_id')->nullable()->after('user_id')
                ->constrained('borrowers')->nullOnDelete();
        });

        // Backfill: group existing loans by user + normalized name.
        // Exact-name matching only; review /borrowers afterwards and merge
        // any people recorded under variant spellings.
        $groups = DB::table('loans_given')->get()
            ->groupBy(fn ($l) => $l->user_id . '|' . mb_strtolower(trim($l->borrower_name)));

        foreach ($groups as $loans) {
            $first = $loans->sortBy('disbursed_date')->first();
            $norm  = mb_strtolower(trim($first->borrower_name));

            $id = DB::table('borrowers')->insertGetId([
                'user_id'         => $first->user_id,
                'name'            => trim($first->borrower_name),
                'normalized_name' => $norm,
                'contact'         => $loans->pluck('borrower_contact')->filter()->last(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            DB::table('loans_given')->whereIn('id', $loans->pluck('id'))->update(['borrower_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('loans_given', fn (Blueprint $t) => $t->dropConstrainedForeignId('borrower_id'));
        Schema::dropIfExists('borrowers');
    }
};
