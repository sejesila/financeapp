<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * IMPROVED VERSION: Links existing Rolling Fund transactions to their respective
     * rolling_funds records using more robust and defensive matching criteria.
     *
     * This migration:
     * - Validates user_id match (better security)
     * - Validates category ownership and type
     * - Checks for completed status BEFORE matching income
     * - Excludes system-generated transaction fees
     * - More defensive against data inconsistencies
     */
    public function up(): void
    {
        // ===================================================================
        // PART 1: Link STAKE transactions (expense - "Rolling Funds Out")
        // ===================================================================
        // These are the initial investments in rolling funds
        DB::statement("
            UPDATE transactions t
            INNER JOIN rolling_funds rf ON
                t.user_id = rf.user_id
                AND t.account_id = rf.account_id
                AND t.date = rf.date
                AND t.amount = rf.stake_amount
                AND t.description = 'Rolling Funds Out'
            INNER JOIN categories c ON
                c.id = t.category_id
                AND c.user_id = t.user_id
                AND c.name = 'Rolling Funds'
                AND c.type = 'expense'
            SET t.rolling_fund_id = rf.id
            WHERE t.rolling_fund_id IS NULL
            AND t.is_transaction_fee = FALSE
            AND t.deleted_at IS NULL
        ");

        // ===================================================================
        // PART 2: Link INCOME transactions (returns - "Rolling Funds Returns")
        // ===================================================================
        // These are the returns/winnings from completed rolling funds
        // NOTE: Only matches COMPLETED rolling funds with actual winnings
        DB::statement("
            UPDATE transactions t
            INNER JOIN rolling_funds rf ON
                t.user_id = rf.user_id
                AND t.account_id = rf.account_id
                AND t.date = rf.completed_date
                AND t.amount = rf.winnings
                AND rf.status = 'completed'
                AND rf.winnings > 0
                AND rf.completed_date IS NOT NULL
            INNER JOIN categories c ON
                c.id = t.category_id
                AND c.user_id = t.user_id
                AND c.name = 'Rolling Funds'
                AND c.type = 'income'
            SET t.rolling_fund_id = rf.id
            WHERE t.rolling_fund_id IS NULL
            AND t.is_transaction_fee = FALSE
            AND t.deleted_at IS NULL
            AND t.description LIKE 'Rolling Funds Returns%'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Unlink all rolling fund transactions by setting rolling_fund_id to NULL
        DB::table('transactions')
            ->whereNotNull('rolling_fund_id')
            ->update(['rolling_fund_id' => null]);
    }
};
