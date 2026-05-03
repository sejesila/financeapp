<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('cash', 'mpesa', 'airtel_money', 'bank', 'savings', 'wallet') NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        // Revert any wallet accounts before removing the enum value
        DB::statement("UPDATE accounts SET type = 'bank' WHERE type = 'wallet'");
        DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('cash', 'mpesa', 'airtel_money', 'bank', 'savings') NOT NULL DEFAULT 'cash'");
    }
};
