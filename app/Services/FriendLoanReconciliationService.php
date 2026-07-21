<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FriendLoanReconciliationService
{
    private const MATCH_WINDOW_DAYS = 30;

    /**
     * Reconcile a "Loan Recovery" against outstanding "Friend Loan Given"
     * transactions on the SAME account, within the match window, FIFO.
     * Cross-account givens are never touched — see note in the previous
     * explanation for why deleting across accounts would falsify balances.
     *
     * A given's true cost includes any linked transaction fee (e.g. a
     * mobile money withdrawal fee incurred when the loan was sent), so
     * matching is done against amount + fee, not amount alone — the friend
     * hasn't fully repaid the loan until both are covered.
     *
     * On a full match, the given (and its fee, via TransactionObserver's
     * cascade) is deleted outright. On a partial match, the fee is treated
     * as a one-time sunk cost and is consumed — deleted — the first time
     * this given is touched at all, even if the recovery only covers part
     * of the principal. This avoids re-summing the same fee into the total
     * on a later, separate recovery against the same still-open given.
     * One consequence: if a recovery is smaller than the fee itself, the
     * fee is fully absorbed and the principal doesn't reduce that round.
     *
     * Whatever remains of the recovery amount after every matching given
     * on that account has been fully consumed (principal + fee) is treated
     * as profit and booked as Interest.
     */
    public function reconcile(Transaction $recovery): void
    {
        DB::transaction(function () use ($recovery) {
            $recoveryDate = Carbon::parse($recovery->date);
            $windowStart  = $recoveryDate->copy()->subDays(self::MATCH_WINDOW_DAYS);
            $originalAmount = (float) $recovery->amount;

            $outstandingGivens = Transaction::where('user_id', $recovery->user_id)
                ->where('account_id', $recovery->account_id)
                ->whereHas('category', fn ($q) => $q->where('name', 'Friend Loan Given'))
                ->where('date', '>=', $windowStart)
                ->where('date', '<=', $recoveryDate)
                ->orderBy('date')->orderBy('id')
                ->get();

            if ($outstandingGivens->isEmpty()) {
                return;
            }

            $remaining    = $originalAmount;
            $totalMatched = 0.0;

            foreach ($outstandingGivens as $given) {
                if ($remaining <= 0) {
                    break;
                }

                $principalAmount = (float) $given->amount;
                $feeAmount       = (float) ($given->feeTransaction?->amount ?? 0);
                $givenTotal      = $principalAmount + $feeAmount;

                $matched       = min($remaining, $givenTotal);
                $remaining    -= $matched;
                $totalMatched += $matched;

                if ($matched >= $givenTotal - 0.0001) {
                    // Fully recovered (principal + fee). Deleting the given
                    // cascades to its fee transaction automatically via
                    // TransactionObserver.
                    $given->delete();
                } else {
                    // Partial recovery: consume the fee now so it isn't
                    // double-counted on a later reconciliation pass against
                    // this same given.
                    if ($feeAmount > 0) {
                        $given->feeTransaction?->delete();
                    }

                    $principalRecovered = max(0, $matched - $feeAmount);
                    $given->amount = round($principalAmount - $principalRecovered, 2);
                    $given->save();
                }
            }

            if ($totalMatched <= 0) {
                return;
            }

            // $remaining is whatever was left of the recovery after settling
            // every matched given's principal and fee -> genuine profit.
            if ($remaining > 0.0001) {
                $interestCategory = Category::firstOrCreate(
                    ['user_id' => $recovery->user_id, 'name' => 'Interest', 'parent_id' => null],
                    ['type' => 'income', 'icon' => '📈', 'is_active' => true]
                );

                Transaction::create([
                    'user_id'        => $recovery->user_id,
                    'account_id'     => $recovery->account_id,
                    'type'           => 'income',
                    'amount'         => round($remaining, 2),
                    'date'           => $recovery->date,
                    'period_date'    => $recovery->period_date,
                    'description'    => 'Interest from friend loan recovery',
                    'category_id'    => $interestCategory->id,
                    'payment_method' => 'Interest',
                ]);
            }

            $recovery->delete();
        });

        // One final, authoritative recompute, using a *fresh* Account row —
        // deliberately not $recovery->account, which may be a cached/stale instance.
        Account::find($recovery->account_id)?->updateBalance();
    }
}
