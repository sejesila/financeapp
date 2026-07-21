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
     * Whatever remains of the recovery amount after every matching given
     * on that account has been fully consumed is treated as profit and
     * booked as Interest. This assumes any excess really is interest on
     * these loans, not an unrelated overpayment or repayment of a given
     * recorded on a different account — a reasonable default given how
     * you described the flow, but worth revisiting if recoveries and
     * givens routinely span different accounts for you.
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

            $remaining = $originalAmount;
            $totalMatched = 0.0;

            foreach ($outstandingGivens as $given) {
                if ($remaining <= 0) break;
                $givenAmount = (float) $given->amount;
                $matched = min($remaining, $givenAmount);
                $remaining -= $matched;
                $totalMatched += $matched;

                if ($matched >= $givenAmount - 0.0001) {
                    $given->delete();
                } else {
                    $given->amount = $givenAmount - $matched;
                    $given->save();
                }
            }

            if ($totalMatched <= 0) return;

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
