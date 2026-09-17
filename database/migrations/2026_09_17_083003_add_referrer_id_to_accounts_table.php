<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Only meaningful for type = 'referrer_float', but left generally
            // nullable rather than constrained to that type at the DB level —
            // simpler, and the app layer already knows which accounts are
            // float accounts via `type`.
            $table->foreignId('referrer_id')->nullable()->after('type')
                ->constrained('referrers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referrer_id');
        });
    }
};
