<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Composite index for account transactions list (most common query)
            $table->index(['account_id', 'date', 'id'], 'idx_account_date_id');

            // Index for user + date queries
            $table->index(['user_id', 'date'], 'idx_user_date');

            // Index for category filtering
            $table->index(['category_id', 'date'], 'idx_category_date');

            // Index for fee transactions
            $table->index(['is_transaction_fee', 'account_id'], 'idx_fee_account');

            // Index for monthly/yearly aggregations
            $table->index(['account_id', 'date', 'category_id'], 'idx_account_date_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_account_date_id');
            $table->dropIndex('idx_user_date');
            $table->dropIndex('idx_category_date');
            $table->dropIndex('idx_fee_account');
            $table->dropIndex('idx_account_date_category');
        });
    }
};
