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

        // Unpaid, not-kept commission, attributed by payment date, the same basis
        // as $totalInterest above. Paid-out commission is already an expense
        // transaction, and kept commission was never in our deposit, so neither
        // is subtracted again here.
        $referrerCut = (float) $payments
            ->filter(fn ($p) => $inRange($p->payment_date)
                && !$p->referrer_payout_id
                && !$p->referrer_commission_kept)
            ->sum('referrer_commission');

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

        // ── Transaction costs vs gross profit, referred vs unreferred ──────
        // Same rules as the headline cards: interest by payment date + legacy
        // residual on loans closed in the period; costs = disbursement fees on
        // loans disbursed in the period.
        $loansById = $loans->keyBy('id');

        $feeByDisbursement = $disbursementIds->isNotEmpty()
            ? Transaction::where('is_transaction_fee', true)
                ->whereIn('fee_for_transaction_id', $disbursementIds)
                ->selectRaw('fee_for_transaction_id, SUM(amount) as fee')
                ->groupBy('fee_for_transaction_id')
                ->pluck('fee', 'fee_for_transaction_id')
            : collect();

        $costBySource = [];

        foreach (['referred' => true, 'unreferred' => false] as $key => $wantReferred) {
            $inGroup = fn ($l) => $l && ((bool) $l->referrer_id === $wantReferred);

            $interest = $payments
                    ->filter(fn ($p) => $inRange($p->payment_date) && $inGroup($loansById->get($p->loan_given_id)))
                    ->sum('interest_portion')
                + $paidInRange->filter($inGroup)->sum($residualInterest);

            $costs = $disbursedInRange->filter($inGroup)
                ->sum(fn ($l) => (float) ($feeByDisbursement[$l->disbursement_transaction_id] ?? 0));

            $cut = $wantReferred ? (float) $referrerCut : 0.0;

            $costBySource[$key] = [
                'loans'         => $disbursedInRange->filter($inGroup)->count(),
                'interest'      => (float) $interest,
                'costs'         => (float) $costs,
                'cost_pct'      => $interest > 0 ? round($costs / $interest * 100, 1) : null,
                'referrer_cut'  => $cut,
                'net'           => (float) $interest - $costs,         // after costs
                'net_after_cut' => (float) $interest - $costs - $cut,  // after costs + referrer share
            ];
        }

        $allInterest = $costBySource['referred']['interest'] + $costBySource['unreferred']['interest'];
        $allCosts    = $costBySource['referred']['costs'] + $costBySource['unreferred']['costs'];
        $costBySource['total'] = [
            'interest' => $allInterest,
            'costs'    => $allCosts,
            'cost_pct' => $allInterest > 0 ? round($allCosts / $allInterest * 100, 1) : null,
        ];

        return compact(
            'totalPrincipal', 'totalRepaid', 'totalInterest', 'avgInterestRate', 'repaymentRate',
            'totalOutstanding', 'totalReceivedAllTime', 'totalTransactionCosts', 'netInterest',
            'referrerCut', 'repaidCount', 'disbursedCount', 'loanYield', 'costBySource'
        );
    }
}
