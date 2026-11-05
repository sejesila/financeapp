<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Referrer;
use App\Models\ReferrerFloatReconciliation;
use App\Models\Transfer;

class ReferrerFloatAllocator
{
    /** Set true to skip auto-allocation (e.g. when a caller records its own rows). */
    public static bool $suspended = false;

    public function allocate(Transfer $transfer): void
    {
        if (self::$suspended) {
            return;
        }

        $from = Account::withoutGlobalScopes()->find($transfer->from_account_id);

        if (!$from || $from->type !== 'referrer_float' || !$from->referrer_id) {
            return;
        }

        // Idempotent: never allocate the same transfer twice.
        if (ReferrerFloatReconciliation::withoutGlobalScopes()->where('transfer_id', $transfer->id)->exists()) {
            return;
        }

        $referrer = Referrer::withoutGlobalScopes()->find($from->referrer_id);

        if (!$referrer) {
            return;
        }

        $left = round((float) $transfer->amount, 2);

        // 1. Interest first: everything held in the float, including her commission share.
        $interest = $referrer->pendingFloatInterestByLoan()->sortKeys();

        foreach ($interest as $loanId => $pending) {
            if ($left <= 0.005) {
                break;
            }

            $take = round(min($left, (float) $pending), 2);

            if ($take > 0) {
                $this->row($referrer, $transfer, $loanId, $take, 'interest');
                $left = round($left - $take, 2);
            }
        }

        // 2. Whatever is left is principal. Any part that can't be attached to a
        //    loan stays unallocated, which the statement already shows as principal.
        if ($left > 0.005) {
            foreach ($referrer->pendingFloatPrincipalByLoan()->sortKeys() as $loanId => $pending) {
                if ($left <= 0.005) {
                    break;
                }

                $take = round(min($left, (float) $pending), 2);

                if ($take > 0) {
                    $this->row($referrer, $transfer, $loanId, $take, 'principal');
                    $left = round($left - $take, 2);
                }
            }
        }
    }

    private function row(Referrer $referrer, Transfer $transfer, int $loanId, float $amount, string $component): void
    {
        ReferrerFloatReconciliation::withoutGlobalScopes()->create([
            'user_id'       => $referrer->user_id,
            'loan_given_id' => $loanId,
            'transfer_id'   => $transfer->id,
            'amount'        => $amount,
            'component'     => $component,
        ]);
    }
}
