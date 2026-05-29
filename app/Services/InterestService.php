<?php

namespace App\Services;

use App\Models\Account;
use Carbon\Carbon;

class InterestService
{
    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 1: Date Tracking
    // ════════════════════════════════════════════════════════════════════════════

    public function getLastInterestDate(Account $account): ?Carbon
    {
        $lastInterest = $account->transactions()
            ->whereNull('deleted_at')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.name', 'Interest')
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->select('transactions.date')
            ->first();

        return $lastInterest ? Carbon::parse($lastInterest->date) : null;
    }

    /**
     * Returns true if no interest has been recorded in the current calendar month.
     */
    public function canRecordTodayKey(Account $account): bool
    {
        $lastDate = $this->getLastInterestDate($account);

        if (!$lastDate) return true;

        // Block if already recorded this calendar month
        return !($lastDate->month === now()->month && $lastDate->year === now()->year);
    }

    /**
     * Returns the number of calendar months skipped since the last recording.
     * Returns null if no previous recording exists.
     * Returns 0 if only one month has elapsed (i.e. no skip).
     */
    public function getSkippedMonthsCount(Account $account): ?int
    {
        $lastDate = $this->getLastInterestDate($account);

        if (!$lastDate) return null;

        $monthsElapsed = (int) $lastDate->copy()->startOfMonth()->diffInMonths(now()->startOfMonth());

        if ($monthsElapsed === 0) return null; // already recorded this month
        if ($monthsElapsed === 1) return 0;    // normal — one month gap, no skip

        return $monthsElapsed - 1; // number of skipped months
    }

    /**
     * @deprecated Use getSkippedMonthsCount() instead.
     */
    public function getSkippedDaysCount(Account $account): ?int
    {
        return $this->getSkippedMonthsCount($account);
    }

    public function getSkippedMonthsMessage(Account $account): ?string
    {
        $skipped = $this->getSkippedMonthsCount($account);

        if ($skipped === null || $skipped === 0) return null;

        $totalMonths   = $skipped + 1;
        $monthWord     = $skipped === 1 ? 'month' : 'months';
        $monthsWord    = $totalMonths === 1 ? 'month' : 'months';

        return "You skipped {$skipped} {$monthWord}. " .
            "Is the interest being recorded for the last {$totalMonths} {$monthsWord}?";
    }

    /**
     * Returns date range info for display when months have been skipped.
     */
    public function getSkippedDateRange(Account $account): ?array
    {
        $lastDate = $this->getLastInterestDate($account);

        if (!$lastDate) return null;

        $monthsElapsed = (int) $lastDate->copy()->startOfMonth()->diffInMonths(now()->startOfMonth());

        if ($monthsElapsed <= 1) return null;

        $skipped     = $monthsElapsed - 1;
        $totalMonths = $monthsElapsed;

        return [
            'last_recorded' => $lastDate->format('M Y'),
            'gap_start'     => $lastDate->copy()->addMonth()->format('M Y'),
            'gap_end'       => now()->copy()->subMonth()->format('M Y'),
            'days_count'    => $skipped,      // kept for Blade compatibility (= skipped months)
            'total_days'    => $totalMonths,   // kept for Blade compatibility (= total months)
        ];
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 2: Validation
    // ════════════════════════════════════════════════════════════════════════════

    public function validateInterestAmount(float $amount): array
    {
        $errors = [];

        if ($amount < 0) {
            $errors[] = 'Interest amount cannot be negative.';
            return $errors;
        }

        if ($amount == 0) {
            $errors[] = 'Interest amount must be greater than zero.';
        }

        return $errors;
    }
}
