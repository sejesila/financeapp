<?php

namespace App\Services;

use App\Models\Borrower;
use Illuminate\Support\Collection;

class BorrowerStatsService
{
    // Must match LoanGiven::processOverdueRollover() / isEligibleForRollover()
    private const TERM_DAYS  = 30;
    private const GRACE_DAYS = 7;

    /** Stats for one borrower. */
    public function forBorrower(Borrower $borrower): array
    {
        $loans = $borrower->relationLoaded('loans')
            ? $borrower->loans
            : $borrower->loans()->with('payments')->get();

        return $this->compute($loans);
    }

    /** Stats for every borrower (index page) — two queries total. */
    public function forAll(): Collection
    {
        return Borrower::with('loans.payments')->get()
            ->map(fn ($b) => ['borrower' => $b] + $this->compute($b->loans));
    }

    public function compute(Collection $loans): array
    {
        $paid   = $loans->where('status', 'paid');
        $active = $loans->where('status', 'active');
        $bad    = $loans->whereIn('status', ['defaulted', 'written_off']);

        // ── Money ────────────────────────────────────────────────────────
        $totalBorrowed = $loans->sum(fn ($l) => $l->original_principal);

        // Closed loans: final interest_amount (already includes rollover interest).
        // Active loans: interest already recognised via payments' interest_portion.
        $interestEarned = $paid->sum('interest_amount')
            + $active->sum(fn ($l) => $l->payments->sum('interest_portion'));

        $outstanding = $active->sum('outstanding_amount');
        $unrecovered = $bad->sum('balance');

        // ── Timing (closed loans only) ───────────────────────────────────
        $daysToRepay = $paid->filter(fn ($l) => $l->repaid_date && $l->disbursed_date)
            ->map(fn ($l) => (int) $l->disbursed_date->copy()->startOfDay()
                ->diffInDays($l->repaid_date->copy()->startOfDay(), true));

        $avgDays = $daysToRepay->isNotEmpty() ? round($daysToRepay->avg(), 1) : null;

        // "Late" = took longer than term + grace to close. (due_date can't be
        // used: it's reset on every partial payment / rollover.)
        $lateCount = $daysToRepay->filter(fn ($d) => $d > self::TERM_DAYS + self::GRACE_DAYS)->count();
        $lateRate  = $daysToRepay->isNotEmpty() ? $lateCount / $daysToRepay->count() : 0;

        // ── Current behaviour ────────────────────────────────────────────
        $overdueNow = $active->filter(fn ($l) => $l->due_date && $l->due_date->isPast());
        $maxOverdueDays = $overdueNow->isNotEmpty()
            ? (int) $overdueNow->map(fn ($l) => $l->due_date->diffInDays(now(), true))->max()
            : 0;

        $stats = [
            'loans_count'       => $loans->count(),
            'active_count'      => $active->count(),
            'paid_count'        => $paid->count(),
            'bad_count'         => $bad->count(),
            'total_borrowed'    => round($totalBorrowed, 2),
            'interest_earned'   => round($interestEarned, 2),
            'avg_interest_rate' => round((float) $paid->where('interest_amount', '>', 0)->avg('interest_rate'), 1),
            'outstanding'       => round($outstanding, 2),
            'unrecovered'       => round($unrecovered, 2),
            'avg_days_to_repay' => $avgDays,
            'fastest_days'      => $daysToRepay->min(),
            'slowest_days'      => $daysToRepay->max(),
            'late_rate'         => (int) round($lateRate * 100),
            'overdue_now'       => $overdueNow->count(),
            'max_overdue_days'  => $maxOverdueDays,
            'rollovers'         => (int) $loans->sum('rollover_count'),
            'first_loan'        => $loans->min('disbursed_date'),
            'last_loan'         => $loans->max('disbursed_date'),
        ];

        return $stats + $this->risk($stats);
    }

    /**
     * Transparent 0–100 score (higher = riskier). Each rule is one line and
     * surfaces in 'risk_reasons', so the number is never a black box.
     */
    private function risk(array $s): array
    {
        $score = 0;
        $why   = [];

        if ($s['bad_count'] > 0) {
            $score += min(70, $s['bad_count'] * 35);
            $why[] = "{$s['bad_count']} defaulted/written-off loan(s)";
        }
        if ($s['overdue_now'] > 0) {
            $score += min(30, $s['overdue_now'] * 15) + ($s['max_overdue_days'] > 30 ? 10 : 0);
            $why[] = "{$s['overdue_now']} loan(s) overdue now (up to {$s['max_overdue_days']} days)";
        }
        if ($s['avg_days_to_repay'] !== null) {
            if ($s['avg_days_to_repay'] > 45) {
                $score += 15; $why[] = 'Averages 45+ days to repay';
            } elseif ($s['avg_days_to_repay'] > self::TERM_DAYS + self::GRACE_DAYS) {
                $score += 8; $why[] = 'Usually repays after the due date';
            }
        }
        if ($s['late_rate'] >= 50) {
            $score += 10; $why[] = "{$s['late_rate']}% of closed loans were late";
        }
        if ($s['loans_count'] > 0 && $s['rollovers'] / $s['loans_count'] >= 1) {
            $score += 10; $why[] = 'Frequently rolls loans over';
        }
        if ($s['paid_count'] >= 3 && $s['bad_count'] === 0 && $s['overdue_now'] === 0) {
            $score -= 10; $why[] = 'Clean record over 3+ repaid loans';
        }

        $score = max(0, min(100, $score));

        $tier = match (true) {
            $s['paid_count'] < 2 && $s['bad_count'] === 0 && $score < 25 => 'new',
            $score < 25 => 'low',
            $score < 55 => 'medium',
            default     => 'high',
        };

        return ['risk_score' => $score, 'risk_tier' => $tier, 'risk_reasons' => $why];
    }
}
