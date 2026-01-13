<?php
// database/migrations/xxxx_add_idempotency_key_to_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('id');
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};

