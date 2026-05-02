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
    public function __construct(private CategoryResolver $categories) {}

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
                'bank'    => $parsed['bank'],
                'subtype' => $parsed['subtype'],
            ]);
            return response()->json(['error' => 'No matching account found'], 404);
        }

        $categoryName = $this->categories->resolveName($parsed);
        $category     = $this->categories->findOrCreate($user, $categoryName, $parsed['type']);

        $paymentMethod = $parsed['bank'] === 'im_bank' ? 'I&M Bank' : 'Mpesa';

        // ── Main transaction ──────────────────────────────────────────────
        $transaction = Transaction::withoutGlobalScopes()->create([
            'user_id'        => $user->id,
            'account_id'     => $account->id,
            'category_id'    => $category->id,
            'amount'         => $parsed['amount'],
            'date'           => $parsed['date'],
            'description'    => $parsed['description'] . ' [' . $parsed['reference'] . ']',
            'payment_method' => $paymentMethod,
            'is_reversal'    => false,
            'is_split'       => false,
        ]);

        // ── Fee transaction (optional) ────────────────────────────────────
        if (!empty($parsed['fee']) && $parsed['fee'] > 0) {
            $feeCategory = $this->categories->findOrCreate($user, 'Transaction Fees', 'expense');

            $feeTransaction = Transaction::withoutGlobalScopes()->create([
                'user_id'                    => $user->id,
                'account_id'                 => $account->id,
                'category_id'                => $feeCategory->id,
                'amount'                     => $parsed['fee'],
                'date'                       => $parsed['date'],
                'description'                => 'Transaction fee for ' . $parsed['reference'],
                'payment_method'             => $paymentMethod,
                'is_transaction_fee'         => true,
                'related_fee_transaction_id' => $transaction->id,
                'is_reversal'                => false,
                'is_split'                   => false,
            ]);

            $transaction->update(['related_fee_transaction_id' => $feeTransaction->id]);
        }

        $account->updateBalance();

        Log::info('Webhook: transaction created', [
            'user_id'     => $user->id,
            'bank'        => $parsed['bank'],
            'reference'   => $parsed['reference'],
            'amount'      => $parsed['amount'],
            'type'        => $parsed['type'],
            'subtype'     => $parsed['subtype'],
            'account'     => $account->name,
            'category'    => $categoryName,
            'transaction' => $transaction->id,
        ]);

        $response = [
            'status'    => 'created',
            'bank'      => $parsed['bank'],
            'reference' => $parsed['reference'],
            'amount'    => $parsed['amount'],
            'type'      => $parsed['type'],
            'subtype'   => $parsed['subtype'],
            'account'   => $account->name,
            'category'  => $categoryName,
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

        return Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'mpesa')
            ->where('is_active', true)
            ->first();
    }
}
