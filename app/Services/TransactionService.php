<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Budget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Cache;

class TransactionService
{
    /**
     * M-Pesa transaction costs (Kenya)
     */
    private function getMpesaTransactionCosts(): array
    {
        return [
            'send_money' => [
                ['min' => 1, 'max' => 100, 'cost' => 0],
                ['min' => 101, 'max' => 500, 'cost' => 7],
                ['min' => 501, 'max' => 1000, 'cost' => 13],
                ['min' => 1001, 'max' => 1500, 'cost' => 23],
                ['min' => 1501, 'max' => 2500, 'cost' => 33],
                ['min' => 2501, 'max' => 3500, 'cost' => 53],
                ['min' => 3501, 'max' => 5000, 'cost' => 57],
                ['min' => 5001, 'max' => 7500, 'cost' => 78],
                ['min' => 7501, 'max' => 10000, 'cost' => 90],
                ['min' => 10001, 'max' => 15000, 'cost' => 100],
                ['min' => 15001, 'max' => 20000, 'cost' => 105],
                ['min' => 20001, 'max' => 35000, 'cost' => 108],
                ['min' => 35001, 'max' => 50000, 'cost' => 110],
                ['min' => 50001, 'max' => 150000, 'cost' => 112],
                ['min' => 150001, 'max' => 250000, 'cost' => 115],
                ['min' => 250001, 'max' => 500000, 'cost' => 117],
            ],
            'paybill' => [
                ['min' => 1, 'max' => 49, 'cost' => 0],
                ['min' => 50, 'max' => 100, 'cost' => 0],
                ['min' => 101, 'max' => 500, 'cost' => 5],
                ['min' => 501, 'max' => 1000, 'cost' => 10],
                ['min' => 1001, 'max' => 1500, 'cost' => 15],
                ['min' => 1501, 'max' => 2500, 'cost' => 20],
                ['min' => 2501, 'max' => 3500, 'cost' => 25],
                ['min' => 3501, 'max' => 5000, 'cost' => 34],
                ['min' => 5001, 'max' => 7500, 'cost' => 42],
                ['min' => 7501, 'max' => 10000, 'cost' => 48],
                ['min' => 10001, 'max' => 15000, 'cost' => 57],
                ['min' => 15001, 'max' => 20000, 'cost' => 62],
                ['min' => 20001, 'max' => 25000, 'cost' => 67],
                ['min' => 25001, 'max' => 30000, 'cost' => 72],
                ['min' => 30001, 'max' => 35000, 'cost' => 83],
                ['min' => 35001, 'max' => 40000, 'cost' => 99],
                ['min' => 40001, 'max' => 45000, 'cost' => 103],
                ['min' => 45001, 'max' => 50000, 'cost' => 108],
                ['min' => 50001, 'max' => 70000, 'cost' => 108],
                ['min' => 70001, 'max' => 250000, 'cost' => 108],
            ],
            'buy_goods' => [
                ['min' => 1, 'max' => 500000, 'cost' => 0],
            ],
            'pochi_la_biashara' => [
                ['min' => 1, 'max' => 100, 'cost' => 0],
                ['min' => 101, 'max' => 500, 'cost' => 7],
                ['min' => 501, 'max' => 1000, 'cost' => 13],
                ['min' => 1001, 'max' => 1500, 'cost' => 23],
                ['min' => 1501, 'max' => 2500, 'cost' => 33],
                ['min' => 2501, 'max' => 3500, 'cost' => 53],
                ['min' => 3501, 'max' => 5000, 'cost' => 57],
                ['min' => 5001, 'max' => 7500, 'cost' => 78],
                ['min' => 7501, 'max' => 10000, 'cost' => 90],
                ['min' => 10001, 'max' => 15000, 'cost' => 100],
                ['min' => 15001, 'max' => 20000, 'cost' => 105],
                ['min' => 20001, 'max' => 35000, 'cost' => 108],
                ['min' => 35001, 'max' => 50000, 'cost' => 110],
                ['min' => 50001, 'max' => 150000, 'cost' => 112],
                ['min' => 150001, 'max' => 250000, 'cost' => 115],
                ['min' => 250001, 'max' => 500000, 'cost' => 117],
            ],
        ];
    }

    /**
     * Airtel Money transaction costs (Kenya)
     */
    private function getAirtelMoneyTransactionCosts(): array
    {
        return [
            'send_money' => [
                ['min' => 10, 'max' => 100, 'cost' => 0],
                ['min' => 101, 'max' => 500, 'cost' => 7],
                ['min' => 501, 'max' => 1000, 'cost' => 15],
                ['min' => 1001, 'max' => 1500, 'cost' => 25],
                ['min' => 1501, 'max' => 2500, 'cost' => 35],
                ['min' => 2501, 'max' => 3500, 'cost' => 55],
                ['min' => 3501, 'max' => 5000, 'cost' => 65],
                ['min' => 5001, 'max' => 7500, 'cost' => 80],
                ['min' => 7501, 'max' => 10000, 'cost' => 95],
                ['min' => 10001, 'max' => 15000, 'cost' => 105],
                ['min' => 15001, 'max' => 20000, 'cost' => 110],
                ['min' => 20001, 'max' => 35000, 'cost' => 115],
                ['min' => 35001, 'max' => 50000, 'cost' => 120],
                ['min' => 50001, 'max' => 70000, 'cost' => 125],
                ['min' => 70001, 'max' => 150000, 'cost' => 130],
            ],
            'paybill' => [
                ['min' => 1, 'max' => 150000, 'cost' => 0],
            ],
            'buy_goods' => [
                ['min' => 1, 'max' => 150000, 'cost' => 0],
            ],
        ];
    }

    /**
     * Calculate transaction cost based on amount, account type, transaction type, and category
     */
    private function calculateTransactionCost(float $amount, string $accountType, string $transactionType = 'send_money', ?Category $category = null): float
    {
        // Special case: Internet and Communication has zero transaction fees
        if ($category && $category->name === 'Internet and Communication') {
            return 0;
        }

        $costs = [];

        if ($accountType === 'mpesa') {
            $allCosts = $this->getMpesaTransactionCosts();
            $costs = $allCosts[$transactionType] ?? $allCosts['send_money'];
        } elseif ($accountType === 'airtel_money') {
            $allCosts = $this->getAirtelMoneyTransactionCosts();
            $costs = $allCosts[$transactionType] ?? $allCosts['send_money'];
        } else {
            return 0; // No fees for other account types
        }

        // Find the appropriate cost tier
        foreach ($costs as $tier) {
            if ($amount >= $tier['min'] && $amount <= $tier['max']) {
                return $tier['cost'];
            }
        }

        // If amount exceeds all tiers, return the highest tier cost
        return end($costs)['cost'] ?? 0;
    }

    /**
     * Get payment method based on account type
     */
    private function getPaymentMethod(Account $account): string
    {
        return match($account->type) {
            'cash' => 'Cash',
            'mpesa' => 'Mpesa',
            'airtel_money' => 'Airtel Money',
            'bank' => 'Bank Transfer',
            default => 'Mpesa'
        };
    }

    /**
     * Get formatted transaction type label
     */
    private function getTransactionTypeLabel(string $transactionType): string
    {
        return match($transactionType) {
            'send_money' => 'Send Money',
            'paybill' => 'PayBill',
            'buy_goods' => 'Buy Goods/Till',
            'pochi_la_biashara' => 'Pochi La Biashara',
            default => 'Send Money'
        };
    }

    /**
     * Find or create Transaction Fees category
     */
    private function getFeesCategory(int $userId): Category
    {
        return Category::withoutGlobalScope('ownedByUser')->firstOrCreate(
            [
                'user_id' => $userId,
                'name' => 'Transaction Fees'
            ],
            [
                'type' => 'expense',
                'icon' => '💸',
                'is_active' => true
            ]
        );
    }

    /**
     * Create a new transaction with fee handling and balance updates
     */

    public function createTransaction(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            // Validate account ownership
            $account = Account::findOrFail($data['account_id']);
            if ($account->user_id !== Auth::id()) {
                throw new Exception('Unauthorized access to this account.');
            }

            // Validate category ownership
            $category = Category::findOrFail($data['category_id']);
            if ($category->user_id !== Auth::id()) {
                throw new Exception('Unauthorized access to this category.');
            }

            // Get transaction type, default to send_money
            $transactionType = $data['mobile_money_type'] ?? 'send_money';

            // Calculate transaction cost
            $transactionCost = $this->calculateTransactionCost(
                $data['amount'],
                $account->type,
                $transactionType,
                $category
            );

            $totalAmount = $data['amount'] + $transactionCost;

            // Check if it's an expense and if account has sufficient balance
            if ($category->type === 'expense' && $account->current_balance < $totalAmount) {
                throw new Exception(
                    "Insufficient balance in {$account->name}. Current balance: "
                    . number_format($account->current_balance, 0, '.', ',')
                    . ", Required: " . number_format($totalAmount, 0, '.', ',')
                    . " (Amount: " . number_format($data['amount'], 0, '.', ',')
                    . " + Cost: " . number_format($transactionCost, 0, '.', ',') . ")"
                );
            }

            // Determine payment method
            $paymentMethod = $this->getPaymentMethod($account);

            // Create main transaction with idempotency key
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'date' => $data['date'],
                'description' => $data['description'],
                'amount' => $data['amount'],
                'category_id' => $data['category_id'],
                'account_id' => $data['account_id'],
                'payment_method' => $paymentMethod,
                'mobile_money_type' => $transactionType,
                'is_transaction_fee' => false,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            // Create fee transaction if applicable
            if ($transactionCost > 0) {
                $feeTransaction = $this->createFeeTransaction(
                    $transaction,
                    $transactionCost,
                    $transactionType,
                    $paymentMethod
                );

                // Link fee to main transaction
                $transaction->update([
                    'related_fee_transaction_id' => $feeTransaction->id
                ]);
            }

            // Recalculate account balance
            $this->recalculateAccountBalance($account);

            // Auto-create or update budget entry
            $this->updateBudgetFromTransaction($transaction);

            // Refresh account to get updated balance
            $account->refresh();
            // Clear cache after transaction is created
            $this->clearAccountCache($data['account_id']);

            return $transaction;
        });
    }

    /**
     * Update an existing transaction with balance recalculation
     */
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        // Don't allow editing system-generated transactions
        if ($transaction->is_transaction_fee) {
            throw new Exception('System-generated transaction fees cannot be edited.');
        }

        return DB::transaction(function () use ($transaction, $data) {
            // Validate account ownership
            $newAccount = Account::findOrFail($data['account_id']);
            if ($newAccount->user_id !== Auth::id()) {
                throw new Exception('Unauthorized access to this account.');
            }

            // Validate category ownership
            $category = Category::findOrFail($data['category_id']);
            if ($category->user_id !== Auth::id()) {
                throw new Exception('Unauthorized access to this category.');
            }

            // Store old account for balance recalculation
            $oldAccount = $transaction->account;
            $accountChanged = $oldAccount->id !== $newAccount->id;

            // Get transaction type
            $transactionType = $data['mobile_money_type'] ?? 'send_money';

            // Calculate new transaction cost
            $newTransactionCost = $this->calculateTransactionCost(
                $data['amount'],
                $newAccount->type,
                $transactionType,
                $category
            );


            // Update main transaction
            $transaction->update([
                'date' => $data['date'],
                'description' => $data['description'],
                'amount' => $data['amount'],
                'category_id' => $data['category_id'],
                'account_id' => $data['account_id'],
                'mobile_money_type' => $transactionType,
                'payment_method' => $this->getPaymentMethod($newAccount)
            ]);

            // Handle fee transaction updates
            $this->updateFeeTransaction($transaction, $newTransactionCost, $transactionType);

            // Recalculate balances for affected accounts
            if ($accountChanged) {
                $this->recalculateAccountBalance($oldAccount);
            }

            $this->recalculateAccountBalance($newAccount);
            // Clear cache for both old and new accounts (if changed)
            $this->clearAccountCache($oldAccount->id);
            if ($accountChanged) {
                $this->clearAccountCache($newAccount->id);
            }

            // Update budget
            $this->updateBudgetFromTransaction($transaction);


            return $transaction->fresh(['account', 'category', 'feeTransaction']);
        });
    }

    /**
     * Soft delete a transaction and recalculate balances
     */
    public function deleteTransaction(Transaction $transaction): bool
    {
        // Don't allow deleting system-generated transactions directly
        if ($transaction->is_transaction_fee) {
            throw new Exception('System-generated transaction fees cannot be deleted directly.');
        }

        return DB::transaction(function () use ($transaction) {
            $account = $transaction->account;

            // Delete related fee transaction if exists
            if ($transaction->related_fee_transaction_id) {
                $feeTransaction = Transaction::withoutGlobalScope('ownedByUser')
                    ->find($transaction->related_fee_transaction_id);

                if ($feeTransaction) {
                    $feeTransaction->delete();
                }
            }

            // Delete main transaction
            $transaction->delete();

            // Recalculate account balance
            $this->recalculateAccountBalance($account);
            // Clear cache after deletion

            return true;


        });
    }

    /**
     * Fully recalculate account balance from all transactions
     */
    public function recalculateAccountBalance(Account $account): void
    {
        // Use the existing updateBalance method on the Account model
        $account->updateBalance();

        $account->refresh();
        // Clear cache after recalculation
        $this->clearAccountCache($account->id);

    }

    /**
     * Create a fee transaction linked to main transaction
     */
    private function createFeeTransaction(
        Transaction $mainTransaction,
        float $feeAmount,
        string $transactionType,
        string $paymentMethod
    ): Transaction {
        $feesCategory = $this->getFeesCategory($mainTransaction->user_id);
        $typeLabel = $this->getTransactionTypeLabel($transactionType);

        return Transaction::withoutGlobalScope('ownedByUser')->create([
            'user_id' => $mainTransaction->user_id,
            'date' => $mainTransaction->date,
            'description' => "{$paymentMethod} fee ({$typeLabel}): {$mainTransaction->description}",
            'amount' => $feeAmount,
            'category_id' => $feesCategory->id,
            'account_id' => $mainTransaction->account_id,
            'payment_method' => $paymentMethod,
            'is_transaction_fee' => true,
            'fee_for_transaction_id' => $mainTransaction->id
        ]);
    }

    /**
     * Update or create/delete fee transaction based on new cost
     */
    private function updateFeeTransaction(
        Transaction $transaction,
        float $newTransactionCost,
        string $transactionType
    ): void {
        $existingFee = $transaction->feeTransaction;

        if ($newTransactionCost > 0) {
            if ($existingFee) {
                // Update existing fee transaction
                $typeLabel = $this->getTransactionTypeLabel($transactionType);

                $existingFee->update([
                    'date' => $transaction->date,
                    'description' => "{$transaction->payment_method} fee ({$typeLabel}): {$transaction->description}",
                    'amount' => $newTransactionCost,
                    'account_id' => $transaction->account_id,
                    'payment_method' => $transaction->payment_method
                ]);

            } else {
                // Create new fee transaction
                $feeTransaction = $this->createFeeTransaction(
                    $transaction,
                    $newTransactionCost,
                    $transactionType,
                    $transaction->payment_method
                );

                $transaction->update([
                    'related_fee_transaction_id' => $feeTransaction->id
                ]);

            }
        } else {
            // Delete fee transaction if new cost is 0
            if ($existingFee) {
                $existingFee->delete();
                $transaction->update(['related_fee_transaction_id' => null]);
            }
        }
    }

    /**
     * Automatically create or update budget based on transaction
     */
    private function updateBudgetFromTransaction(Transaction $transaction): void
    {
        // Use period_date if available, otherwise use transaction date
        $date = $transaction->period_date ?? $transaction->date;
        $year = Carbon::parse($date)->year;
        $month = Carbon::parse($date)->month;

        // Find or create budget entry
        $budget = Budget::firstOrCreate(
            [
                'category_id' => $transaction->category_id,
                'year' => $year,
                'month' => $month,
                'user_id' => $transaction->user_id
            ],
            [
                'amount' => 0
            ]
        );

        // Update budget amount by adding this transaction
        $budget->amount += $transaction->amount;
        $budget->save();

    }
    /**
     * Clear account statistics cache
     */
    private function clearAccountCache(int $accountId): void
    {
        Cache::forget("account.{$accountId}.stats");
    }
}
