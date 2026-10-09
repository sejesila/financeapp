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
        // ── 1. Authenticate ───────────────────────────────────────────────
        // Header first, then the request BODY (not the query string, which
        // can end up in server logs). If no secret is configured on the
        // server, reject everything rather than letting null === null pass.
        $expected = (string) config('services.mpesa_webhook.secret');
        $provided = (string) ($request->header('X-Webhook-Secret')
            ?? $request->post('secret', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Webhook: invalid or unconfigured secret', ['ip' => $request->ip()]);
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
            if (config('app.debug')) {
                Log::info('Webhook: SMS not recognised, skipping', [
                    'user_id' => $user->id,
                    'sms'     => $smsBody,
                ]);
            }
            return response()->json([
                'status' => 'ignored',
                'reason' => 'SMS not recognised as a tracked transaction',
            ]);
        }

        // ── 4. Duplicate guard ────────────────────────────────────────────
        $alreadyExists = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('description', 'like', '%' . $parsed['reference'] . '%')
            ->exists();

        if (!$alreadyExists) {
            $alreadyExists = Transfer::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where(function ($q) use ($parsed) {
                    $q->where('mpesa_reference', $parsed['reference'])
                        ->orWhere('description', 'like', '%' . $parsed['reference'] . '%');
                })
                ->exists();
        }

        if ($alreadyExists) {
            return response()->json([
                'status'    => 'duplicate',
                'reference' => $parsed['reference'],
            ]);
        }

        // ── 5. Route transfers / record transaction ───────────────────────
        // The try/catch is a safety net for simultaneous webhook hits that
        // slip past the duplicate guard above due to a race condition.
        try {
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

            if ($parsed['subtype'] === 'pesalink_to_savings') {
                return $this->transfers->pesaLinkToSavings($user, $parsed);
            }

            if ($parsed['subtype'] === 'airtelcashback') {
                return $this->transactions->applyCashback($user, $parsed);
            }
            // Money received from a referrer is her float being remitted to
            // you, not side income.
            if ($parsed['subtype'] === 'receive_money' && !empty($parsed['sender'])) {
                $referrer = $this->transfers->findReferrerBySender($user, $parsed['sender']);

                if ($referrer) {
                    return $this->transfers->referrerFloatToMpesa($user, $parsed, $referrer);
                }
            }

            // ── 6. Record expense / income ────────────────────────────────
            return $this->transactions->record($user, $parsed);

        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json([
                'status'    => 'duplicate',
                'reference' => $parsed['reference'],
                'note'      => 'Caught by unique constraint',
            ]);
        }
    }
}
