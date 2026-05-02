<?php
// app/Http/Controllers/MpesaSmsController.php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\MpesaSmsParser;
use App\Services\TransactionRecorder;
use App\Services\TransferRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaSmsController extends Controller
{
    public function __construct(
        private TransferRecorder    $transfers,
        private TransactionRecorder $transactions,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        Log::info('triggered');

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

        if (!$alreadyExists && in_array($parsed['subtype'], ['atm_withdrawal', 'account_transfer', 'bank_to_mpesa_self', 'bank_to_airtel_self'])) {
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

        // ── 5. Route transfers ────────────────────────────────────────────
        if ($parsed['subtype'] === 'atm_withdrawal' && $parsed['bank'] === 'im_bank') {
            return $this->transfers->atmWithdrawal($user, $parsed);
        }

        if ($parsed['subtype'] === 'bank_to_mpesa_self') {
            return $this->transfers->bankToMpesaSelf($user, $parsed);
        }

        if ($parsed['subtype'] === 'bank_to_airtel_self') {
            return $this->transfers->bankToAirtelSelf($user, $parsed);
        }

        if ($parsed['subtype'] === 'account_transfer' && $parsed['type'] === 'transfer' && isset($parsed['to_account_hint'])) {
            return $this->transfers->outgoing($user, $parsed);
        }

        if ($parsed['subtype'] === 'account_transfer' && $parsed['type'] === 'transfer' && isset($parsed['from_account_hint'])) {
            return $this->transfers->incoming($user, $parsed);
        }

        // ── 6. Record expense / income ────────────────────────────────────
        return $this->transactions->record($user, $parsed);
    }
}
