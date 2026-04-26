<?php

    namespace App\Services;

    use App\Models\Account;
    use App\Models\Budget;
    use App\Models\Category;
    use App\Models\Transaction;
    use App\Models\TransactionSplit;
    use Carbon\Carbon;
    use Exception;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\DB;

    class TransactionService
    {
        /**
         * Create a new transaction with fee handling and balance updates
         */
        public function createTransaction(array $data): Transaction
        {
            return DB::transaction(function () use ($data) {
                $category = Category::findOrFail($data['category_id']);
                if ($category->user_id !== Auth::id()) {
                    throw new Exception('Unauthorized access to this category.');
                }

                $isSplit = !empty($data['splits']);

                $primaryAccountId = $isSplit
                    ? $data['splits'][0]['account_id']
                    : $data['account_id'];

                $primaryAccount = Account::find($primaryAccountId);
                $isMobileMoney  = in_array($primaryAccount?->type, ['mpesa', 'airtel_money']);

                $transaction = Transaction::create([
                    'user_id'           => Auth::id(),
                    'date'              => $data['date'],
                    'description'       => $data['description'],
                    'amount'            => $data['amount'],
                    'category_id'       => $data['category_id'],
                    'account_id'        => $primaryAccountId,
                    'is_split'          => $isSplit,
                    'payment_method'    => $isSplit ? 'Split' : $this->getPaymentMethod($primaryAccount),
                    'mobile_money_type' => $isSplit ? null : ($isMobileMoney ? ($data['mobile_money_type'] ?? 'send_money') : null),
                ]);

                if ($isSplit) {
                    $splits = $data['splits'];

                    $splitTotal = array_sum(array_column($splits, 'amount'));
                    if (round($splitTotal, 2) !== round((float) $data['amount'], 2)) {
                        throw new Exception(
                            "Split amounts (" . number_format($splitTotal, 2) . ") must equal total (" . number_format($data['amount'], 2) . ")."
                        );
                    }

                    $affectedAccountIds = [];

                    foreach ($splits as $splitData) {
                        $account = Account::findOrFail($splitData['account_id']);
                        if ($account->user_id !== Auth::id()) {
                            throw new Exception('Unauthorized account access.');
                        }

                        $splitIsMobile   = in_array($account->type, ['mpesa', 'airtel_money']);
                        $mobileMoneyType = $splitIsMobile ? ($splitData['mobile_money_type'] ?? 'send_money') : null;
                        $fee             = $this->calculateTransactionCost($splitData['amount'], $account->type, $mobileMoneyType ?? 'send_money', $category);
                        $paymentMethod   = $this->getPaymentMethod($account);

                        if ($category->type === 'expense' && $account->current_balance < ($splitData['amount'] + $fee)) {
                            throw new Exception(
                                "Insufficient balance in {$account->name}. " .
                                "Available: KSh " . number_format($account->current_balance, 2) . ", " .
                                "Required: KSh " . number_format($splitData['amount'] + $fee, 2)
                            );
                        }

                        TransactionSplit::create([
                            'transaction_id'    => $transaction->id,
                            'account_id'        => $account->id,
                            'amount'            => $splitData['amount'],
                            'payment_method'    => $paymentMethod,
                            'mobile_money_type' => $mobileMoneyType,
                        ]);

                        if ($fee > 0) {
                            $feeTransaction = Transaction::withoutGlobalScope('ownedByUser')->create([
                                'user_id'                => Auth::id(),
                                'date'                   => $transaction->date,
                                'description'            => "{$this->getPaymentMethod($account)} fee ({$this->getTransactionTypeLabel($mobileMoneyType)}): {$transaction->description}",
                                'amount'                 => $fee,
                                'category_id'            => $this->getFeesCategory(Auth::id())->id,
                                'account_id'             => $account->id,
                                'payment_method'         => $paymentMethod,
                                'is_transaction_fee'     => true,
                                'fee_for_transaction_id' => $transaction->id,
                            ]);

                            TransactionSplit::where('transaction_id', $transaction->id)
                                ->where('account_id', $account->id)
                                ->latest()
                                ->first()
                                ?->update(['related_fee_transaction_id' => $feeTransaction->id]);
                        }

                        $affectedAccountIds[] = $account->id;
                    }

                    foreach (array_unique($affectedAccountIds) as $accountId) {
                        $this->recalculateAccountBalance(Account::find($accountId));
                    }

                } else {
                    $account = Account::findOrFail($data['account_id']);
                    if ($account->user_id !== Auth::id()) {
                        throw new Exception('Unauthorized access to this account.');
                    }

                    $transactionType = $isMobileMoney ? ($data['mobile_money_type'] ?? 'send_money') : 'send_money';
                    $fee             = $this->calculateTransactionCost($data['amount'], $account->type, $transactionType, $category);

                    if ($category->type === 'expense' && $account->current_balance < ($data['amount'] + $fee)) {
                        throw new Exception(
                            "Insufficient balance in {$account->name}. " .
                            "Available: KSh " . number_format($account->current_balance, 2) . ", " .
                            "Required: KSh " . number_format($data['amount'] + $fee, 2)
                        );
                    }

                    if ($fee > 0) {
                        $feeTransaction = $this->createFeeTransaction(
                            $transaction,
                            $fee,
                            $transactionType,
                            $this->getPaymentMethod($account)
                        );
                        $transaction->update(['related_fee_transaction_id' => $feeTransaction->id]);
                    }

                    $this->recalculateAccountBalance($account);
                }

                $this->updateBudgetFromTransaction($transaction);
                $this->clearAccountCache($primaryAccountId);

                return $transaction->fresh();
            });
        }

        /**
         * Calculate transaction cost based on amount, account type, transaction type, and category
         */
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

        /**
         * M-Pesa transaction costs (Kenya)
         */
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
            ];
        }

        /**
         * Airtel Money transaction costs (Kenya)
         */
        private function getAirtelMoneyTransactionCosts(): array
        {
            return [
                'send_money' => [
                    ['min' => 10,    'max' => 100,    'cost' => 0],
                    ['min' => 101,   'max' => 500,    'cost' => 6],
                    ['min' => 501,   'max' => 1000,   'cost' => 11],
                    ['min' => 1001,  'max' => 1500,   'cost' => 20],
                    ['min' => 1501,  'max' => 2500,   'cost' => 30],
                    ['min' => 2501,  'max' => 3500,   'cost' => 50],
                    ['min' => 3501,  'max' => 5000,   'cost' => 50],
                    ['min' => 5001,  'max' => 7500,   'cost' => 70],
                    ['min' => 7501,  'max' => 10000,  'cost' => 80],
                    ['min' => 10001, 'max' => 15000,  'cost' => 90],
                    ['min' => 15001, 'max' => 20000,  'cost' => 95],
                    ['min' => 20001, 'max' => 35000,  'cost' => 95],
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
                    ['min' => 70001, 'max' => 250000, 'cost' => 108]
                ],
                'buy_goods' => [
                    ['min' => 1, 'max' => 150000, 'cost' => 0],
                ],
            ];
        }

        /**
         * Get payment method based on account type
         */
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

        /**
         * Create a fee transaction linked to main transaction
         */
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

        /**
         * Find or create Transaction Fees category
         */
        private function getFeesCategory(int $userId): Category
        {
            return Category::withoutGlobalScope('ownedByUser')->firstOrCreate(
                ['user_id' => $userId, 'name' => 'Transaction Fees'],
                ['type' => 'expense', 'icon' => '💸', 'is_active' => true]
            );
        }

        /**
         * Get formatted transaction type label
         */
        private function getTransactionTypeLabel(?string $transactionType): string
        {
            return match ($transactionType) {
                'send_money'         => 'Send Money',
                'paybill'            => 'PayBill',
                'buy_goods'          => 'Buy Goods/Till',
                'pochi_la_biashara'  => 'Pochi La Biashara',
                default              => 'Send Money',
            };
        }

        /**
         * Fully recalculate account balance from all transactions
         */
        public function recalculateAccountBalance(Account $account): void
        {
            $account->updateBalance();
            $account->refresh();
            $this->clearAccountCache($account->id);
        }

        /**
         * Clear account statistics cache
         */
        private function clearAccountCache(int $accountId): void
        {
            Cache::forget("account.{$accountId}.stats");
        }

        /**
         * Automatically create or update budget based on transaction
         */
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

        /**
         * Update an existing transaction with balance recalculation
         */
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

                $oldAccount     = $transaction->account;
                $accountChanged = $oldAccount->id !== $newAccount->id;

                // Only use mobile_money_type for mobile money accounts
                $isMobileMoney   = in_array($newAccount->type, ['mpesa', 'airtel_money']);
                $transactionType = $isMobileMoney ? ($data['mobile_money_type'] ?? 'send_money') : null;

                $newTransactionCost = $this->calculateTransactionCost(
                    $data['amount'],
                    $newAccount->type,
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

                $this->updateBudgetFromTransaction($transaction);

                return $transaction->fresh(['account', 'category', 'feeTransaction']);
            });
        }

        /**
         * Update or create/delete fee transaction based on new cost
         */
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
                        'date'          => $transaction->date,
                        'description'   => "{$transaction->payment_method} fee ({$typeLabel}): {$transaction->description}",
                        'amount'        => $newTransactionCost,
                        'account_id'    => $transaction->account_id,
                        'payment_method'=> $transaction->payment_method,
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

        /**
         * Soft delete a transaction and recalculate balances
         */
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

                    if ($feeTransaction) {
                        $feeTransaction->delete();
                    }
                }

                $transaction->delete();
                $this->recalculateAccountBalance($account);

                return true;
            });
        }
    }
