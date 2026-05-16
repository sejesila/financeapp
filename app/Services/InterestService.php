<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;

class InterestService
{
    /**
     * Get the last interest recording date for an account
     */
    public function getLastInterestDate(Account $account): ?Carbon
    {
        $lastInterest = $account->transactions()
            ->whereNull('deleted_at')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.name', 'Interest')
            ->orderByDesc('transactions.date')
            ->select('transactions.date')
            ->first();

        return $lastInterest ? Carbon::parse($lastInterest->date) : null;
    }

    /**
     * Check if interest can be recorded today
     * Returns false if already recorded today
     */
    public function canRecordTodayKey(Account $account): bool
    {
        $lastDate = $this->getLastInterestDate($account);

        if (!$lastDate) {
            return true; // No interest recorded yet
        }

        return !$lastDate->isSameDay(now());
    }

    /**
     * Check if there are skipped days since last interest recording
     * Returns the count of days skipped (0 if consecutive, null if no prior recording)
     */
    public function getSkippedDaysCount(Account $account): ?int
    {
        $lastDate = $this->getLastInterestDate($account);

        if (!$lastDate) {
            return null; // No prior interest recording
        }

        $daysSinceLastRecording = $lastDate->diffInDays(now());

        if ($daysSinceLastRecording === 0) {
            return null; // Already recorded today
        }

        if ($daysSinceLastRecording === 1) {
            return 0; // No skipped days (recorded yesterday)
        }

        return $daysSinceLastRecording - 1; // Number of skipped days
    }

    /**
     * Get a human-readable message about skipped days
     */
    public function getSkippedDaysMessage(Account $account): ?string
    {
        $skippedDays = $this->getSkippedDaysCount($account);

        if ($skippedDays === null) {
            return null;
        }

        if ($skippedDays === 0) {
            return null; // No gaps
        }

        return "You skipped {$skippedDays} " . ($skippedDays === 1 ? 'day' : 'days') .
            ". Is the interest being recorded for the last " . ($skippedDays + 1) . " " .
            ($skippedDays + 1 === 1 ? 'day' : 'days') . "?";
    }

    /**
     * Get the date range for skipped days explanation
     */
    public function getSkippedDateRange(Account $account): ?array
    {
        $lastDate = $this->getLastInterestDate($account);

        if (!$lastDate) {
            return null;
        }

        $daysSinceLastRecording = $lastDate->diffInDays(now());

        if ($daysSinceLastRecording <= 1) {
            return null; // No skipped days
        }

        $lastRecordedDate = $lastDate->copy();
        $nextDayAfterLastRecorded = $lastRecordedDate->copy()->addDay();
        $today = now();

        return [
            'last_recorded' => $lastRecordedDate->format('M d, Y'),
            'gap_start'     => $nextDayAfterLastRecorded->format('M d, Y'),
            'gap_end'       => $today->copy()->subDay()->format('M d, Y'),
            'days_count'    => $daysSinceLastRecording - 1,
            'total_days'    => $daysSinceLastRecording,
        ];
    }
}
