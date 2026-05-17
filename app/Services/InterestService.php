<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;

/**
 * Interest Service - Enhanced Version
 *
 * Combines date-based tracking with validation,
 * APY calculation, and formatting.
 *
 * Stores rates as percentages (e.g., 0.027397 for 0.0274% daily = ~10% APY)
 */
class InterestService
{
    // ── Maximum allowed daily interest rate (100% APY) ────────────────────────
    const MAX_APY = 100.0;           // 100% per year
    const MAX_DAILY_RATE = 100.0 / 365;  // ≈ 0.274% per day

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 1: Date Tracking
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Get the last date interest was recorded for this account.
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
     * Check if interest can be recorded today (once per day limit).
     * Uses direct date comparison instead of cache.
     */
    public function canRecordTodayKey(Account $account): bool
    {
        $lastDate = $this->getLastInterestDate($account);
        if (!$lastDate) return true;
        return !$lastDate->isSameDay(now());
    }

    /**
     * Get count of skipped days since last interest recording.
     *
     * Returns:
     *   null = no previous interest OR already recorded today
     *   0    = next consecutive day (no days skipped)
     *   >0   = number of days skipped between last recording and today
     */
    public function getSkippedDaysCount(Account $account): ?int
    {
        $lastDate = $this->getLastInterestDate($account);
        if (!$lastDate) return null;

        $daysSinceLastRecording = $lastDate->diffInDays(now());

        if ($daysSinceLastRecording === 0) return null;  // Same day — already recorded
        if ($daysSinceLastRecording === 1) return 0;     // Next consecutive day — no skip

        return $daysSinceLastRecording - 1;  // Days actually skipped
    }

    /**
     * Get a user-friendly message about skipped days.
     * Example: "You skipped 3 days. Is the interest being recorded for the last 4 days?"
     */
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

    /**
     * Get date range information for the skipped period.
     *
     * Example return:
     * [
     *   'last_recorded' => 'May 12, 2025',
     *   'gap_start'     => 'May 13, 2025',
     *   'gap_end'       => 'May 15, 2025',
     *   'days_count'    => 3,    // days skipped
     *   'total_days'    => 4,    // total period including today
     * ]
     */
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
     *
     * - opening_balance = account balance at close of day before the interest date
     * - period_days     = extracted from description for multi-day periods, else 1
     *
     * Returns percentage rate (e.g., 0.027397 for 0.0274%/day ≈ 10% APY).
     * Returns null if opening balance is zero or negative.
     */
    public function computeDailyRate(Account $account, Transaction $interestTxn): ?float
    {
        $openingBalance = $this->computeOpeningBalance($account, $interestTxn->date);

        if (!$openingBalance || $openingBalance <= 0) {
            return null;
        }

        // Extract period length from description e.g. "(7-day period)"
        $days = $this->extractPeriodDays($interestTxn->description);

        // Return as percentage: e.g. 27.40 / 100,000 / 1 * 100 = 0.027400
        return round(($interestTxn->amount / $openingBalance / $days) * 100, 6);
    }

    /**
     * Compute the account balance at the close of the day BEFORE $date.
     *
     * Uses the same income/expense logic as updateBalance().
     * Excludes same-day and future transactions so interest is calculated
     * against the correct prior balance.
     */
    public function computeOpeningBalance(Account $account, $date): float
    {
        if ($date instanceof Carbon) {
            $date = $date->toDateString();
        }

        $result = Transaction::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereNull('transactions.deleted_at')
            ->whereDate('transactions.date', '<', $date)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("
                SUM(CASE WHEN categories.type IN ('income', 'liability') THEN transactions.amount ELSE 0 END) -
                SUM(CASE WHEN categories.type = 'expense'               THEN transactions.amount ELSE 0 END)
                AS net
            ")
            ->value('net');

        return (float) $account->initial_balance + (float) $result;
    }

    /**
     * Extract the number of days from an interest description.
     *
     * Parses patterns like:
     *   - "Interest earned (7-day period)" → 7
     *   - "Interest earned"               → 1
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

    /**
     * Validate an interest amount before recording.
     *
     * Checks:
     *   - Not negative
     *   - Not zero
     *   - Account has a positive balance
     *   - Implied rate doesn't exceed 100% APY
     *
     * Returns an array of error messages (empty if valid).
     *
     * FIX: Previously $impliedAPY was computed as a fraction (e.g. 0.10) and
     * compared to MAX_APY = 100.0, so the check never triggered. Now we
     * multiply by 100 to convert to a percentage before comparing.
     */
    public function validateInterestAmount(Account $account, float $amount, $date): array
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;

        $errors = [];

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

        // Compute as percentage to match MAX_APY (which is also a percentage).
        // daily fraction → daily % → annualised %
        // e.g. 27.40 / 100,000 = 0.000274 → × 100 = 0.0274% /day → × 365 = 10.00% APY
        $impliedDailyPct = ($amount / $openingBalance) * 100;   // percentage per day
        $impliedAPY      = $impliedDailyPct * 365;              // simple APY as percentage

        if ($impliedAPY > self::MAX_APY) {
            $impliedAPYRounded = round($impliedAPY, 2);
            $errors[] = "Interest rate ({$impliedAPYRounded}% APY) exceeds maximum allowed (" .
                self::MAX_APY . "% APY).";
        }

        return $errors;
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 4: Calculations
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Calculate Annual Percentage Yield (APY) from a daily rate percentage.
     *
     * Input : daily rate as a percentage  (e.g. 0.027397 for 0.0274%/day)
     * Output: APY as a percentage         (e.g. 10.00 for 10% APY)
     *
     * Uses simple (non-compounding) formula: daily_rate_pct × 365
     * For a more accurate compound APY: ((1 + daily_rate_pct/100)^365 - 1) * 100
     */
    public function calculateAPY(?float $dailyRatePercent): ?float
    {
        if ($dailyRatePercent === null) {
            return null;
        }
        return $dailyRatePercent * 365;
    }

    /**
     * Calculate compound APY from a daily rate percentage.
     *
     * More accurate than simple multiplication when the rate compounds daily.
     * Input : daily rate as a percentage  (e.g. 0.027397)
     * Output: compound APY as a percentage (e.g. 10.516)
     */
    public function calculateCompoundAPY(?float $dailyRatePercent): ?float
    {
        if ($dailyRatePercent === null) {
            return null;
        }
        return (pow(1 + ($dailyRatePercent / 100), 365) - 1) * 100;
    }

    /**
     * Check if a daily rate exceeds the maximum allowed (100% APY).
     */
    public function exceedsMaximumRate(float $dailyRatePercent): bool
    {
        $apy = $this->calculateAPY($dailyRatePercent);
        return $apy > self::MAX_APY;
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 5: Formatting
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Format daily rate for display.
     *
     * Example: 0.027397 → "0.0274%"
     * Null:    null     → "—"
     */
    public function formatDailyRate(?float $dailyRatePercent, int $decimals = 4): string
    {
        if ($dailyRatePercent === null) {
            return '—';
        }
        return number_format($dailyRatePercent, $decimals) . '%';
    }

    /**
     * Format APY for display.
     *
     * Example: 0.027397 daily → "10.00% APY"
     * Null:    null            → "—"
     */
    public function formatAPY(?float $dailyRatePercent, int $decimals = 2): string
    {
        if ($dailyRatePercent === null) {
            return '—';
        }
        $apy = $this->calculateAPY($dailyRatePercent);
        return number_format($apy, $decimals) . '%';
    }

    /**
     * Get a rate summary string for display.
     *
     * Example: "0.0274%/day (10.00% APY)"
     */
    public function getRateSummary(?float $dailyRatePercent): ?string
    {
        if ($dailyRatePercent === null) {
            return null;
        }
        $daily = $this->formatDailyRate($dailyRatePercent);
        $apy   = $this->formatAPY($dailyRatePercent);
        return "{$daily}/day ({$apy} APY)";
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION 6: Statistics
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Get average daily rate from a collection of interest transactions.
     *
     * Simple mean of computed_rate values (transactions without a rate are excluded).
     */
    public function getAverageDailyRate($transactions): ?float
    {
        if ($transactions->isEmpty()) {
            return null;
        }

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

    /**
     * Get interest statistics for a period.
     *
     * NOTE: This method is available for standalone use (e.g. APIs or reports).
     * The accounts.show view builds its own period queries directly in the
     * controller so that period/year filtering and label mapping are co-located.
     *
     * @param Account $account
     * @param string  $period 'daily' | 'weekly' | 'monthly' | 'yearly'
     * @param int|null $year  Year to filter by (defaults to current year)
     */
    public function getInterestStatsByPeriod(
        Account $account,
        string  $period = 'monthly',
        ?int    $year = null
    ) {
        $year = $year ?? now()->year;

        $query = $account->transactions()
            ->whereNull('deleted_at')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.name', 'Interest')
            ->whereYear('transactions.date', $year);

        $groupBy = match ($period) {
            'daily'   => 'DATE(transactions.date)',
            'weekly'  => 'WEEK(transactions.date)',
            'monthly' => 'MONTH(transactions.date)',
            'yearly'  => 'YEAR(transactions.date)',
        };

        return $query->selectRaw("
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
