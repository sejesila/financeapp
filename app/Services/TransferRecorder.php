<?php
// app/Services/TransferRecorder.php

namespace App\Services;

use App\Models\Account;
use App\Models\ClientFund;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferRecorder
{
    public function __construct(private CategoryResolver $categories) {}

    // ─────────────────────────────────────────────────────────────────────
    // Bank → Own Mpesa (self transfer)
    // ─────────────────────────────────────────────────────────────────────

    public function bankToMpesaSelf(User $user, array $parsed): JsonResponse
    {
        $bankAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'bank')
            ->where('is_active', true)
            ->first();

        $mpesaAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'mpesa')
            ->where('is_active', true)
            ->first();

        if (!$bankAccount || !$mpesaAccount) {
            Log::warning('Webhook: bank→mpesa self transfer — bank or mpesa account not found', [
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Bank or Mpesa account not found'], 404);
        }

        DB::transaction(function () use ($user, $parsed, $bankAccount, $mpesaAccount) {
            $transfer = Transfer::create([
                'user_id'         => $user->id,
                'from_account_id' => $bankAccount->id,
                'to_account_id'   => $mpesaAccount->id,
                'amount'          => $parsed['amount'],
                'date'            => now(),
                'description'     => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'mpesa_reference'  => $parsed['reference'],
            ]);

            $bankAccount->updateBalance();
            $mpesaAccount->updateBalance();

            app(BorrowedFundReturnService::class)->applyDepositAgainstBorrowed(
                userId: $user->id,
                accountId: $mpesaAccount->id,
                depositAmount: $parsed['amount'],
                date: now()->format('Y-m-d'),
                transfer: $transfer,
            );
        });

        Log::info('Webhook: bank → mpesa self transfer recorded', [
            'user_id'    => $user->id,
            'reference'  => $parsed['reference'],
            'amount'     => $parsed['amount'],
            'from'       => $bankAccount->name,
            'to'         => $mpesaAccount->name,
            'source_sms' => $parsed['bank'],
        ]);

        return response()->json([
            'status'  => 'created',
            'subtype' => 'bank_to_mpesa_self',
            'amount'  => $parsed['amount'],
            'from'    => $bankAccount->name,
            'to'      => $mpesaAccount->name,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bank → Own Airtel Money (self transfer)
    // ─────────────────────────────────────────────────────────────────────

    public function bankToAirtelSelf(User $user, array $parsed): JsonResponse
    {
        $bankAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'bank')
            ->where('is_active', true)
            ->first();

        $airtelAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'airtel_money')
            ->where('is_active', true)
            ->first();

        if (!$bankAccount || !$airtelAccount) {
            Log::warning('Webhook: bank→airtel self transfer — bank or airtel account not found', [
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Bank or Airtel Money account not found'], 404);
        }

        DB::transaction(function () use ($user, $parsed, $bankAccount, $airtelAccount) {
            $transfer = Transfer::create([
                'user_id'         => $user->id,
                'from_account_id' => $bankAccount->id,
                'to_account_id'   => $airtelAccount->id,
                'amount'          => $parsed['amount'],
                'date'            => now(),
                'description'     => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'mpesa_reference'  => $parsed['reference'],
            ]);

            $bankAccount->updateBalance();
            $airtelAccount->updateBalance();

            app(BorrowedFundReturnService::class)->applyDepositAgainstBorrowed(
                userId: $user->id,
                accountId: $airtelAccount->id,
                depositAmount: $parsed['amount'],
                date: now()->format('Y-m-d'),
                transfer: $transfer,
            );
        });

        Log::info('Webhook: bank → airtel self transfer recorded', [
            'user_id'   => $user->id,
            'reference' => $parsed['reference'],
            'amount'    => $parsed['amount'],
            'from'      => $bankAccount->name,
            'to'        => $airtelAccount->name,
        ]);

        return response()->json([
            'status'  => 'created',
            'subtype' => 'bank_to_airtel_self',
            'amount'  => $parsed['amount'],
            'from'    => $bankAccount->name,
            'to'      => $airtelAccount->name,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ATM Withdrawal — Bank → Cash + fixed ATM fee (KES 33 + 15% excise)
    // ─────────────────────────────────────────────────────────────────────

    public function atmWithdrawal(User $user, array $parsed): JsonResponse
    {
        $bankAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'bank')
            ->where('is_active', true)
            ->first();

        $cashAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'cash')
            ->where('is_active', true)
            ->first();

        if (!$bankAccount || !$cashAccount) {
            Log::warning('Webhook: ATM withdrawal — bank or cash account not found', [
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Bank or cash account not found'], 404);
        }

        $atmFee = round(33 * 1.15, 2); // KES 37.95 (base KES 33 + 15% excise duty)

        DB::transaction(function () use ($user, $parsed, $bankAccount, $cashAccount, $atmFee) {
            $transfer = Transfer::create([
                'user_id'         => $user->id,
                'from_account_id' => $bankAccount->id,
                'to_account_id'   => $cashAccount->id,
                'amount'          => $parsed['amount'],
                'date'            => now(),
                'description'     => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'mpesa_reference'  => $parsed['reference'],
            ]);

            $feeCategory = $this->categories->findOrCreate($user, 'Transaction Fees', 'expense');

            Transaction::withoutGlobalScopes()->create([
                'user_id'            => $user->id,
                'account_id'         => $bankAccount->id,
                'category_id'        => $feeCategory->id,
                'amount'             => $atmFee,
                'date'               => $parsed['date'],
                'description'        => 'ATM fee (incl. 15% excise duty) for ' . $parsed['reference'],
                'payment_method'     => 'I&M Bank',
                'is_transaction_fee' => true,
            ]);

            $bankAccount->updateBalance();
            $cashAccount->updateBalance();

            app(BorrowedFundReturnService::class)->applyDepositAgainstBorrowed(
                userId: $user->id,
                accountId: $cashAccount->id,
                depositAmount: $parsed['amount'],
                date: now()->format('Y-m-d'),
                transfer: $transfer,
            );
        });

        Log::info('Webhook: ATM withdrawal → bank→cash transfer', [
            'user_id'   => $user->id,
            'reference' => $parsed['reference'],
            'amount'    => $parsed['amount'],
            'fee'       => $atmFee,
            'from'      => $bankAccount->name,
            'to'        => $cashAccount->name,
        ]);

        return response()->json([
            'status'  => 'created',
            'subtype' => 'atm_withdrawal',
            'amount'  => $parsed['amount'],
            'fee'     => $atmFee,
            'from'    => $bankAccount->name,
            'to'      => $cashAccount->name,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PesaLink — Bank → Savings account (e.g. Etica at Equity Bank)
    // Records the transfer + PesaLink fee (incl. 15% excise duty) as an
    // expense on the bank account.
    // ─────────────────────────────────────────────────────────────────────

    public function pesaLinkToSavings(User $user, array $parsed): JsonResponse
    {
        $bankAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'bank')
            ->where('is_active', true)
            ->first();

        // Find savings account by name — fuzzy match on "etica"
        $savingsAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'savings')
            ->where('is_active', true)
            ->whereRaw('LOWER(name) LIKE ?', ['%etica%'])
            ->first();

        if (!$bankAccount) {
            Log::warning('Webhook: PesaLink→savings — bank account not found', [
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Bank account not found'], 404);
        }

        if (!$savingsAccount) {
            // Fallback: record as a bank expense if savings account not configured
            Log::warning('Webhook: PesaLink→savings — Etica savings account not found, recording as expense', [
                'user_id' => $user->id,
            ]);

            $category = $this->categories->findOrCreate($user, 'Savings', 'expense');

            DB::transaction(function () use ($user, $parsed, $bankAccount, $category) {
                Transaction::withoutGlobalScopes()->create([
                    'user_id'        => $user->id,
                    'account_id'     => $bankAccount->id,
                    'category_id'    => $category->id,
                    'amount'         => $parsed['amount'],
                    'date'           => $parsed['date'],
                    'description'    => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                    'payment_method' => 'PesaLink',
                ]);

                if ($parsed['fee'] > 0) {
                    $feeCategory = $this->categories->findOrCreate($user, 'Transaction Fees', 'expense');
                    Transaction::withoutGlobalScopes()->create([
                        'user_id'            => $user->id,
                        'account_id'         => $bankAccount->id,
                        'category_id'        => $feeCategory->id,
                        'amount'             => $parsed['fee'],
                        'date'               => $parsed['date'],
                        'description'        => 'PesaLink fee (incl. 15% excise duty) for ' . $parsed['reference'],
                        'payment_method'     => 'I&M Bank',
                        'is_transaction_fee' => true,
                    ]);
                }

                $bankAccount->updateBalance();
            });

            return response()->json([
                'status'  => 'created',
                'subtype' => 'pesalink_fallback',
                'amount'  => $parsed['amount'],
                'fee'     => $parsed['fee'],
                'note'    => 'Etica savings account not found — recorded as expense',
            ], 201);
        }

        // ── Happy path: Transfer + fee transaction ────────────────────────
        DB::transaction(function () use ($user, $parsed, $bankAccount, $savingsAccount) {
            // PesaLink SMS carries the real transaction timestamp (date +
            // time of day), so it doubles as both the cutoff-check anchor
            // and the next-business-day fallback anchor — unlike the manual
            // transfer form, there's no backdating concern here.
            $transactionTime = Carbon::parse($parsed['date']);

            $valueDate = KenyanBusinessDays::resolveEticaValueDate(
                $transactionTime,
                $transactionTime,
            );

            $transfer = Transfer::create([
                'user_id'         => $user->id,
                'from_account_id' => $bankAccount->id,
                'to_account_id'   => $savingsAccount->id,
                'amount'          => $parsed['amount'],
                'date'            => $parsed['date'],
                'value_date'      => $valueDate,
                'description'     => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'mpesa_reference'  => $parsed['reference'],
            ]);
            $this->attemptClientFundAutoMatch($transfer, $user, $bankAccount, $parsed['amount']);

            if ($parsed['fee'] > 0) {
                $feeCategory = $this->categories->findOrCreate($user, 'Transaction Fees', 'expense');
                Transaction::withoutGlobalScopes()->create([
                    'user_id'            => $user->id,
                    'account_id'         => $bankAccount->id,
                    'category_id'        => $feeCategory->id,
                    'amount'             => $parsed['fee'],
                    'date'               => $parsed['date'],
                    'description'        => 'PesaLink fee (incl. 15% excise duty) for ' . $parsed['reference'],
                    'payment_method'     => 'I&M Bank',
                    'is_transaction_fee' => true,
                ]);
            }

            $bankAccount->updateBalance();
            $savingsAccount->updateBalance();

            // Skip if this deposit was itself auto-matched as new client
            // money in (attemptClientFundAutoMatch() above may have just
            // set is_client_fund) — that's not a repayment of borrowing.
            if (! $transfer->fresh()->is_client_fund) {
                app(BorrowedFundReturnService::class)->applyDepositAgainstBorrowed(
                    userId: $user->id,
                    accountId: $savingsAccount->id,
                    depositAmount: $parsed['amount'],
                    date: $parsed['date'],
                    transfer: $transfer,
                );
            }
        });

        Log::info('Webhook: PesaLink → savings transfer recorded', [
            'user_id'   => $user->id,
            'reference' => $parsed['reference'],
            'amount'    => $parsed['amount'],
            'fee'       => $parsed['fee'],
            'from'      => $bankAccount->name,
            'to'        => $savingsAccount->name,
        ]);

        return response()->json([
            'status'  => 'created',
            'subtype' => 'pesalink_to_savings',
            'amount'  => $parsed['amount'],
            'fee'     => $parsed['fee'],
            'from'    => $bankAccount->name,
            'to'      => $savingsAccount->name,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Outgoing account transfer — e.g. Mpesa → Airtel Money, Mpesa → Sanlam
    // ─────────────────────────────────────────────────────────────────────

    public function outgoing(User $user, array $parsed): JsonResponse
    {
        $mpesaAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'mpesa')
            ->where('is_active', true)
            ->first();

        if (!$mpesaAccount) {
            return response()->json(['error' => 'Mpesa account not found'], 404);
        }

        $hint = $parsed['to_account_hint'] ?? '';

        $destinationAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($hint) . '%'])
            ->first();

        if (!$destinationAccount) {
            Log::info('Webhook: outgoing transfer — destination not found, recording as expense', [
                'user_id' => $user->id,
                'hint'    => $hint,
            ]);

            $category = $this->categories->findOrCreate($user, 'Other Expenses', 'expense');
            $transaction = Transaction::withoutGlobalScopes()->create([
                'user_id'        => $user->id,
                'account_id'     => $mpesaAccount->id,
                'category_id'    => $category->id,
                'amount'         => $parsed['amount'],
                'date'           => $parsed['date'],
                // no value_date — this is a transaction row, not a transfer to savings
                'description'    => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'payment_method' => 'Mpesa',
            ]);

            if (!empty($parsed['fee']) && $parsed['fee'] > 0) {
                $feeCategory = $this->categories->findOrCreate($user, 'Transaction Fees', 'expense');
                $feeTransaction = Transaction::withoutGlobalScopes()->create([
                    'user_id'                    => $user->id,
                    'account_id'                 => $mpesaAccount->id,
                    'category_id'                => $feeCategory->id,
                    'amount'                     => $parsed['fee'],
                    'date'                       => $parsed['date'],
                    'description'                => 'Transaction fee for ' . $parsed['reference'],
                    'payment_method'             => 'Mpesa',
                    'is_transaction_fee'         => true,
                    'related_fee_transaction_id' => $transaction->id,
                ]);
                $transaction->update(['related_fee_transaction_id' => $feeTransaction->id]);
            }

            $mpesaAccount->updateBalance();

            return response()->json([
                'status'    => 'created',
                'subtype'   => 'account_transfer_fallback',
                'reference' => $parsed['reference'],
                'amount'    => $parsed['amount'],
                'account'   => $mpesaAccount->name,
                'note'      => "Destination account '{$hint}' not found — recorded as expense",
            ], 201);
        }

        DB::transaction(function () use ($user, $parsed, $mpesaAccount, $destinationAccount) {
            $isInterestGated = $destinationAccount->type === 'savings'
                && stripos($destinationAccount->name, 'etica') !== false;

            $valueDate = null;

            if ($isInterestGated) {
                // Mirrors pesaLinkToSavings(): the M-Pesa confirmation SMS
                // timestamp is the real transaction time, so it's used both
                // as the cutoff-check anchor and the next-business-day
                // fallback anchor.
                $transactionTime = Carbon::parse($parsed['date']);

                $valueDate = KenyanBusinessDays::resolveEticaValueDate(
                    $transactionTime,
                    $transactionTime,
                );
            }

            $transfer = Transfer::create([
                'user_id'         => $user->id,
                'from_account_id' => $mpesaAccount->id,
                'to_account_id'   => $destinationAccount->id,
                'amount'          => $parsed['amount'],
                'date'            => $parsed['date'],
                'value_date'      => $valueDate,
                'description'     => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'mpesa_reference' => $parsed['reference'],
            ]);
            $this->attemptClientFundAutoMatch($transfer, $user, $mpesaAccount, $parsed['amount']);

            if (!empty($parsed['fee']) && $parsed['fee'] > 0) {
                $feeCategory = $this->categories->findOrCreate($user, 'Transaction Fees', 'expense');
                Transaction::withoutGlobalScopes()->create([
                    'user_id'            => $user->id,
                    'account_id'         => $mpesaAccount->id,
                    'category_id'        => $feeCategory->id,
                    'amount'             => $parsed['fee'],
                    'date'               => $parsed['date'],
                    'description'        => 'Transaction fee for ' . $parsed['reference'],
                    'payment_method'     => 'Mpesa',
                    'is_transaction_fee' => true,
                ]);
            }

            $mpesaAccount->updateBalance();
            $destinationAccount->updateBalance();

            // Skip if this deposit was itself auto-matched as new client
            // money in — that's not a repayment of borrowing.
            if (! $transfer->fresh()->is_client_fund) {
                app(BorrowedFundReturnService::class)->applyDepositAgainstBorrowed(
                    userId: $user->id,
                    accountId: $destinationAccount->id,
                    depositAmount: $parsed['amount'],
                    date: $parsed['date'],
                    transfer: $transfer,
                );
            }
        });

        Log::info('Webhook: outgoing account transfer', [
            'user_id'   => $user->id,
            'reference' => $parsed['reference'],
            'amount'    => $parsed['amount'],
            'hint'      => $parsed['to_account_hint'],
            'from'      => $mpesaAccount->name,
            'to'        => $destinationAccount->name,
        ]);

        return response()->json([
            'status'  => 'created',
            'subtype' => 'account_transfer',
            'amount'  => $parsed['amount'],
            'fee'     => $parsed['fee'],
            'from'    => $mpesaAccount->name,
            'to'      => $destinationAccount->name,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Incoming account transfer — e.g. Airtel Money → Mpesa
    // ─────────────────────────────────────────────────────────────────────

    public function incoming(User $user, array $parsed): JsonResponse
    {
        $mpesaAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'mpesa')
            ->where('is_active', true)
            ->first();

        if (!$mpesaAccount) {
            return response()->json(['error' => 'Mpesa account not found'], 404);
        }

        $hint          = $parsed['from_account_hint'] ?? '';
        $sourceAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($hint) . '%'])
            ->first();

        if (!$sourceAccount) {
            Log::info('Webhook: incoming transfer — source account not found, recording as income', [
                'user_id' => $user->id,
                'hint'    => $hint,
            ]);

            $transaction = Transaction::withoutGlobalScopes()->create([
                'user_id'        => $user->id,
                'account_id'     => $mpesaAccount->id,
                'category_id'    => $this->categories->findOrCreate($user, 'Side Income', 'income')->id,
                'amount'         => $parsed['amount'],
                'date'           => $parsed['date'],
                'description'    => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'payment_method' => 'Mpesa',
            ]);

            $mpesaAccount->updateBalance();

            app(BorrowedFundReturnService::class)->applyDepositAgainstBorrowed(
                userId: $user->id,
                accountId: $mpesaAccount->id,
                depositAmount: $parsed['amount'],
                date: $parsed['date'],
                depositTransaction: $transaction,
            );

            return response()->json([
                'status'  => 'created',
                'subtype' => 'account_transfer_fallback',
                'amount'  => $parsed['amount'],
                'account' => $mpesaAccount->name,
                'note'    => "Source account '{$hint}' not found — recorded as income",
            ], 201);
        }

        DB::transaction(function () use ($user, $parsed, $sourceAccount, $mpesaAccount) {
            $transfer = Transfer::create([
                'user_id'         => $user->id,
                'from_account_id' => $sourceAccount->id,
                'to_account_id'   => $mpesaAccount->id,
                'amount'          => $parsed['amount'],
                'date'            => now(),
                'description'     => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'mpesa_reference'  => $parsed['reference'],
            ]);

            $sourceAccount->updateBalance();
            $mpesaAccount->updateBalance();

            app(BorrowedFundReturnService::class)->applyDepositAgainstBorrowed(
                userId: $user->id,
                accountId: $mpesaAccount->id,
                depositAmount: $parsed['amount'],
                date: now()->format('Y-m-d'),
                transfer: $transfer,
            );
        });

        Log::info('Webhook: incoming account transfer', [
            'user_id'   => $user->id,
            'reference' => $parsed['reference'],
            'amount'    => $parsed['amount'],
            'from'      => $sourceAccount->name,
            'to'        => $mpesaAccount->name,
        ]);

        return response()->json([
            'status'  => 'created',
            'subtype' => 'account_transfer',
            'type'    => 'transfer',
            'amount'  => $parsed['amount'],
            'from'    => $sourceAccount->name,
            'to'      => $mpesaAccount->name,
        ], 201);
    }
    private function attemptClientFundAutoMatch(Transfer $transfer, User $user, Account $fromAccount, float $amount): void
    {
        $candidateFund = ClientFund::where('user_id', $user->id)
            ->where('account_id', $fromAccount->id)
            ->where('balance', $amount)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($candidateFund) {
            $transfer->update([
                'client_fund_id' => $candidateFund->id,
                'is_client_fund' => true,
            ]);

            Log::info('Webhook: auto-matched transfer to client fund', [
                'transfer_id'    => $transfer->id,
                'client_fund_id' => $candidateFund->id,
                'amount'         => $amount,
            ]);
            return;
        }

        $hasOutstanding = ClientFund::where('user_id', $user->id)
            ->where('account_id', $fromAccount->id)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($hasOutstanding) {
            $transfer->update(['needs_reconciliation' => true]);

            Log::warning('Webhook: transfer moved money out of an account with outstanding client funds — could not auto-match, needs manual reconciliation', [
                'transfer_id'      => $transfer->id,
                'from_account_id'  => $fromAccount->id,
                'amount'           => $amount,
            ]);
        }
    }
}
