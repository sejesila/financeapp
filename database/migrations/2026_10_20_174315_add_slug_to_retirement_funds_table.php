<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retirement_funds', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Give existing funds a slug, unique per user.
        $used = [];
        DB::table('retirement_funds')->orderBy('id')->get()->each(function ($fund) use (&$used) {
            $base = Str::slug($fund->name) ?: 'fund';
            $slug = $base;
            $i = 2;
            while (isset($used[$fund->user_id][$slug])) {
                $slug = $base . '-' . $i++;
            }
            $used[$fund->user_id][$slug] = true;

            DB::table('retirement_funds')->where('id', $fund->id)->update(['slug' => $slug]);
        });

        Schema::table('retirement_funds', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('retirement_funds', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
