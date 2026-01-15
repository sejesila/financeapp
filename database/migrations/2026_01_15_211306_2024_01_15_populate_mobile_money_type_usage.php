<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\MobileMoneyTypeUsage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run if the table exists
        if (!Schema::hasTable('mobile_money_type_usage')) {
            return;
        }

        // Get all transactions with mobile_money_type, grouped by user, account_type, and transaction_type
        $transactions = DB::table('transactions')
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->select(
                'transactions.user_id',
                'accounts.type as account_type',
                'transactions.mobile_money_type as transaction_type',
                DB::raw('COUNT(*) as usage_count')
            )
            ->whereNotNull('transactions.mobile_money_type')
            ->whereNull('transactions.deleted_at') // Exclude soft-deleted transactions
            ->where('accounts.type', 'in', ['mpesa', 'airtel_money'])
            ->groupBy('transactions.user_id', 'accounts.type', 'transactions.mobile_money_type')
            ->get();

        // Update or create usage records
        foreach ($transactions as $row) {
            MobileMoneyTypeUsage::updateOrCreate(
                [
                    'user_id' => $row->user_id,
                    'account_type' => $row->account_type,
                    'transaction_type' => $row->transaction_type,
                ],
                [
                    'usage_count' => $row->usage_count,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset all usage counts to 0
        DB::table('mobile_money_type_usage')->update(['usage_count' => 0]);
    }
};
