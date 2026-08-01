<?php
// app/Services/TransactionRecorder.php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TransactionRecorder
{
    public function __construct(private CategoryResolver $categories)
    {
    }

    /**
     * Record a regular expense or income transaction, plus an optional fee
     * transaction, then update the account balance.
     *
     * Returns a 201 JSON response on success or 404 if no matching account
     * is found.
     */
    public function record(User $user, array $parsed): JsonResponse
    {
        $account = $this->resolveAccount($user, $parsed);

        if (!$account) {
            Log::warning('Webhook: no matching account found', [
                'user_id' => $user->id,
                'bank' => $parsed['bank'],
                'subtype' => $parsed['subtype'],
            ]);
            return response()->json(['error' => 'No matching account found'], 404);
        }

        $categoryName = $this->categories->resolveName($parsed);
        $category = $this->categories->findOrCreate($user, $categoryName, $parsed['type']);

        $paymentMethod = match ($parsed['bank']) {
            'im_bank' => 'I&M Bank',
            'airtel' => 'Airtel Money',
            default => 'Mpesa',
        };

        // Map subtype → mobile_money_type
        $mobileMoneyType = match ($parsed['subtype'] ?? '') {
            'send_money' => 'send_money',
            'paybill' => 'paybill',
            'till' => 'buy_goods',
            'withdrawal' => 'withdrawal',
            'airtime' => 'airtime',
            'pochi' => 'pochi_la_biashara',
            'receive_money' => 'receive_money',
            default => null,
        };

        $transaction = Transaction::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => $parsed['amount'],
            'date' => $parsed['date'],
            'description' => $parsed['description'] . ' [' . $parsed['reference'] . ']',
            'payment_method' => $paymentMethod,
            'mobile_money_type' => $mobileMoneyType,

        ]);

        // ── Fee transaction (optional) ────────────────────────────────────
        if (!empty($parsed['fee']) && $parsed['fee'] > 0) {
            $feeCategory = $this->categories->findOrCreate($user, 'Transaction Fees', 'expense');

            $feeLabel = $parsed['reference']
                . (!empty($parsed['to_account_hint'])
                    ? ' (' . ucfirst($parsed['to_account_hint']) . ')'
                    : '');

            $feeTransaction = Transaction::withoutGlobalScopes()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $feeCategory->id,
                'amount' => $parsed['fee'],
                'date' => $parsed['date'],
                'description' => 'Transaction fee for ' . $feeLabel,
                'payment_method' => $paymentMethod,
                'is_transaction_fee' => true,
                'related_fee_transaction_id' => $transaction->id,
                'fee_for_transaction_id' => $transaction->id,

            ]);

            $transaction->update(['related_fee_transaction_id' => $feeTransaction->id]);
        }

        $account->updateBalance();

        Log::info('Webhook: transaction created', [
            'user_id' => $user->id,
            'bank' => $parsed['bank'],
            'reference' => $parsed['reference'],
            'amount' => $parsed['amount'],
            'type' => $parsed['type'],
            'subtype' => $parsed['subtype'],
            'account' => $account->name,
            'category' => $categoryName,
            'transaction' => $transaction->id,
        ]);

        $response = [
            'status' => 'created',
            'bank' => $parsed['bank'],
            'reference' => $parsed['reference'],
            'amount' => $parsed['amount'],
            'type' => $parsed['type'],
            'subtype' => $parsed['subtype'],
            'account' => $account->name,
            'category' => $categoryName,
        ];

        if (!empty($parsed['fee']) && $parsed['fee'] > 0) {
            $response['fee'] = $parsed['fee'];
        }

        return response()->json($response, 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    private function resolveAccount(User $user, array $parsed): ?Account
    {
        if ($parsed['bank'] === 'im_bank') {
            return Account::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('type', 'bank')
                ->where('is_active', true)
                ->first();
        }
        if ($parsed['bank'] === 'airtel') {
            return Account::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('type', 'airtel_money')
                ->where('is_active', true)
                ->first();
        }

        return Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'mpesa')
            ->where('is_active', true)
            ->first();
    }

    public function applyCashback(User $user, array $parsed): JsonResponse
    {
        $account = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'airtel_money')
            ->where('is_active', true)
            ->first();

        if (!$account) {
            return response()->json(['error' => 'No matching account found'], 404);
        }

        $cashback = $parsed['amount'];

        // Nearest preceding fee transaction, within 30 days, with enough
        // headroom to absorb this cashback without going negative.
        $feeTransaction = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('account_id', $account->id)
            ->where('is_transaction_fee', true)
            ->where('date', '<=', $parsed['date'])
            ->where('date', '>=', $parsed['date']->copy()->subDays(30))
            ->where('amount', '>=', $cashback)
            ->orderByDesc('date')
            ->first();

        if (!$feeTransaction) {
            // No eligible fee within the window — fall back to recording as income
            return $this->record($user, array_merge($parsed, [
                'type' => 'income',
            ]));
        }

        $originalFee = (float)$feeTransaction->amount;
        $newFee = round($originalFee - $cashback, 2);

        $feeTransaction->update([
            'amount' => $newFee,
            'description' => $feeTransaction->description
                . " (Ksh {$cashback} cashback applied "
                . $parsed['date']->format('d/m/y')
                . ", ref {$parsed['reference']})",
        ]);

        $account->updateBalance();

        Log::info('Webhook: cashback applied to fee', [
            'user_id' => $user->id,
            'cashback_reference' => $parsed['reference'],
            'fee_transaction_id' => $feeTransaction->id,
            'original_fee' => $originalFee,
            'cashback' => $cashback,
            'new_fee' => $newFee,
        ]);

        return response()->json([
            'status' => 'cashback_applied',
            'fee_transaction_id' => $feeTransaction->id,
            'original_fee' => $originalFee,
            'cashback' => $cashback,
            'new_fee' => $newFee,
        ], 200);
    }
}
