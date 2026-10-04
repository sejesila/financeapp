<?php

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Moves fee transactions that belong to loan-given disbursements out of the
 * everyday "Transaction Fees" category and into "Loan Transaction Fees".
 *
 * New loans are categorised correctly by TransactionService already; this only
 * fixes history. Safe to run more than once: rows already in the loan category
 * are simply re-set to it.
 *
 * Fees are found via loans_given.disbursement_transaction_id ->
 * transactions.fee_for_transaction_id (the same link LoanGivenController uses),
 * so only fees tied to a real loan are touched.
 *
 * No balance or budget resync is needed: both categories are type 'expense',
 * so account balances don't change, and fee transactions never contribute to
 * the budgets table.
 */
return new class extends Migration {
    private const LOAN_FEES = 'Loan Transaction Fees';
    private const NORMAL_FEES = 'Transaction Fees';

    public function up(): void
    {
        $userIds = DB::table('loans_given')
            ->whereNotNull('disbursement_transaction_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $loanFeesCategory = $this->category($userId, self::LOAN_FEES);

            $moved = Transaction::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('is_transaction_fee', true)
                ->whereIn('fee_for_transaction_id', $this->disbursementIds($userId))
                ->where('category_id', '!=', $loanFeesCategory->id)
                ->update(['category_id' => $loanFeesCategory->id]);

            echo "User {$userId}: moved {$moved} loan fee transaction(s)\n";
        }
    }

    public function down(): void
    {
        $userIds = DB::table('loans_given')
            ->whereNotNull('disbursement_transaction_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $loanFeesCategory = Category::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('name', self::LOAN_FEES)
                ->first();

            if (!$loanFeesCategory) {
                continue;
            }

            $normalFeesCategory = $this->category($userId, self::NORMAL_FEES);

            Transaction::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('is_transaction_fee', true)
                ->where('category_id', $loanFeesCategory->id)
                ->update(['category_id' => $normalFeesCategory->id]);
        }
    }

    private function disbursementIds(int $userId)
    {
        return DB::table('loans_given')
            ->where('user_id', $userId)
            ->whereNotNull('disbursement_transaction_id')
            ->select('disbursement_transaction_id');
    }

    private function category(int $userId, string $name): Category
    {
        return Category::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $userId, 'name' => $name],
            ['type' => 'expense', 'icon' => '💸', 'is_active' => true]
        );
    }
};
