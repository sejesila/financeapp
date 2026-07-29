<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('cash', 'mpesa', 'airtel_money', 'bank', 'savings', 'wallet', 'referrer_float') DEFAULT 'cash'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('cash', 'mpesa', 'airtel_money', 'bank', 'savings', 'wallet') DEFAULT 'cash'");
    }
};
