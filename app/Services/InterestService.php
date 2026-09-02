<?php

namespace App\Services;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Str;

class InterestService
{
    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 1: Date Tracking (day-based)
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Returns the date of the most recent interest transaction, or null if none exist.
     */
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
     * Returns true if no interest has been recorded today.
     * Recording is allowed at most once per calendar day.
     */
    public function canRecordToday(Account $account): bool
    {
        $lastDate = $this->getLastInterestDate($account);

        if (! $lastDate) {
            return true;
        }

        // Block if the last interest was already recorded today
        return ! $lastDate->isToday();
    }

    /**
     * Returns the number of calendar days that have been skipped since the last
     * interest recording (not counting today, which is the day being recorded).
     *
     * Examples:
     *   Last recorded: today        → null  (already done today, canRecordToday() would block)
     *   Last recorded: yesterday    → 0     (normal — no skip)
     *   Last recorded: 3 days ago   → 2     (today + 2 skipped days in between)
     *
     * Returns null when there is no previous recording.
     */
    public function getSkippedDaysCount(Account $account): ?int
    {
        $lastDate = $this->getLastInterestDate($account);

        if (! $lastDate) {
            return null;
        }

        // Days elapsed from last recording up to and including today
        $daysElapsed = (int) $lastDate->copy()->startOfDay()->diffInDays(now()->startOfDay());

        if ($daysElapsed === 0) {
            return null; // already recorded today
        }

        if ($daysElapsed === 1) {
            return 0;    // normal — consecutive day, no skip
        }

        // Number of days being backfilled (yesterday back to day-after-last)
        return $daysElapsed - 1;
    }

    /**
     * Returns the full list of dates that need an interest transaction,
     * ordered from oldest to newest. When there are no skips this is just [today].
     * When days were skipped, it includes the gap days plus today.
     */
    public function getTargetDates(Account $account): array
    {
        $lastDate = $this->getLastInterestDate($account);

        $startDate = $lastDate
            ? $lastDate->copy()->addDay()->startOfDay()
            : now()->startOfDay();

        $endDate = now()->startOfDay();

        $dates = [];
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * Returns date range info for Blade display when days have been skipped.
     */
    public function getSkippedDateRange(Account $account): ?array
    {
        $lastDate    = $this->getLastInterestDate($account);
        $skippedDays = $this->getSkippedDaysCount($account);

        if ($skippedDays === null || $skippedDays === 0) {
            return null;
        }

        $gapStart = $lastDate->copy()->addDay();
        $gapEnd   = now()->copy()->subDay();    // everything before today
        $totalDays = $skippedDays + 1;          // gap days + today

        return [
            'last_recorded' => $lastDate->format('M d, Y'),
            'gap_start'     => $gapStart->format('M d, Y'),
            'gap_end'       => $gapEnd->format('M d, Y'),
            'days_count'    => $skippedDays,   // number of skipped (gap) days
            'total_days'    => $totalDays,      // total days being covered incl. today
        ];
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 2: Transaction Building
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Same as buildDailyEntries() but also returns the batch id used to tag
     * every transaction created for this single recordInterest() call, so the
     * whole recording can be reversed as one unit later.
     */
    public function buildDailyEntries(Account $account, float $totalAmount, ?string $batchId = null): array
    {
        $batchId = $batchId ?? (string) Str::uuid();
        $dates   = $this->getTargetDates($account);
        $count   = count($dates);
        $perDay  = round($totalAmount / $count, 2);
        $entries = [];

        foreach ($dates as $i => $date) {
            $amount = ($i === $count - 1)
                ? round($totalAmount - ($perDay * ($count - 1)), 2)
                : $perDay;

            $entries[] = [
                'date'        => $date,
                'amount'      => $amount,
                'description' => 'Interest earned – ' . $date->format('M d, Y'),
                'batch_id'    => $batchId,
            ];
        }

        return $entries;
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 3: Validation
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

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 4: Etica Gate
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Returns true if the given savings account requires an interest recording
     * before a withdrawal/transfer can proceed.
     *
     * This applies to any savings account whose name contains "etica" (case-insensitive).
     */
    public function requiresInterestBeforeWithdrawal(Account $account): bool
    {
        if ($account->type !== 'savings') {
            return false;
        }

        return str_contains(strtolower($account->name), 'etica');
    }

    /**
     * Returns true if the Etica interest gate is satisfied — i.e. interest has
     * already been recorded today (so a withdrawal can proceed).
     */
    public function isInterestGateSatisfied(Account $account): bool
    {
        return ! $this->canRecordToday($account);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 5: Deprecated / Legacy shims
    // ════════════════════════════════════════════════════════════════════════════

    /** @deprecated Use canRecordToday() */
    public function canRecordTodayKey(Account $account): bool
    {
        return $this->canRecordToday($account);
    }

    /** @deprecated Use getSkippedDaysCount() */
    public function getSkippedMonthsCount(Account $account): ?int
    {
        return $this->getSkippedDaysCount($account);
    }

    /** @deprecated Use getSkippedDaysCount() */
    public function getSkippedMonthsMessage(Account $account): ?string
    {
        $skipped = $this->getSkippedDaysCount($account);

        if ($skipped === null || $skipped === 0) {
            return null;
        }

        $totalDays = $skipped + 1;
        $dayWord   = $skipped === 1 ? 'day' : 'days';

        return "You skipped {$skipped} {$dayWord}. "
            . "Is the interest being recorded for the last {$totalDays} days?";
    }

    /** @deprecated Not used in day-based logic */
    public function getTargetMonth(): \Carbon\CarbonInterface
    {
        return now()->subMonthNoOverflow()->startOfMonth();
    }
    public function getReversibleInterestBatch(Account $account): ?string
    {
        $last = $account->transactions()
            ->whereNull('deleted_at')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.name', 'Interest')
            ->whereNotNull('transactions.batch_id')
            ->orderByDesc('transactions.created_at')
            ->select('transactions.batch_id', 'transactions.created_at')
            ->first();

        if (! $last || Carbon::parse($last->created_at)->diffInMinutes(now()) > 60) {
            return null;
        }

        return $last->batch_id;
    }
}
