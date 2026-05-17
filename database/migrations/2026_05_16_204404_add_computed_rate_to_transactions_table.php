<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
// database/migrations/xxxx_add_computed_rate_to_transactions_table.php
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('computed_rate', 10, 6)->nullable()->after('is_split');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('computed_rate');
        });
    }
};
