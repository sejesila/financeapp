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

    public function canRecordTodayKey(Account $account): bool
    {
        $lastDate = $this->getLastInterestDate($account);
        if (!$lastDate) return true;
        return !$lastDate->isSameDay(now());
    }

    public function getSkippedDaysCount(Account $account): ?int
    {
        $lastDate = $this->getLastInterestDate($account);
        if (!$lastDate) return null;

        $daysSinceLastRecording = $lastDate->diffInDays(now());

        if ($daysSinceLastRecording === 0) return null;
        if ($daysSinceLastRecording === 1) return 0;

        return $daysSinceLastRecording - 1;
    }

    public function getSkippedDaysMessage(Account $account): ?string
    {
        $skippedDays = $this->getSkippedDaysCount($account);
        if ($skippedDays === null || $skippedDays === 0) return null;

        $totalDays = $skippedDays + 1;
        $dayWord   = $skippedDays === 1 ? 'day' : 'days';
        $daysWord  = $totalDays === 1 ? 'day' : 'days';

        return "You skipped {$skippedDays} {$dayWord}. " .
            "Is the interest being recorded for the last {$totalDays} {$daysWord}?";
    }

    public function getSkippedDateRange(Account $account): ?array
    {
        $lastDate = $this->getLastInterestDate($account);
        if (!$lastDate) return null;

        $daysSinceLastRecording = $lastDate->diffInDays(now());
        if ($daysSinceLastRecording <= 1) return null;

        return [
            'last_recorded' => $lastDate->format('M d, Y'),
            'gap_start'     => $lastDate->copy()->addDay()->format('M d, Y'),
            'gap_end'       => now()->copy()->subDay()->format('M d, Y'),
            'days_count'    => $daysSinceLastRecording - 1,
            'total_days'    => $daysSinceLastRecording,
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
