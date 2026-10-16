<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoanBookStatsService
{
    public function __construct(protected LoanYieldService $yield) {}

    /** @return array{0: ?Carbon, 1: ?Carbon, 2: string} [start, end, label] */
    public static function resolvePeriod(string $period, ?string $customStart = null, ?string $customEnd = null): array
    {
        return match ($period) {
            'this_month' => [now()->startOfMonth(), now()->endOfMonth(), 'This month'],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth(), 'Last month'],
            'this_year'  => [now()->startOfYear(), now()->endOfYear(), 'This year'],
            'last_year'  => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear(), 'Last year'],
            'custom'     => [
                $customStart ? Carbon::parse($customStart)->startOfDay() : null,
                $customEnd   ? Carbon::parse($customEnd)->endOfDay()     : null,
                'Custom range',
            ],
            default      => [null, null, 'All-time'],
        };
    }

    /**
     * @param Collection $loans    every LoanGiven in scope (any status), payments need not be loaded
     * @param Collection $payments every LoanGivenPayment belonging to those loans
     */
    public function compute(Collection $loans, Collection $payments, ?Carbon $start, ?Carbon $end): array
    {
        $inRange = fn ($date) => $date !== null
            && (!$start || Carbon::parse($date)->gte($start))
            && (!$end   || Carbon::parse($date)->lte($end));

        $active = $loans->where('status', 'active');
        $paid   = $loans->where('status', 'paid');

        $disbursedInRange = $loans->filter(fn ($l) => $inRange($l->disbursed_date));
        $paidInRange      = $paid->filter(fn ($l) => $inRange($l->repaid_date));

        $totalPrincipal = $disbursedInRange->sum(fn ($l) => $l->original_principal);
        $totalRepaid    = $paidInRange->sum('amount_paid');

        // Interest is attributed to the month it was recognised (payment date),
        // plus any legacy residual on loans closed in the period.
        $recognizedByLoan = $payments->groupBy('loan_given_id')
            ->map(fn ($g) => (float) $g->sum('interest_portion'));

        $residualInterest = fn ($l) => max(0, (float) $l->interest_amount - ($recognizedByLoan[$l->id] ?? 0));

        $totalInterest = $payments->filter(fn ($p) => $inRange($p->payment_date))->sum('interest_portion')
            + $paidInRange->sum($residualInterest);

        $totalOutstanding = $active->sum('outstanding_amount');

        $disbursementIds = $disbursedInRange->pluck('disbursement_transaction_id')->filter()->values();
        $totalTransactionCosts = $disbursementIds->isNotEmpty()
            ? Transaction::where('is_transaction_fee', true)
                ->whereIn('fee_for_transaction_id', $disbursementIds)
                ->sum('amount')
            : 0;

        $referrerCut = $paidInRange
            ->filter(fn ($l) => $l->referrer_id
                && $l->referrer_share_percentage !== null
                && !$l->referrer_payout_id
                && !$l->referrer_deducted_before_deposit)
            ->sum(fn ($l) => round($l->interest_amount * ($l->referrer_share_percentage / 100), 2));

        $netInterest = $totalInterest - $totalTransactionCosts - $referrerCut;

        $loanYield = $this->yield->compute($loans, $payments, $start, $end, (float) $netInterest);

        $totalReceivedAllTime = $payments
            ->filter(fn ($p) => (!$start || Carbon::parse($p->payment_date)->gte($start->copy()->startOfDay()))
                && (!$end   || Carbon::parse($p->payment_date)->lte($end->copy()->endOfDay())))
            ->sum('amount');

        $withInterest   = $paidInRange->filter(fn ($l) => $l->interest_amount > 0);
        $avgInterestRate = $withInterest->isNotEmpty() ? $withInterest->avg('interest_rate') : 0;

        $disbursedCount = $disbursedInRange->count();
        $repaidCount    = $disbursedInRange->where('status', 'paid')->count();
        $repaymentRate  = $disbursedCount > 0 ? ($repaidCount / $disbursedCount) * 100 : 0;

        return compact(
            'totalPrincipal', 'totalRepaid', 'totalInterest', 'avgInterestRate', 'repaymentRate',
            'totalOutstanding', 'totalReceivedAllTime', 'totalTransactionCosts', 'netInterest',
            'referrerCut', 'repaidCount', 'disbursedCount', 'loanYield'
        );
    }
}
