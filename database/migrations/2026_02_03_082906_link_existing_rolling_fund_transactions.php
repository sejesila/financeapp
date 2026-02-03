<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration links existing Rolling Fund transactions to their respective
     * rolling_funds records based on matching date, amount, and account.
     */
    public function up(): void
    {
        // Link existing stake transactions (expense)
        DB::statement("
            UPDATE transactions t
            INNER JOIN rolling_funds rf ON
                t.account_id = rf.account_id
                AND t.date = rf.date
                AND t.amount = rf.stake_amount
                AND t.payment_method = 'Rolling Funds'
                AND t.description = 'Rolling Funds Out'
            SET t.rolling_fund_id = rf.id
            WHERE t.rolling_fund_id IS NULL
        ");

        // Link existing winnings transactions (income)
        DB::statement("
            UPDATE transactions t
            INNER JOIN rolling_funds rf ON
                t.account_id = rf.account_id
                AND t.date = rf.completed_date
                AND t.amount = rf.winnings
                AND t.payment_method = 'Rolling Funds'
                AND t.description LIKE 'Rolling Funds Returns%'
            SET t.rolling_fund_id = rf.id
            WHERE t.rolling_fund_id IS NULL
                AND rf.status = 'completed'
                AND rf.winnings > 0
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Unlink all rolling fund transactions
        DB::table('transactions')
            ->whereNotNull('rolling_fund_id')
            ->update(['rolling_fund_id' => null]);
    }
};
