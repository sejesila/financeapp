<?php

namespace App\Services;

use App\Models\LoanGiven;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Return on deployed capital for the loans-given book.
 *
 * Mirrors StatementDataService::computeYield(): build a daily series of capital
 * at work and average it. Returns the raw (un-annualized) return for the period.
 * The differences from the savings version:
 *
 *  - Capital is PRINCIPAL OUT (cash you actually lent and haven't got back),
 *    not a balance that interest compounds into. Capitalized/rolled-over
 *    interest is NOT counted as deployed cash.
 *  - Numerator is NET interest (after disbursement fees and referrer share),
 *    minus principal written off in the period.
 */
class LoanYieldService
{
    /**
     * @param Collection $loans       All of the user's LoanGiven rows (any status)
     * @param Collection $payments    All of the user's LoanGivenPayment rows
     * @param Carbon|null $from       Stats period start (null = first disbursement)
     * @param Carbon|null $to         Stats period end (null/future = today)
     * @param float       $netInterest Interest - transaction costs - referrer share (period)
     */
    public function compute(Collection $loans, Collection $payments, ?Carbon $from, ?Carbon $to, float $netInterest): ?array
    {
        $today = now()->startOfDay();

        $end = ($to ?? $today)->copy()->startOfDay();
        if ($end->gt($today)) {
            $end = $today;
        }

        $start = $from?->copy()->startOfDay()
            ?? $loans->filter(fn ($l) => $l->disbursed_date)
                ->map(fn ($l) => Carbon::parse($l->disbursed_date)->startOfDay())
                ->sortBy(fn ($d) => $d->timestamp)
                ->first();

        if (! $start || $start->gt($end)) {
            return null;
        }

        // ── Daily deltas of principal out ────────────────────────────────────
        // Convention: a loan is "out" from its disbursement day up to and
        // including the day it is repaid (the repayment lowers capital from the
        // NEXT day), so a same-day loan still counts one day.
        $deltas = [];
        $add = function (string $day, float $amount) use (&$deltas) {
            $deltas[$day] = ($deltas[$day] ?? 0.0) + $amount;
        };

        $paymentsByLoan = $payments->groupBy('loan_given_id');

        foreach ($loans as $loan) {
            if (! $loan->disbursed_date) {
                continue;
            }

            $remaining = (float) $loan->original_principal;
            $add(Carbon::parse($loan->disbursed_date)->toDateString(), $remaining);

            $loanPayments = $paymentsByLoan->get($loan->id, collect())->sortBy('payment_date');

            foreach ($loanPayments as $p) {
                // Only the principal part of a payment frees up capital.
                $portion = min($remaining, max(0.0, (float) $p->amount - (float) $p->interest_portion));
                if ($portion <= 0) {
                    continue;
                }
                $remaining -= $portion;
                $add(Carbon::parse($p->payment_date)->addDay()->toDateString(), -$portion);
            }

            // Closed loan that never returned its full principal (e.g. settled
            // short): release whatever is left on the closing date.
            if ($loan->status === 'paid' && $remaining > 0.005 && $loan->repaid_date) {
                $add(Carbon::parse($loan->repaid_date)->addDay()->toDateString(), -$remaining);
            }
        }

        // Capital already out before the window opens.
        $capital = 0.0;
        foreach ($deltas as $day => $amount) {
            if ($day < $start->toDateString()) {
                $capital += $amount;
            }
        }

        $sum  = 0.0;
        $days = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $capital += $deltas[$d->toDateString()] ?? 0.0;
            $sum     += max(0.0, $capital);
            $days++;
        }

        $avg = $days > 0 ? $sum / $days : 0.0;

        // ── Losses ───────────────────────────────────────────────────────────
        // NOTE: there is no written_off_at column, so updated_at is used as a
        // proxy for "when it was written off". Add a real timestamp if you want
        // this to be exact.
        $writeOffs = $loans
            ->filter(fn ($l) => $l->status === 'written_off'
                && $l->updated_at
                && $l->updated_at->between($start, $end->copy()->endOfDay()))
            ->sum(fn ($l) => max(0.0, (float) $l->balance));

        $atRisk = $loans
            ->where('status', 'defaulted')
            ->sum(fn ($l) => max(0.0, (float) $l->balance));

        if ($avg <= 0) {
            return null;
        }

        $netAfterLosses = $netInterest - $writeOffs;
        $periodReturn   = $netAfterLosses / $avg;

        return [
            'days'                => $days,
            'avg_capital'         => $avg,
            'net_interest'        => $netInterest,
            'write_offs'          => (float) $writeOffs,
            'at_risk'             => (float) $atRisk,
            'period_return_pct'   => $periodReturn * 100,
        ];
    }

    /**
     * Informational simple annualized rate for ONE closed loan:
     *   interest_amount / original_principal * 365 / days_outstanding
     * Not a rate you "earn": it just makes loans comparable to other investments.
     */
    public function loanSimpleAnnualized(LoanGiven $loan): ?float
    {
        $principal = (float) $loan->original_principal;

        if ($loan->status !== 'paid' || $principal <= 0 || (float) $loan->interest_amount <= 0 || ! $loan->disbursed_date) {
            return null;
        }

        $endDate = $loan->repaid_date ?? now();
        $days = max(1, (int) Carbon::parse($loan->disbursed_date)->startOfDay()
            ->diffInDays(Carbon::parse($endDate)->startOfDay(), true));

        return ((float) $loan->interest_amount / $principal) * (365 / $days) * 100;
    }
}
