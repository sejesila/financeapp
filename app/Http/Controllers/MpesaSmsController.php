<?php
// app/Http/Controllers/MpesaSmsController.php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\MpesaSmsParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaSmsController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // ── 1. Authenticate ───────────────────────────────────────────────
        $secret = $request->header('X-Webhook-Secret')
            ?? $request->input('secret');

        if ($secret !== config('services.mpesa_webhook.secret')) {
            Log::warning('Webhook: invalid secret', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ── 2. Get the user ───────────────────────────────────────────────
        $user = User::find($request->input('user_id'));
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // ── 3. Parse the SMS ──────────────────────────────────────────────
        $smsBody = $request->input('sms');
        if (!$smsBody) {
            return response()->json(['error' => 'No SMS body provided'], 422);
        }

        $parsed = MpesaSmsParser::parse($smsBody);

        if (!$parsed) {
            Log::info('Webhook: SMS not recognised, skipping', [
                'user_id' => $user->id,
                'sms'     => $smsBody,
            ]);
            return response()->json([
                'status' => 'ignored',
                'reason' => 'SMS not recognised as a tracked transaction',
            ]);
        }

        // ── 4. Prevent duplicates ─────────────────────────────────────────
        $alreadyExists = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('description', 'like', '%' . $parsed['reference'] . '%')
            ->exists();

        // Also check Transfer table for subtypes stored as transfers
        if (!$alreadyExists && in_array($parsed['subtype'], ['atm_withdrawal', 'account_transfer'])) {
            $alreadyExists = Transfer::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('description', 'like', '%' . $parsed['reference'] . '%')
                ->exists();
        }

        if ($alreadyExists) {
            return response()->json([
                'status'    => 'duplicate',
                'reference' => $parsed['reference'],
            ]);
        }

        // ── 5a. Route ATM withdrawal as bank → cash transfer ──────────────
        if ($parsed['subtype'] === 'atm_withdrawal' && $parsed['bank'] === 'im_bank') {
            return $this->handleAtmWithdrawal($user, $parsed);
        }

        // ── 5b. Route outgoing account transfer (e.g. Mpesa → Airtel) ─────
        if ($parsed['subtype'] === 'account_transfer' && $parsed['type'] === 'transfer' && isset($parsed['to_account_hint'])) {
            return $this->handleOutgoingAccountTransfer($user, $parsed);
        }

        // ── 5c. Route incoming account transfer (e.g. Airtel → Mpesa) ─────
        if ($parsed['subtype'] === 'account_transfer' && $parsed['type'] === 'transfer' && isset($parsed['from_account_hint'])) {
            return $this->handleIncomingAccountTransfer($user, $parsed);
        }

        // ── 6. Find the right account ─────────────────────────────────────
        $account = $this->resolveAccount($user, $parsed);

        if (!$account) {
            Log::warning('Webhook: no matching account found', [
                'user_id' => $user->id,
                'bank'    => $parsed['bank'],
                'subtype' => $parsed['subtype'],
            ]);
            return response()->json(['error' => 'No matching account found'], 404);
        }

        // ── 7. Resolve category ───────────────────────────────────────────
        $categoryName = $this->resolveCategoryName($parsed);
        $category     = $this->findOrCreateCategory($user, $categoryName, $parsed['type']);

        // ── 8. Create the main transaction ────────────────────────────────
        $transaction = Transaction::withoutGlobalScopes()->create([
            'user_id'        => $user->id,
            'account_id'     => $account->id,
            'category_id'    => $category->id,
            'amount'         => $parsed['amount'],
            'date'           => $parsed['date'],
            'description'    => $parsed['description'] . ' [' . $parsed['reference'] . ']',
            'payment_method' => $parsed['bank'] === 'im_bank' ? 'I&M Bank' : 'Mpesa',
            'is_reversal'    => false,
            'is_split'       => false,
        ]);

        // ── 9. Create fee transaction if applicable ───────────────────────
        if (!empty($parsed['fee']) && $parsed['fee'] > 0) {
            $feeCategory = $this->findOrCreateCategory($user, 'Transaction Fees', 'expense');

            $feeTransaction = Transaction::withoutGlobalScopes()->create([
                'user_id'                    => $user->id,
                'account_id'                 => $account->id,
                'category_id'                => $feeCategory->id,
                'amount'                     => $parsed['fee'],
                'date'                       => $parsed['date'],
                'description'                => 'Transaction fee for ' . $parsed['reference'],
                'payment_method'             => $parsed['bank'] === 'im_bank' ? 'I&M Bank' : 'Mpesa',
                'is_transaction_fee'         => true,
                'related_fee_transaction_id' => $transaction->id,
                'is_reversal'                => false,
                'is_split'                   => false,
            ]);

            $transaction->update(['related_fee_transaction_id' => $feeTransaction->id]);
        }

        // ── 10. Update account balance ────────────────────────────────────
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

        $responseData = [
            'status'    => 'created',
            'bank'      => $parsed['bank'],
            'reference' => $parsed['reference'],
            'amount'    => $parsed['amount'],
            'type'      => $parsed['type'],
            'subtype'   => $parsed['subtype'],
            'account'   => $account->name,
            'category'  => $categoryName,
        ];

            // Add fee to response if present
        if (isset($parsed['fee']) && $parsed['fee'] > 0) {
            $responseData['fee'] = $parsed['fee'];
        }

        return response()->json($responseData, 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ATM Withdrawal — Bank → Cash transfer + ATM fee (KES 33 + 15% excise)
    // ─────────────────────────────────────────────────────────────────────────

    private function handleAtmWithdrawal(User $user, array $parsed): JsonResponse
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
            Log::warning('Webhook: ATM withdrawal — bank or cash account not found', ['user_id' => $user->id]);
            return response()->json(['error' => 'Bank or cash account not found'], 404);
        }

        $atmFee = round(33 + (33 * 0.15), 2); // KES 37.95

        DB::transaction(function () use ($user, $parsed, $bankAccount, $cashAccount, $atmFee) {
            Transfer::create([
                'user_id'         => $user->id,
                'from_account_id' => $bankAccount->id,
                'to_account_id'   => $cashAccount->id,
                'amount'          => $parsed['amount'],
                'date'            => $parsed['date'],
                'description'     => $parsed['description'] . ' [' . $parsed['reference'] . ']',
            ]);

            $feeCategory = $this->findOrCreateCategory($user, 'Transaction Fees', 'expense');

            Transaction::withoutGlobalScopes()->create([
                'user_id'            => $user->id,
                'account_id'         => $bankAccount->id,
                'category_id'        => $feeCategory->id,
                'amount'             => $atmFee,
                'date'               => $parsed['date'],
                'description'        => 'ATM fee for ' . $parsed['reference'],
                'payment_method'     => 'I&M Bank',
                'is_transaction_fee' => true,
                'is_reversal'        => false,
                'is_split'           => false,
            ]);

            $bankAccount->updateBalance();
            $cashAccount->updateBalance();
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

    // ─────────────────────────────────────────────────────────────────────────
    // Outgoing account transfer — e.g. Mpesa → Airtel Money, Mpesa → Sanlam MMF
    // ─────────────────────────────────────────────────────────────────────────

    private function handleOutgoingAccountTransfer(User $user, array $parsed): JsonResponse
    {
        $mpesaAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'mpesa')
            ->where('is_active', true)
            ->first();

        if (!$mpesaAccount) {
            return response()->json(['error' => 'Mpesa account not found'], 404);
        }

        $hint               = $parsed['to_account_hint'] ?? '';
        $destinationAccount = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($hint) . '%'])
            ->first();

        // ── Fallback: destination not found — record as regular expense ───
        if (!$destinationAccount) {
            Log::info('Webhook: outgoing transfer — destination not found, recording as expense', [
                'user_id' => $user->id,
                'hint'    => $hint,
            ]);

            $category    = $this->findOrCreateCategory($user, 'Other Expenses', 'expense');
            $transaction = Transaction::withoutGlobalScopes()->create([
                'user_id'        => $user->id,
                'account_id'     => $mpesaAccount->id,
                'category_id'    => $category->id,
                'amount'         => $parsed['amount'],
                'date'           => $parsed['date'],
                'description'    => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'payment_method' => 'Mpesa',
                'is_reversal'    => false,
                'is_split'       => false,
            ]);

            if (!empty($parsed['fee']) && $parsed['fee'] > 0) {
                $feeCategory    = $this->findOrCreateCategory($user, 'Transaction Fees', 'expense');
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
                    'is_reversal'                => false,
                    'is_split'                   => false,
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

        // ── Happy path: create transfer ───────────────────────────────────
        DB::transaction(function () use ($user, $parsed, $mpesaAccount, $destinationAccount) {
            Transfer::create([
                'user_id'         => $user->id,
                'from_account_id' => $mpesaAccount->id,
                'to_account_id'   => $destinationAccount->id,
                'amount'          => $parsed['amount'],
                'date'            => $parsed['date'],
                'description'     => $parsed['description'] . ' [' . $parsed['reference'] . ']',
            ]);

            if (!empty($parsed['fee']) && $parsed['fee'] > 0) {
                $feeCategory = $this->findOrCreateCategory($user, 'Transaction Fees', 'expense');
                Transaction::withoutGlobalScopes()->create([
                    'user_id'            => $user->id,
                    'account_id'         => $mpesaAccount->id,
                    'category_id'        => $feeCategory->id,
                    'amount'             => $parsed['fee'],
                    'date'               => $parsed['date'],
                    'description'        => 'Transaction fee for ' . $parsed['reference'],
                    'payment_method'     => 'Mpesa',
                    'is_transaction_fee' => true,
                    'is_reversal'        => false,
                    'is_split'           => false,
                ]);
            }

            $mpesaAccount->updateBalance();
            $destinationAccount->updateBalance();
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

    // ─────────────────────────────────────────────────────────────────────────
    // Incoming account transfer — e.g. Airtel Money → Mpesa
    // ─────────────────────────────────────────────────────────────────────────

    private function handleIncomingAccountTransfer(User $user, array $parsed): JsonResponse
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

        // ── Fallback: source not found — record as income ─────────────────
        if (!$sourceAccount) {
            Log::info('Webhook: incoming transfer — source account not found, recording as income', [
                'user_id' => $user->id,
                'hint'    => $hint,
            ]);

            $category = $this->findOrCreateCategory($user, 'Side Income', 'income');
            Transaction::withoutGlobalScopes()->create([
                'user_id'        => $user->id,
                'account_id'     => $mpesaAccount->id,
                'category_id'    => $category->id,
                'amount'         => $parsed['amount'],
                'date'           => $parsed['date'],
                'description'    => $parsed['description'] . ' [' . $parsed['reference'] . ']',
                'payment_method' => 'Mpesa',
                'is_reversal'    => false,
                'is_split'       => false,
            ]);

            $mpesaAccount->updateBalance();

            return response()->json([
                'status'  => 'created',
                'subtype' => 'account_transfer_fallback',
                'amount'  => $parsed['amount'],
                'account' => $mpesaAccount->name,
                'note'    => "Source account '{$hint}' not found — recorded as income",
            ], 201);
        }

        // ── Happy path: create transfer (source → mpesa) ──────────────────
        DB::transaction(function () use ($user, $parsed, $sourceAccount, $mpesaAccount) {
            Transfer::create([
                'user_id'         => $user->id,
                'from_account_id' => $sourceAccount->id,
                'to_account_id'   => $mpesaAccount->id,
                'amount'          => $parsed['amount'],
                'date'            => $parsed['date'],
                'description'     => $parsed['description'] . ' [' . $parsed['reference'] . ']',
            ]);

            $sourceAccount->updateBalance();
            $mpesaAccount->updateBalance();
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

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

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

    private function findOrCreateCategory(User $user, string $name, string $type): Category
    {
        $expectedType = $type === 'income' ? 'income' : 'expense';

        // For "Side Income", specifically look for the child under "Income" parent
        if ($name === 'Side Income' && $expectedType === 'income') {
            $incomeParent = Category::where('user_id', $user->id)
                ->where('name', 'Income')
                ->where('type', 'income')
                ->whereNull('parent_id')
                ->first();

            if ($incomeParent) {
                $childCategory = Category::where('user_id', $user->id)
                    ->where('name', 'Side Income')
                    ->where('type', 'income')
                    ->where('parent_id', $incomeParent->id)
                    ->first();

                if ($childCategory) {
                    return $childCategory;
                }
            }
        }

        // First, try to find a child category (has parent_id)
        $childCategory = Category::where('user_id', $user->id)
            ->where('name', $name)
            ->where('type', $expectedType)
            ->whereNotNull('parent_id')
            ->first();

        if ($childCategory) {
            return $childCategory;
        }

        // Second, try to find a root-level category
        $rootCategory = Category::where('user_id', $user->id)
            ->where('name', $name)
            ->where('type', $expectedType)
            ->whereNull('parent_id')
            ->first();

        if ($rootCategory) {
            return $rootCategory;
        }

        // Finally, create a new root-level category
        return Category::create([
            'user_id'   => $user->id,
            'name'      => $name,
            'type'      => $expectedType,
            'is_active' => true,
            'parent_id' => null,
        ]);
    }

    private function resolveCategoryName(array $parsed): string
    {
        if ($parsed['bank'] === 'im_bank') {
            return match($parsed['subtype']) {
                'bank_to_mpesa' => 'Other Expenses',
                'bank_received' => 'Side Income',
                default         => 'Other Expenses',
            };
        }

        if ($parsed['subtype'] === 'paybill') {
            return $this->resolvePaybillCategory(
                $parsed['recipient']       ?? '',
                $parsed['paybill_account'] ?? ''
            );
        }

        if (in_array($parsed['subtype'], ['till', 'buy_goods'])) {
            return $this->resolveTillCategory($parsed['recipient'] ?? '');
        }

        return match($parsed['subtype']) {
            'receive_money' => 'Side Income',
            'send_money'    => 'Other Expenses',
            'withdrawal'    => 'Other Expenses',
            'airtime'       => 'Airtime & Data',
            default         => 'Other Expenses',
        };
    }

    private function resolvePaybillCategory(string $recipient, string $accountNo = ''): string
    {
        $r = strtolower($recipient);

        if (str_contains($r, 'kplc')) {
            return 'Electricity';
        }
        if (str_contains($r, 'kcb') && $accountNo === '6330444') {
            return 'Rent';
        }
        if (str_contains($r, 'safaricom') || str_contains($r, 'zuku')
            || str_contains($r, 'faiba') || str_contains($r, 'airtel')) {
            return 'Internet and Communication';
        }
        if (str_contains($r, 'co-operative') && $accountNo === '1040616#0889') {
            return 'School Fees';
        }

        return 'Other Expenses';
    }

    private function resolveTillCategory(string $recipient): string
    {
        $r = strtolower($recipient);

        if (str_contains($r, 'naivas') || str_contains($r, 'quick mart')
            || str_contains($r, 'vuno') || str_contains($r, 'cleanshelf')
            || str_contains($r, 'eastmatt')) {
            return 'Groceries';
        }


        return 'Other Expenses';
    }
}
