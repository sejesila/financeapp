<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function createTransaction(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $account = Account::lockForUpdate()->findOrFail($data['account_id']);

            if ($account->user_id !== Auth::id()) {
                throw new Exception('Unauthorized access to this account.');
            }

            $isMobileMoney = in_array($account->type, ['mpesa', 'airtel_money']);

            $category = Category::findOrFail($data['category_id']);
            if ($category->user_id !== Auth::id()) {
                throw new Exception('Unauthorized access to this category.');
            }

            $transactionType = $isMobileMoney
                ? ($data['mobile_money_type'] ?? 'send_money')
                : 'send_money';

            $fee = $this->resolveTransactionCost($data, $account, $transactionType, $category);

            if ($category->type === 'expense'
                && (float) $account->current_balance < ($data['amount'] + $fee)) {
                throw new Exception(
                    "Insufficient balance in {$account->name}. " .
                    "Available: KSh " . number_format($account->current_balance, 2) . ", " .
                    "Required: KSh " . number_format($data['amount'] + $fee, 2)
                );
            }

            $transaction = Transaction::create([
                'user_id'           => Auth::id(),
                'date'              => $data['date'],
                'description'       => $data['description'],
                'amount'            => $data['amount'],
                'category_id'       => $data['category_id'],
                'account_id'        => $data['account_id'],
                'payment_method'    => $this->getPaymentMethod($account),
                'mobile_money_type' => $isMobileMoney ? $transactionType : null,
            ]);

            if ($fee > 0) {
                $feeTransaction = $this->createFeeTransaction(
                    $transaction, $fee, $transactionType, $this->getPaymentMethod($account)
                );
                $transaction->update(['related_fee_transaction_id' => $feeTransaction->id]);
            }

            $this->recalculateAccountBalance($account);
            $this->updateBudgetFromTransaction($transaction);
            $this->clearAccountCache($account->id);

            return $transaction->fresh();
        });
    }

    private function calculateTransactionCost(float $amount, string $accountType, string $transactionType = 'send_money', ?Category $category = null): float
    {
        if ($category && $category->name === 'Internet and Communication') {
            return 0;
        }

        $costs = [];

        if ($accountType === 'mpesa') {
            $allCosts = $this->getMpesaTransactionCosts();
            $costs    = $allCosts[$transactionType] ?? $allCosts['send_money'];
        } elseif ($accountType === 'airtel_money') {
            $allCosts = $this->getAirtelMoneyTransactionCosts();
            $costs    = $allCosts[$transactionType] ?? $allCosts['send_money'];
        } else {
            return 0;
        }

        foreach ($costs as $tier) {
            if ($amount >= $tier['min'] && $amount <= $tier['max']) {
                return $tier['cost'];
            }
        }

        return end($costs)['cost'] ?? 0;
    }

    private function getMpesaTransactionCosts(): array
    {
        return [
            'send_money' => [
                ['min' => 1,      'max' => 100,    'cost' => 0],
                ['min' => 101,    'max' => 500,    'cost' => 7],
                ['min' => 501,    'max' => 1000,   'cost' => 13],
                ['min' => 1001,   'max' => 1500,   'cost' => 23],
                ['min' => 1501,   'max' => 2500,   'cost' => 33],
                ['min' => 2501,   'max' => 3500,   'cost' => 53],
                ['min' => 3501,   'max' => 5000,   'cost' => 57],
                ['min' => 5001,   'max' => 7500,   'cost' => 78],
                ['min' => 7501,   'max' => 10000,  'cost' => 90],
                ['min' => 10001,  'max' => 15000,  'cost' => 100],
                ['min' => 15001,  'max' => 20000,  'cost' => 105],
                ['min' => 20001,  'max' => 35000,  'cost' => 108],
                ['min' => 35001,  'max' => 50000,  'cost' => 110],
                ['min' => 50001,  'max' => 150000, 'cost' => 112],
                ['min' => 150001, 'max' => 250000, 'cost' => 115],
                ['min' => 250001, 'max' => 500000, 'cost' => 117],
            ],
            'paybill' => [
                ['min' => 1,     'max' => 100,     'cost' => 0],
                ['min' => 101,   'max' => 500,    'cost' => 5],
                ['min' => 501,   'max' => 1000,   'cost' => 10],
                ['min' => 1001,  'max' => 1500,   'cost' => 15],
                ['min' => 1501,  'max' => 2500,   'cost' => 20],
                ['min' => 2501,  'max' => 3500,   'cost' => 25],
                ['min' => 3501,  'max' => 5000,   'cost' => 34],
                ['min' => 5001,  'max' => 7500,   'cost' => 42],
                ['min' => 7501,  'max' => 10000,  'cost' => 48],
                ['min' => 10001, 'max' => 15000,  'cost' => 57],
                ['min' => 15001, 'max' => 20000,  'cost' => 62],
                ['min' => 20001, 'max' => 25000,  'cost' => 67],
                ['min' => 25001, 'max' => 30000,  'cost' => 72],
                ['min' => 30001, 'max' => 35000,  'cost' => 83],
                ['min' => 35001, 'max' => 40000,  'cost' => 99],
                ['min' => 40001, 'max' => 45000,  'cost' => 103],
                ['min' => 45001, 'max' => 50000,  'cost' => 108],
                ['min' => 50001, 'max' => 70000,  'cost' => 108],
                ['min' => 70001, 'max' => 250000, 'cost' => 108],
            ],
            'buy_goods' => [
                ['min' => 1, 'max' => 500000, 'cost' => 0],
            ],
            'pochi_la_biashara' => [
                ['min' => 1,      'max' => 200,    'cost' => 0],
                ['min' => 201,    'max' => 500,    'cost' => 7],
                ['min' => 501,    'max' => 1000,   'cost' => 13],
                ['min' => 1001,   'max' => 1500,   'cost' => 23],
                ['min' => 1501,   'max' => 2500,   'cost' => 33],
                ['min' => 2501,   'max' => 250000, 'cost' => 50],
            ],
        ];
    }
    /**
     * Fee resolution for a transaction. If the caller supplied an explicit
     * 'manual_fee' (e.g. loan disbursement letting the user type in the real
     * M-Pesa charge, or a fee for an account type the tier tables don't cover),
     * that wins outright — no tier lookup happens. Otherwise falls back to the
     * normal auto-calculated cost, exactly as before. This keeps every other
     * caller of createTransaction() (the regular transaction form) completely
     * unaffected, since they never pass 'manual_fee'.
     */
    private function resolveTransactionCost(array $data, Account $account, string $transactionType, Category $category): float
    {
        if (array_key_exists('manual_fee', $data) && $data['manual_fee'] !== null && $data['manual_fee'] !== '') {
            return max(0, (float) $data['manual_fee']);
        }

        return $this->calculateTransactionCost($data['amount'], $account->type, $transactionType, $category);
    }

    private function getAirtelMoneyTransactionCosts(): array
    {
        return [
            'send_money' => [
                ['min' => 10,    'max' => 100,    'cost' => 0],
                ['min' => 101,   'max' => 500,    'cost' => 7],
                ['min' => 501,   'max' => 1000,   'cost' => 13],
                ['min' => 1001,  'max' => 1500,   'cost' => 23],
                ['min' => 1501,  'max' => 2500,   'cost' => 33],
                ['min' => 2501,  'max' => 3500,   'cost' => 53],
                ['min' => 3501,  'max' => 5000,   'cost' => 57],
                ['min' => 5001,  'max' => 7500,   'cost' => 78],
                ['min' => 7501,  'max' => 10000,  'cost' => 90],
                ['min' => 10001, 'max' => 15000,  'cost' => 100],
                ['min' => 15001, 'max' => 20000,  'cost' => 105],
                ['min' => 20001, 'max' => 35000,  'cost' => 108],
            ],

            'paybill' => [
                ['min' => 1,     'max' => 100,     'cost' => 0],
                ['min' => 101,   'max' => 500,    'cost' => 4],
                ['min' => 501,   'max' => 1000,   'cost' => 9],
                ['min' => 1001,  'max' => 1500,   'cost' => 12],
                ['min' => 1501,  'max' => 2500,   'cost' => 13],
                ['min' => 2501,  'max' => 5000,   'cost' => 20],
                ['min' => 5001,  'max' => 7500,   'cost' => 33],
                ['min' => 7501,  'max' => 10000,  'cost' => 37],
                ['min' => 10001, 'max' => 15000,  'cost' => 57],
                ['min' => 15001, 'max' => 20000,  'cost' => 62],
                ['min' => 20001, 'max' => 25000,  'cost' => 67],
                ['min' => 25001, 'max' => 30000,  'cost' => 72],
                ['min' => 30001, 'max' => 35000,  'cost' => 83],
                ['min' => 35001, 'max' => 40000,  'cost' => 99],
                ['min' => 40001, 'max' => 45000,  'cost' => 103],
                ['min' => 45001, 'max' => 50000,  'cost' => 108],
                ['min' => 50001, 'max' => 70000,  'cost' => 108],
                ['min' => 70001, 'max' => 250000, 'cost' => 108],
            ],
            'buy_goods' => [
                ['min' => 1, 'max' => 150000, 'cost' => 0],
            ],
        ];
    }

    private function getPaymentMethod(Account $account): string
    {
        return match ($account->type) {
            'cash'         => 'Cash',
            'mpesa'        => 'Mpesa',
            'airtel_money' => 'Airtel Money',
            'bank'         => 'Bank Transfer',
            default        => 'Mpesa',
        };
    }

    private function createFeeTransaction(
        Transaction $mainTransaction,
        float       $feeAmount,
        string      $transactionType,
        string      $paymentMethod
    ): Transaction {
        $feesCategory = $this->getFeesCategory($mainTransaction->user_id);
        $typeLabel    = $this->getTransactionTypeLabel($transactionType);

        return Transaction::withoutGlobalScope('ownedByUser')->create([
            'user_id'                => $mainTransaction->user_id,
            'date'                   => $mainTransaction->date,
            'description'            => "{$paymentMethod} fee ({$typeLabel}): {$mainTransaction->description}",
            'amount'                 => $feeAmount,
            'category_id'            => $feesCategory->id,
            'account_id'             => $mainTransaction->account_id,
            'payment_method'         => $paymentMethod,
            'is_transaction_fee'     => true,
            'fee_for_transaction_id' => $mainTransaction->id,
        ]);
    }

    private function getFeesCategory(int $userId): Category
    {
        return Category::withoutGlobalScope('ownedByUser')->firstOrCreate(
            ['user_id' => $userId, 'name' => 'Transaction Fees'],
            ['type' => 'expense', 'icon' => '💸', 'is_active' => true]
        );
    }

    private function getTransactionTypeLabel(?string $transactionType): string
    {
        return match ($transactionType) {
            'send_money'        => 'Send Money',
            'paybill'           => 'PayBill',
            'buy_goods'         => 'Buy Goods/Till',
            'pochi_la_biashara' => 'Pochi La Biashara',
            default             => 'Send Money',
        };
    }

    public function recalculateAccountBalance(Account $account): void
    {
        $account->updateBalance();
        $account->refresh();
        $this->clearAccountCache($account->id);
    }

    private function clearAccountCache(int $accountId): void
    {
        Cache::forget("account.{$accountId}.stats");
    }

    private function updateBudgetFromTransaction(Transaction $transaction): void
    {
        $date  = $transaction->period_date ?? $transaction->date;
        $year  = Carbon::parse($date)->year;
        $month = Carbon::parse($date)->month;

        $budget = Budget::firstOrCreate(
            [
                'category_id' => $transaction->category_id,
                'year'        => $year,
                'month'       => $month,
                'user_id'     => $transaction->user_id,
            ],
            ['amount' => 0]
        );

        $budget->amount += $transaction->amount;
        $budget->save();
    }
    private function reverseBudgetContribution(int $userId, int $categoryId, string $date, float $amount): void
    {
        $parsed = Carbon::parse($date);

        $budget = Budget::where([
            'category_id' => $categoryId,
            'year'        => $parsed->year,
            'month'       => $parsed->month,
            'user_id'     => $userId,
        ])->first();

        if ($budget) {
            $budget->amount -= $amount;
            $budget->save();
        }
    }

    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        if ($transaction->is_transaction_fee) {
            throw new Exception('System-generated transaction fees cannot be edited.');
        }

        return DB::transaction(function () use ($transaction, $data) {
            $newAccount = Account::findOrFail($data['account_id']);
            if ($newAccount->user_id !== Auth::id()) {
                throw new Exception('Unauthorized access to this account.');
            }

            $category = Category::findOrFail($data['category_id']);
            if ($category->user_id !== Auth::id()) {
                throw new Exception('Unauthorized access to this category.');
            }

            // Capture pre-update state so its budget contribution can be reversed.
            $oldAmount      = (float) $transaction->amount;
            $oldCategoryId  = $transaction->category_id;
            $oldBudgetDate  = $transaction->period_date ?? $transaction->date;

            $oldAccount     = $transaction->account;
            $accountChanged = $oldAccount->id !== $newAccount->id;

            $isMobileMoney   = in_array($newAccount->type, ['mpesa', 'airtel_money']);
            $transactionType = $isMobileMoney ? ($data['mobile_money_type'] ?? 'send_money') : null;

            $newTransactionCost = $this->resolveTransactionCost(
                $data,
                $newAccount,
                $transactionType ?? 'send_money',
                $category
            );

            $transaction->update([
                'date'              => $data['date'],
                'description'       => $data['description'],
                'amount'            => $data['amount'],
                'category_id'       => $data['category_id'],
                'account_id'        => $data['account_id'],
                'mobile_money_type' => $transactionType,
                'payment_method'    => $this->getPaymentMethod($newAccount),
            ]);

            $this->updateFeeTransaction($transaction, $newTransactionCost, $transactionType ?? 'send_money');

            if ($accountChanged) {
                $this->recalculateAccountBalance($oldAccount);
            }

            $this->recalculateAccountBalance($newAccount);
            $this->clearAccountCache($oldAccount->id);
            if ($accountChanged) {
                $this->clearAccountCache($newAccount->id);
            }

            $this->reverseBudgetContribution($transaction->user_id, $oldCategoryId, $oldBudgetDate, $oldAmount);
            $this->updateBudgetFromTransaction($transaction);

            return $transaction->fresh(['account', 'category', 'feeTransaction']);
        });
    }

    private function updateFeeTransaction(
        Transaction $transaction,
        float       $newTransactionCost,
        string      $transactionType
    ): void {
        $existingFee = $transaction->feeTransaction;

        if ($newTransactionCost > 0) {
            if ($existingFee) {
                $typeLabel = $this->getTransactionTypeLabel($transactionType);
                $existingFee->update([
                    'date'           => $transaction->date,
                    'description'    => "{$transaction->payment_method} fee ({$typeLabel}): {$transaction->description}",
                    'amount'         => $newTransactionCost,
                    'account_id'     => $transaction->account_id,
                    'payment_method' => $transaction->payment_method,
                ]);
            } else {
                $feeTransaction = $this->createFeeTransaction(
                    $transaction,
                    $newTransactionCost,
                    $transactionType,
                    $transaction->payment_method
                );
                $transaction->update(['related_fee_transaction_id' => $feeTransaction->id]);
            }
        } else {
            if ($existingFee) {
                $existingFee->delete();
                $transaction->update(['related_fee_transaction_id' => null]);
            }
        }
    }

    public function deleteTransaction(Transaction $transaction): bool
    {
        if ($transaction->is_transaction_fee) {
            throw new Exception('System-generated transaction fees cannot be deleted directly.');
        }

        return DB::transaction(function () use ($transaction) {
            $account = $transaction->account;

            if ($transaction->related_fee_transaction_id) {
                $feeTransaction = Transaction::withoutGlobalScope('ownedByUser')
                    ->find($transaction->related_fee_transaction_id);
                $feeTransaction?->delete();
            }

            $this->reverseBudgetContribution(
                $transaction->user_id,
                $transaction->category_id,
                $transaction->period_date ?? $transaction->date,
                (float) $transaction->amount
            );

            $transaction->delete();
            $this->recalculateAccountBalance($account);

            return true;
        });
    }
}
