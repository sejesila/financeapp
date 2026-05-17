<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use Carbon\Carbon;

/**
 * Interest Service - Enhanced Version
 *
 * Stores rates as percentages (e.g., 0.038356 for 0.0384%/day ≈ 14% APY)
 */
class InterestService
{
    const MAX_APY        = 25.0;
    const MAX_DAILY_RATE = 25.0 / 365;

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
    // SECTION 2: Rate Computation
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Compute the effective daily interest rate (in percentage format).
     *
     * Formula: (interest_amount / opening_balance / period_days) * 100
     */
    public function computeDailyRate(Account $account, Transaction $interestTxn): ?float
    {
        $openingBalance = $this->computeOpeningBalance(
            $account,
            $interestTxn->date,
            $interestTxn->id
        );

        if (!$openingBalance || $openingBalance <= 0) {
            return null;
        }

        $days = $this->extractPeriodDays($interestTxn->description);

        return round(($interestTxn->amount / $openingBalance / $days) * 100, 6);
    }

    /**
     * Compute the balance used as the base for interest rate calculation.
     *
     * Includes BOTH transactions AND transfers so that accounts funded
     * primarily via transfers (like an MMF) have a correct opening balance.
     *
     * Transaction rules:
     *  - Income / liability ON OR BEFORE date  → added
     *  - Expenses ON OR BEFORE date            → deducted
     *  - Interest on the SAME day              → excluded (circular reference)
     *  - $excludeId transaction                → always excluded
     *
     * Transfer rules:
     *  - Incoming transfers ON OR BEFORE date  → added
     *  - Outgoing transfers ON OR BEFORE date  → deducted
     */
    public function computeOpeningBalance(Account $account, $date, ?int $excludeId = null): float
    {
        if ($date instanceof Carbon) {
            $date = $date->toDateString();
        }

        // ── 1. Net from transactions ──────────────────────────────────────────
        $txNet = (float) Transaction::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereNull('transactions.deleted_at')
            ->when($excludeId, fn($q) => $q->where('transactions.id', '!=', $excludeId))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN categories.name = 'Interest'
                             AND DATE(transactions.date) = ?
                        THEN 0
                        WHEN categories.type IN ('income', 'liability')
                             AND DATE(transactions.date) <= ?
                        THEN transactions.amount
                        WHEN categories.type = 'expense'
                             AND DATE(transactions.date) <= ?
                        THEN -transactions.amount
                        ELSE 0
                    END
                ) AS net
            ", [$date, $date, $date])
            ->value('net');

        // ── 2. Net from transfers ─────────────────────────────────────────────
        $transfersIn = (float) Transfer::withoutGlobalScopes()
            ->where('to_account_id', $account->id)
            ->whereDate('date', '<=', $date)
            ->sum('amount');

        $transfersOut = (float) Transfer::withoutGlobalScopes()
            ->where('from_account_id', $account->id)
            ->whereDate('date', '<=', $date)
            ->sum('amount');

        return (float) $account->initial_balance + $txNet + $transfersIn - $transfersOut;
    }

    /**
     * Extract the number of days from an interest description.
     *
     *   "Interest earned (7-day period)" → 7
     *   "Interest earned"               → 1
     */
    public function extractPeriodDays(string $description): int
    {
        if (preg_match('/\((\d+)-day period\)/', $description, $m)) {
            return max(1, (int) $m[1]);
        }
        return 1;
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 3: Validation
    // ════════════════════════════════════════════════════════════════════════════

    public function validateInterestAmount(Account $account, float $amount, $date): array
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;
        $errors  = [];

        if ($amount < 0) {
            $errors[] = 'Interest amount cannot be negative.';
            return $errors;
        }

        if ($amount == 0) {
            $errors[] = 'Interest amount must be greater than zero.';
            return $errors;
        }

        $openingBalance = $this->computeOpeningBalance($account, $dateStr);

        if ($openingBalance <= 0) {
            $errors[] = 'Cannot record interest without a positive account balance.';
            return $errors;
        }

        $impliedDailyPct = ($amount / $openingBalance) * 100;
        $impliedAPY      = $impliedDailyPct * 365;

        if ($impliedAPY > self::MAX_APY) {
            $impliedAPYRounded = round($impliedAPY, 2);
            $maxDaily          = number_format($openingBalance * (self::MAX_APY / 100 / 365), 2);
            $errors[] = "Interest rate ({$impliedAPYRounded}% APY) exceeds the maximum allowed (" .
                self::MAX_APY . "% APY). " .
                "At your current balance the maximum daily interest is KES {$maxDaily}.";
        }

        return $errors;
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 4: Calculations
    // ════════════════════════════════════════════════════════════════════════════

    public function calculateAPY(?float $dailyRatePercent): ?float
    {
        if ($dailyRatePercent === null) return null;
        return $dailyRatePercent * 365;
    }

    public function calculateCompoundAPY(?float $dailyRatePercent): ?float
    {
        if ($dailyRatePercent === null) return null;
        return (pow(1 + ($dailyRatePercent / 100), 365) - 1) * 100;
    }

    public function exceedsMaximumRate(float $dailyRatePercent): bool
    {
        return $this->calculateAPY($dailyRatePercent) > self::MAX_APY;
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 5: Formatting
    // ════════════════════════════════════════════════════════════════════════════

    public function formatDailyRate(?float $dailyRatePercent, int $decimals = 4): string
    {
        if ($dailyRatePercent === null) return '—';
        return number_format($dailyRatePercent, $decimals) . '%';
    }

    public function formatAPY(?float $dailyRatePercent, int $decimals = 2): string
    {
        if ($dailyRatePercent === null) return '—';
        return number_format($this->calculateAPY($dailyRatePercent), $decimals) . '%';
    }

    public function getRateSummary(?float $dailyRatePercent): ?string
    {
        if ($dailyRatePercent === null) return null;
        return $this->formatDailyRate($dailyRatePercent) . '/day (' . $this->formatAPY($dailyRatePercent) . ' APY)';
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 6: Statistics
    // ════════════════════════════════════════════════════════════════════════════

    public function getAverageDailyRate($transactions): ?float
    {
        if ($transactions->isEmpty()) return null;

        $totalRate = 0;
        $count     = 0;

        foreach ($transactions as $tx) {
            if ($tx->computed_rate !== null) {
                $totalRate += $tx->computed_rate;
                $count++;
            }
        }

        return $count > 0 ? round($totalRate / $count, 6) : null;
    }

    public function getInterestStatsByPeriod(Account $account, string $period = 'monthly', ?int $year = null)
    {
        $year    = $year ?? now()->year;
        $groupBy = match ($period) {
            'daily'   => 'DATE(transactions.date)',
            'weekly'  => 'WEEK(transactions.date)',
            'monthly' => 'MONTH(transactions.date)',
            'yearly'  => 'YEAR(transactions.date)',
        };

        return $account->transactions()
            ->whereNull('deleted_at')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.name', 'Interest')
            ->whereYear('transactions.date', $year)
            ->selectRaw("
                {$groupBy} as period,
                DATE_FORMAT(transactions.date, '%M %Y') as period_label,
                SUM(transactions.amount) as total_interest,
                AVG(transactions.computed_rate) as avg_daily_rate,
                COUNT(*) as transaction_count
            ")
            ->groupByRaw($groupBy)
            ->orderBy('period')
            ->get();
    }
}
