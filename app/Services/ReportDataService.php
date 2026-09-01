<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\ClientFund;
use App\Models\ClientFundTransaction;
use App\Models\Loan;
use App\Models\LoanGiven;
use App\Models\LoanGivenPayment;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ReportDataService
{
    private const MIN_SALARY_AMOUNT_FOR_SAVINGS_RATE = 40000;
    private const SALARY_TO_SAVINGS_WINDOW_HOURS = 72;
    private const SAVINGS_REVERSAL_WINDOW_DAYS = 8;
    /**
     * Category names that represent loan mechanics, balance corrections, or
     * client-fund passthrough — never the user's own real income or spending.
     * This is the single canonical list; DashboardController, BudgetController,
     * and ReportsController all pull from here via the accessor below instead
     * of maintaining their own copies. Previously each controller hardcoded
     * its own version of this list and they drifted apart — e.g. Dashboard
     * omitted 'Friend Loan Given' from its expense exclusions (so lending
     * money to someone spiked "This Month" spending there but nowhere else)
     * and omitted 'Loan Interest' from income (so closed loan-given interest
     * got folded into regular income there but nowhere else). Any category
     * added here automatically stops leaking into all four surfaces at once.
     */
    public const NON_SPENDING_CATEGORY_NAMES = [
        'Loan Disbursement',
        'Loan Receipt',
        'Balance Adjustment',
        'Client Funds',
        'Friend Loan Given',   // disbursement — not a real expense
        'Loan Recovery',       // principal returning — not real income
        'Loan Interest',       // handled as its own Interest Income section
    ];

    /**
     * Accessor so callers don't reference the const directly (keeps the
     * option open to make this dynamic/DB-driven later without breaking
     * every call site).
     */
    public static function nonSpendingCategoryNames(): array
    {
        return self::NON_SPENDING_CATEGORY_NAMES;
    }

    /**
     * Generate annual report for the prior full year
     */
    public function generateAnnualReport(User $user): array
    {
        $year = now()->subYear()->year;
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        Log::info('Generating annual report for user: ' . $user->email . ' | year: ' . $year);

        $report = $this->generateReport($user, $startDate, $endDate, 'annual');

        // Load ALL budget records for the year in one query
        $allBudgets = Budget::where('user_id', $user->id)
            ->where('year', $year)
            ->get()
            ->groupBy('month')
            ->map(fn($group) => $group->keyBy('category_id'));

        // Load all lookback transactions once (3 months before Jan 1) — fixes N+1
        $lookbackStart = Carbon::create($year, 1, 1)->startOfDay()->subMonths(3)->startOfMonth();
        $lookbackEnd = Carbon::create($year, 1, 1)->startOfDay()->subDay()->endOfDay();
        $lookbackTx = $this->getFilteredTransactions($user, $lookbackStart, $lookbackEnd);

        $monthlyBreakdown = [];
        for ($month = 1; $month <= 12; $month++) {
            $mStart = Carbon::create($year, $month, 1)->startOfDay();
            $mEnd = $mStart->copy()->endOfMonth()->endOfDay();

            $monthTransactions = $this->getFilteredTransactions($user, $mStart, $mEnd);

            $mIncome = $monthTransactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
            $mExpenses = $monthTransactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
            $mNet = $mIncome - $mExpenses;

            // Stored budgets for this month, keyed by category_id
            $monthBudgets = $allBudgets->get($month, collect());

            // Actual spend per category
            $actualByCat = $monthTransactions
                ->filter(fn($t) => $t->category->type === 'expense')
                ->groupBy('category_id')
                ->map(fn($group) => [
                    'name' => $group->first()->category->name,
                    'spent' => $group->sum('amount'),
                ]);

            // Use pre-loaded lookback transactions instead of re-querying
            $baselines = $this->buildRollingBaselines($user, $mStart, lookbackMonths: 3, preloadedTx: $lookbackTx);

            $allCatIds = $actualByCat->keys()
                ->merge($monthBudgets->keys())
                ->unique();

            $categoryPerformance = [];
            $totalBudgeted = 0;
            $totalSpent = 0;
            $catsOver = 0;
            $catsUnder = 0;

            foreach ($allCatIds as $catId) {
                $spent = $actualByCat[$catId]['spent'] ?? 0;
                if ($spent === 0) continue;

                $catName = $actualByCat[$catId]['name']
                    ?? $baselines[$catId]['name']
                    ?? 'Unknown';
                $hasBudget = $monthBudgets->has($catId);
                $budgeted = $hasBudget
                    ? (float)$monthBudgets[$catId]->amount
                    : ($baselines[$catId]['baseline'] ?? $spent);

                $remaining = $budgeted - $spent;
                $percentage = $budgeted > 0 ? ($spent / $budgeted) * 100 : 100;

                $categoryPerformance[] = [
                    'category' => $catName,
                    'budgeted' => round($budgeted, 2),
                    'spent' => round($spent, 2),
                    'remaining' => round($remaining, 2),
                    'percentage' => round($percentage, 1),
                    'has_budget' => $hasBudget,
                    'months_used' => $baselines[$catId]['months_used'] ?? 0,
                    'is_new' => ($baselines[$catId]['months_used'] ?? 0) === 0,
                ];

                $totalBudgeted += $budgeted;
                $totalSpent += $spent;
                $percentage >= 100 ? $catsOver++ : $catsUnder++;
            }

            usort($categoryPerformance, fn($a, $b) => $b['percentage'] <=> $a['percentage']);

            // Income budgets for the month
            $mIncomeBudgeted = $monthBudgets
                ->filter(fn($b) => optional($b->category)->type === 'income')
                ->sum('amount');

            $monthlyBreakdown[] = [
                'month' => $mStart->format('F Y'),
                'month_short' => $mStart->format('M'),
                'income' => $mIncome,
                'expenses' => $mExpenses,
                'net_flow' => $mNet,
                'savings_rate' => $mIncome > 0 ? ($mNet / $mIncome) * 100 : 0,
                'transaction_count' => $monthTransactions->count(),
                'budgeted_expenses' => round($totalBudgeted, 2),
                'budgeted_income' => round($mIncomeBudgeted, 2),
                'budget_variance' => round($totalBudgeted - $totalSpent, 2),
                'cats_over_budget' => $catsOver,
                'cats_under_budget' => $catsUnder,
                'category_performance' => $categoryPerformance,
            ];
        }

        $collection = collect($monthlyBreakdown);
        $bestMonth = $collection->sortByDesc('net_flow')->first();
        $worstMonth = $collection->sortBy('net_flow')->first();
        $profitMonths = $collection->where('net_flow', '>', 0)->count();

        // Annual budget summary across all months
        $annualBudgetedExpenses = $collection->sum('budgeted_expenses');
        $annualActualExpenses = $collection->sum('expenses');
        $annualBudgetVariance = $annualBudgetedExpenses - $annualActualExpenses;
        $monthsOverBudget = $collection->where('cats_over_budget', '>', 0)->count();

        $loansPaidInYear = $this->getLoanPaymentsInPeriod($user, $startDate, $endDate);
        $loansRepaidDuringYear = $this->getLoansRepaidInPeriod($user, $startDate, $endDate);

        $priorYearStart = Carbon::create($year - 1, 1, 1)->startOfDay();
        $priorYearEnd = Carbon::create($year - 1, 12, 31)->endOfDay();
        $priorYearIncome = $this->getFilteredTransactions($user, $priorYearStart, $priorYearEnd)
            ->filter(fn($t) => $t->category->type === 'income')
            ->sum('amount');

        $report['monthly_breakdown'] = $monthlyBreakdown;
        $report['best_month'] = $bestMonth;
        $report['worst_month'] = $worstMonth;
        $report['profitable_months'] = $profitMonths;
        $report['loans_paid_in_period'] = $loansPaidInYear;
        $report['loans_repaid_in_period'] = $loansRepaidDuringYear;
        $report['prior_period_income'] = $priorYearIncome;
        $report['income_trend'] = $priorYearIncome > 0
            ? (($report['income'] - $priorYearIncome) / $priorYearIncome) * 100
            : null;
        $report['period_type'] = 'annual';
        $report['year'] = $year;
        $report['salary_savings_rate'] = $this->getSalarySavingsRate($user, $startDate, $endDate);
        $report['annual_budgeted_expenses'] = $annualBudgetedExpenses;
        $report['annual_budget_variance'] = $annualBudgetVariance;
        $report['months_over_budget'] = $monthsOverBudget;

        return $report;
    }

    /**
     * Generate monthly report for the prior month
     */
    public function generateMonthlyReport(User $user): array
    {
        $lastMonth = now()->subMonthNoOverflow();
        $startDate = $lastMonth->copy()->startOfMonth();
        $endDate = $lastMonth->copy()->endOfMonth();

        $report = $this->generateReport($user, $startDate, $endDate, 'monthly');

        $report['loans_paid_in_period'] = $this->getLoanPaymentsInPeriod($user, $startDate, $endDate);
        $report['loans_repaid_in_period'] = $this->getLoansRepaidInPeriod($user, $startDate, $endDate);

        $prevStart = $startDate->copy()->subMonth()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();

        $priorMonthIncome = $this->getFilteredTransactions($user, $prevStart, $prevEnd)
            ->filter(fn($t) => $t->category->type === 'income')
            ->sum('amount');

        $report['prior_period_income'] = $priorMonthIncome;
        $report['income_trend'] = $priorMonthIncome > 0
            ? (($report['income'] - $priorMonthIncome) / $priorMonthIncome) * 100
            : null;

        $budgetPerf = collect($report['budget_performance']);
        $report['budgets_under'] = $budgetPerf->where('percentage', '<=', 100)->count();
        $report['budgets_over'] = $budgetPerf->where('percentage', '>', 100)->count();
        $report['budgets_total'] = $budgetPerf->count();

        $report['salary_savings_rate'] = $this->getSalarySavingsRate($user, $startDate, $endDate);

        return $report;
    }

    /**
     * Generate custom period report
     */
    public function generateCustomReport(User $user, Carbon $startDate, Carbon $endDate): array
    {
        return $this->generateReport($user, $startDate, $endDate, 'custom');
    }

    /**
     * Get filtered transactions — excludes client fund pass-throughs and loan-related entries.
     * Matches the filtering logic used in BudgetController.
     */
    private function getFilteredTransactions(User $user, Carbon $startDate, Carbon $endDate)
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->where(function ($q) {
                $q->whereNull('payment_method')
                    ->orWhere(function ($q2) {
                        $q2->where('payment_method', '!=', 'Client Fund')
                            ->where('payment_method', '!=', 'Client Commission');
                    })
                    ->orWhere(function ($q2) {
                        $q2->where('payment_method', 'Client Commission')
                            ->whereHas('category', fn($c) => $c->where('type', 'income'));
                    });
            })
            ->whereHas('category', function ($q) {
                $q->whereIn('type', ['income', 'expense'])
                    ->whereNotIn('name', self::NON_SPENDING_CATEGORY_NAMES);
            })
            ->with(['category', 'account'])
            ->get();
    }

    /**
     * Build a rolling-average budget baseline for the given month.
     *
     * For each expense category that has any spend in the report period we look
     * at the three full calendar months immediately before $startDate and take
     * the average monthly spend as the "budget" target.  If a category has
     * fewer than three prior months of data we still use whatever months exist
     * (minimum 1), so a new category is never silently excluded.
     *
     * Returns an array keyed by category_id:
     *   [ category_id => ['name' => string, 'baseline' => float, 'months_used' => int] ]
     */
    private function buildRollingBaselines(User $user, Carbon $startDate, int $lookbackMonths = 3, ?Collection $preloadedTx = null): array
    {
        $baselines = [];

        for ($i = 1; $i <= $lookbackMonths; $i++) {
            $mStart = $startDate->copy()->subMonths($i)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth();

            if ($preloadedTx !== null) {
                $monthTx = $preloadedTx->filter(function ($t) use ($mStart, $mEnd) {
                    $date = Carbon::parse($t->date);
                    return $date->between($mStart, $mEnd) && $t->category->type === 'expense';
                });
            } else {
                $monthTx = $this->getFilteredTransactions($user, $mStart, $mEnd)
                    ->filter(fn($t) => $t->category->type === 'expense');
            }

            foreach ($monthTx->groupBy('category_id') as $catId => $group) {
                if (!isset($baselines[$catId])) {
                    $baselines[$catId] = [
                        'name' => $group->first()->category->name,
                        'total' => 0.0,
                        'months_used' => 0,
                    ];
                }
                $baselines[$catId]['total'] += $group->sum('amount');
                $baselines[$catId]['months_used'] += 1;
            }
        }

        foreach ($baselines as $catId => &$data) {
            $data['baseline'] = $data['months_used'] > 0
                ? $data['total'] / $data['months_used']
                : 0.0;
            unset($data['total']);
        }
        unset($data);

        return $baselines;
    }

    /**
     * Core report generation logic
     */
    private function generateReport(User $user, Carbon $startDate, Carbon $endDate, string $type): array
    {
        $accounts = Account::where('user_id', $user->id)->where('is_active', true)->get();

        // Reconstruct each account's balance as of $endDate, not today
        $accountsAsAt = $accounts->map(function ($account) use ($user, $endDate) {
            $rawBalance = $this->getAccountBalanceAsAt($account, $endDate);
            $clientFundsInAccount = $this->getClientFundsBalanceAsAt($user, $endDate, $account->id);

            // Positive only when this account's tagged client funds exceed what's
            // actually sitting in it — i.e. client money was borrowed against
            // without a tracked Transfer recording the movement (see
            // ClientFundController::recordBorrowed / reconcileBorrowed, which are
            // bookkeeping-only and don't create a Transfer). Previously this was
            // only Log::warning()'d and the account's display balance silently
            // clamped to 0 — invisible to the user, and if it clamped to exactly
            // 0 the account also vanished entirely from the report's Account
            // Overview table (see the blade templates' zero-balance filter).
            // Recording it on the account lets the report surface it instead.
            //
            // NOTE: this remains a per-account DISPLAY diagnostic only — it is
            // no longer used in the net-worth calculation below. It's a proxy
            // (raw balance vs. reconstructed "true owed"), and as such it's
            // blind to any borrow recorded via recordBorrowed()/reconcileBorrowed()
            // that was never corrected with an offsetting Balance Adjustment —
            // those calls create no Transaction at all. Net worth now uses the
            // direct ledger figure from getUnreturnedBorrowedAsAt() instead.
            $shortfall = max(0, $clientFundsInAccount - $rawBalance);

            if ($shortfall > 0) {
                Log::warning('ReportDataService: client funds exceed reconstructed account balance', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'as_at_date' => $endDate->toDateString(),
                    'raw_balance' => $rawBalance,
                    'client_funds' => $clientFundsInAccount,
                    'shortfall' => -$shortfall,
                ]);
            }

            $account->raw_balance_as_at = $rawBalance;
            $account->client_funds_as_at = $clientFundsInAccount;
            $account->balance_as_at = max(0, $rawBalance - $clientFundsInAccount);
            $account->client_fund_shortfall = $shortfall;
            return $account;
        });

// balance_as_at is clamped per-account for display (an account can't show
// negative cash), which can silently drop money from a naive sum when one
// account's client funds exceed its own balance — exactly the Sanlam MMF
// scenario (client funds tagged to an account whose real balance is now 0
// because the underlying cash moved through an untracked path). total_balance
// must therefore never be a sum of the clamped display values; it's the
// pooled raw balance minus pooled client funds, computed once, unaffected
// by any single account's shortfall.
        $totalBalanceUnclamped = $accountsAsAt->sum('raw_balance_as_at') - $accountsAsAt->sum('client_funds_as_at');
        $totalBalance = max(0, $totalBalanceUnclamped);

        // Which accounts (if any) are carrying a shortfall, and how much in
        // total — surfaced to the report templates so the user can see WHERE
        // the gap between "Total Assets" and the sum of listed accounts comes
        // from, instead of it only ever reaching a server log. This is a
        // per-account display diagnostic only — see the note above on why it
        // no longer feeds into net worth.
        $accountsWithShortfall = $accountsAsAt->where('client_fund_shortfall', '>', 0);
        $totalClientFundShortfall = $accountsWithShortfall->sum('client_fund_shortfall');

        $activeLoans = Loan::where('user_id', $user->id)->where('status', 'active')->with('account')->get();
        $totalLoanBalance = $activeLoans->sum('balance');
        $activeLoansGiven = LoanGiven::where('user_id', $user->id)->where('status', 'active')->get();
        $totalLoansGivenBalance = $activeLoansGiven->sum('balance');

        // Historical client funds balance — what was still outstanding as of $endDate,
        // not what's outstanding today. This is the GLOBAL figure across every
        // account (mpesa/bank/savings — see ClientFundController@store, client
        // funds aren't savings-only) and is what the "excludes KES X" footnote
        // in the report templates uses.
        $totalClientFunds = $this->getClientFundsBalanceAsAt($user, $endDate);

        // Historical savings balance — what was actually in savings at period end, not today
        $savingsBalance = $this->getSavingsBalanceAsAt($user, $endDate);

        // For working out how much of the SAVINGS balance is actually owned,
        // we need client funds specifically parked in savings accounts — NOT
        // $totalClientFunds above, which spans every account type. Subtracting
        // the global figure here was wrongly pulling money out of savings that
        // was never there to begin with (e.g. client funds sitting in M-Pesa),
        // understating owned savings any time most client money lives outside
        // savings accounts.
        //
        // NOTE: $ownedSavings is now a DISPLAY-ONLY figure for the "Savings
        // Accounts" line in the report banners. Savings accounts are already
        // included in $totalBalanceUnclamped (which is used directly in net
        // worth below), so $ownedSavings must never also be added into net
        // worth separately — that would double-count savings-account cash.
        $savingsAccountIds = Account::where('user_id', $user->id)
            ->where('type', 'savings')
            ->where('is_active', true)
            ->pluck('id');

        $clientFundsInSavings = $savingsAccountIds->isEmpty()
            ? 0.0
            : $savingsAccountIds->sum(fn($id) => $this->getClientFundsBalanceAsAt($user, $endDate, $id));

        $ownedSavings = max(0, $savingsBalance - $clientFundsInSavings);

        // Real, direct ledger figure for money borrowed from client funds for
        // personal use and not yet returned — mirrors
        // ClientFundController::index()'s $summary['total_borrowed'], but
        // as-at $endDate rather than live. This replaces the old per-account
        // shortfall proxy for net-worth purposes (see notes above on why that
        // proxy under-counts).
        $totalUnreturnedBorrowed = $this->getUnreturnedBorrowedAsAt($user, $endDate);

        // Net worth = pooled account cash (unclamped, so a genuine deficit
        // isn't hidden) + money owed back to the user (Outstanding Loans
        // Given, gross) - money the user owes on active loans - money the
        // user has borrowed from client funds and not yet returned.
        // No outer max(0, ...): a person can legitimately owe more than they
        // own, and clamping here would hide that.
        $netWorth = $totalBalanceUnclamped + $totalLoansGivenBalance - $totalLoanBalance;


        // --- Transactions ---
        $transactions = $this->getFilteredTransactions($user, $startDate, $endDate)
            ->sortBy(fn($t) => $t->date)
            ->reverse()
            ->values();

        $income = $transactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
        $expenses = $transactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
        $netFlow = $income - $expenses;

        $topCategories = $transactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->groupBy('category_id')
            ->map(fn($group) => [
                'category' => $group->first()->category->name,
                'amount' => $group->sum('amount'),
                'count' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->take(5)
            ->values();

        $largestTransactions = $transactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->sortByDesc('amount')
            ->take(5)
            ->values();

        $dailySpending = $transactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->groupBy(fn($t) => Carbon::parse($t->date)->format('Y-m-d'))
            ->map(fn($group, $date) => [
                'date' => Carbon::parse($date)->format('M d'),
                'amount' => $group->sum('amount'),
            ])
            ->sortKeys()
            ->values();

        // --- Budget Performance (monthly only) ---
        $budgetPerformance = [];

        if ($type === 'monthly') {
            $reportMonth = $startDate->month;
            $reportYear = $startDate->year;

            $storedBudgets = Budget::where('user_id', $user->id)
                ->where('year', $reportYear)
                ->where('month', $reportMonth)
                ->get()
                ->keyBy('category_id');

            $baselines = $this->buildRollingBaselines($user, $startDate, lookbackMonths: 3);

            $actualByCat = $transactions
                ->filter(fn($t) => $t->category->type === 'expense')
                ->groupBy('category_id')
                ->map(fn($group) => [
                    'name' => $group->first()->category->name,
                    'spent' => $group->sum('amount'),
                ]);

            $allCategoryIds = $actualByCat->keys()
                ->merge($storedBudgets->keys())
                ->unique();

            foreach ($allCategoryIds as $catId) {
                $spent = $actualByCat[$catId]['spent'] ?? 0;
                if ($spent === 0) continue;

                $catName = $actualByCat[$catId]['name']
                    ?? $storedBudgets[$catId]?->category?->name
                    ?? $baselines[$catId]['name']
                    ?? 'Unknown';

                $hasBudget = isset($storedBudgets[$catId]);
                $baseline = $hasBudget
                    ? (float)$storedBudgets[$catId]->amount
                    : ($baselines[$catId]['baseline'] ?? $spent);
                $monthsUsed = $baselines[$catId]['months_used'] ?? 0;
                $remaining = $baseline - $spent;
                $percentage = $baseline > 0 ? ($spent / $baseline) * 100 : ($spent > 0 ? 100 : 0);

                $budgetPerformance[] = [
                    'category' => $catName,
                    'budgeted' => round($baseline, 2),
                    'spent' => round($spent, 2),
                    'remaining' => round($remaining, 2),
                    'percentage' => round($percentage, 1),
                    'months_used' => $monthsUsed,
                    'is_new' => $monthsUsed === 0,
                    'has_budget' => $hasBudget,
                ];
            }

            usort($budgetPerformance, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
        }

        $insights = $this->generateInsights($user, $transactions, $startDate, $endDate, $type);

        $investmentIncome = $this->getInvestmentIncome($user, $startDate, $endDate);
        $loansGivenActivity = $this->getLoansGivenActivityInPeriod($user, $startDate, $endDate);
        $loanGivenInterestIncome = $this->getLoanGivenInterestIncome($user, $startDate, $endDate);
        $totalInterestIncome = (float)$investmentIncome['total'] + $loanGivenInterestIncome;

        return [
            'period_type' => $type,
            'start_date' => $startDate->format('M d, Y'),
            'end_date' => $endDate->format('M d, Y'),
            'user' => $user,
            'accounts' => $accountsAsAt,
            'total_balance' => $totalBalance,
            'savings_balance' => $ownedSavings,
            'total_loans' => $totalLoanBalance,
            'total_loans_given' => $totalLoansGivenBalance,
            'total_client_funds' => $totalClientFunds,
            'account_client_fund_shortfalls' => $accountsWithShortfall->map(fn($a) => [
                'name' => $a->name,
                'shortfall' => (float)$a->client_fund_shortfall,
            ])->values(),
            'total_client_fund_shortfall' => (float)$totalClientFundShortfall,
            'total_unreturned_borrowed' => (float)$totalUnreturnedBorrowed,
            'net_worth' => $netWorth,
            'transactions' => match ($type) {
                'annual' => $transactions->take(50),
                'monthly' => $transactions->take(30),
                default => $transactions->take(25),
            },
            'transaction_count' => $transactions->count(),
            'income' => $income,
            'expenses' => $expenses,
            'net_flow' => $netFlow,
            'savings_rate' => $income > 0 ? ($netFlow / $income) * 100 : 0,
            'top_categories' => $topCategories,
            'largest_transactions' => $largestTransactions,
            'daily_spending' => $dailySpending,
            'active_loans' => $activeLoans,
            'active_loans_given' => $activeLoansGiven,
            'loans_given_activity' => $loansGivenActivity,
            'budget_performance' => $budgetPerformance,
            'insights' => $insights,
            'investment_income' => $investmentIncome,
            'total_interest_income' => $totalInterestIncome,
            'loan_given_interest_income' => $loanGivenInterestIncome,

        ];
    }

    private function getLoanGivenInterestIncome(User $user, Carbon $startDate, Carbon $endDate): float
    {
        return (float)LoanGiven::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereBetween('repaid_date', [$startDate, $endDate])
            ->sum('interest_amount');
    }

    /**
     * Calculate interest earned on savings/investment accounts during the period,
     * broken down per account. Interest is identified by category name 'Interest',
     * matching the convention used in StatementDataService::computeBalanceAt().
     *
     * Returns:
     *   [
     *       'total'    => float,
     *       'accounts' => [ ['name' => string, 'amount' => float], ... ]  // only accounts with amount > 0
     *   ]
     */
    private function getInvestmentIncome(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $savingsAccounts = Account::where('user_id', $user->id)
            ->where('type', 'savings')
            ->where('is_active', true)
            ->get();

        if ($savingsAccounts->isEmpty()) {
            return ['total' => 0.0, 'accounts' => []];
        }

        $interestByAccount = Transaction::whereIn('account_id', $savingsAccounts->pluck('id'))
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->whereHas('category', fn($q) => $q->where('name', 'Interest'))
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        $accounts = $savingsAccounts
            ->map(fn($account) => [
                'name' => $account->name,
                'amount' => (float)($interestByAccount[$account->id] ?? 0),
            ])
            ->filter(fn($a) => $a['amount'] > 0)
            ->sortByDesc('amount')
            ->values()
            ->all();

        return [
            'total' => (float)$interestByAccount->sum(),
            'accounts' => $accounts,
        ];
    }

    /**
     * Calculate what a savings account's balance was at a specific point in time
     * by taking current_balance and reversing transactions that occurred after $asAtDate.
     */
    private function getSavingsBalanceAsAt(User $user, Carbon $asAtDate): float
    {
        $savingsAccounts = Account::where('user_id', $user->id)
            ->where('type', 'savings')
            ->where('is_active', true)
            ->get();

        if ($savingsAccounts->isEmpty()) {
            return 0.0;
        }

        $savingsAccountIds = $savingsAccounts->pluck('id');
        $currentSavingsTotal = $savingsAccounts->sum('current_balance');

        // Pull all transactions on these accounts after asAtDate in one query
        $txAfter = Transaction::where('user_id', $user->id)
            ->whereIn('account_id', $savingsAccountIds)
            ->where('date', '>', $asAtDate->toDateString())
            ->with('category')
            ->get();

        // Income transactions, "Client Funds" liability transactions, AND
        // "Loan Receipt" liability transactions all increase the account balance
        // in Account::updateBalance() (loan_disbursements is added unconditionally
        // there, not gated by category type), so all three must be reversed the
        // same way here — matching updateBalance()'s forward logic exactly.
        $incomingAfter = $txAfter
            ->filter(fn($t) => $t->category->type === 'income'
                || $t->category->name === 'Client Funds'
                || $t->category->name === 'Loan Receipt')
            ->sum('amount');

        $outgoingAfter = $txAfter
            ->filter(fn($t) => $t->category->type === 'expense')
            ->sum('amount');

        // "Balance Adjustment" is added into current_balance unconditionally in
        // updateBalance() (regardless of category type, via balance_adjustments),
        // separate from the income/expense buckets above — so it needs its own
        // reversal term with its own (possibly negative) signed amount.
        $balanceAdjustmentsAfter = $txAfter
            ->filter(fn($t) => $t->category->name === 'Balance Adjustment')
            ->sum('amount');

        // Transfers INTO savings accounts after period end (subtract to reverse)
        $transfersInAfter = Transfer::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereIn('to_account_id', $savingsAccountIds)
            ->where('date', '>', $asAtDate->toDateString())
            ->sum('amount');

        // Transfers OUT OF savings accounts after period end (add back to reverse)
        $transfersOutAfter = Transfer::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereIn('from_account_id', $savingsAccountIds)
            ->where('date', '>', $asAtDate->toDateString())
            ->sum('amount');

        $balanceAsAt = $currentSavingsTotal
            - $incomingAfter
            + $outgoingAfter
            - $transfersInAfter
            + $transfersOutAfter
            - $balanceAdjustmentsAfter;

        return max(0, $balanceAsAt);
    }

    /**
     * Calculate what a single account's balance was at a specific point in time
     * by taking current_balance and reversing transactions/transfers that
     * occurred after $asAtDate.
     */
    private function getAccountBalanceAsAt(Account $account, Carbon $asAtDate): float
    {
        $currentBalance = (float)$account->current_balance;

        $txAfter = Transaction::where('user_id', $account->user_id)
            ->where('account_id', $account->id)
            ->where('date', '>', $asAtDate->toDateString())
            ->with('category')
            ->get();

        // See getSavingsBalanceAsAt() above — must mirror what
        // Account::updateBalance() adds forward, including 'Loan Receipt'
        // (liability type, previously missing here) so a loan disbursed after
        // $asAtDate doesn't stay baked into the reconstructed historical balance.
        $incomingAfter = $txAfter
            ->filter(fn($t) => $t->category->type === 'income'
                || $t->category->name === 'Client Funds'
                || $t->category->name === 'Loan Receipt')
            ->sum('amount');

        $outgoingAfter = $txAfter
            ->filter(fn($t) => $t->category->type === 'expense')
            ->sum('amount');

        // See getSavingsBalanceAsAt() above — 'Balance Adjustment' is added
        // unconditionally in updateBalance(), independent of category type,
        // and needs the same unconditional reversal here (previously missing).
        $balanceAdjustmentsAfter = $txAfter
            ->filter(fn($t) => $t->category->name === 'Balance Adjustment')
            ->sum('amount');

        $transfersInAfter = Transfer::withoutGlobalScopes()
            ->where('user_id', $account->user_id)
            ->where('to_account_id', $account->id)
            ->where('date', '>', $asAtDate->toDateString())
            ->sum('amount');

        $transfersOutAfter = Transfer::withoutGlobalScopes()
            ->where('user_id', $account->user_id)
            ->where('from_account_id', $account->id)
            ->where('date', '>', $asAtDate->toDateString())
            ->sum('amount');

        $balanceAsAt = $currentBalance
            - $incomingAfter
            + $outgoingAfter
            - $transfersInAfter
            + $transfersOutAfter
            - $balanceAdjustmentsAfter;

        return $balanceAsAt; // no max(0,...) here — non-savings accounts can legitimately be negative
    }


    /**
     * Get loan payment transactions in a period
     */
    private function getLoanPaymentsInPeriod(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // Source from LoanPayment (the authoritative record created by
        // LoanController::recordPayment()) rather than matching transactions
        // by category name. A transaction tagged "Loan Repayment" is not
        // necessarily tied to a tracked Loan — e.g. a manual/duplicate entry,
        // or one whose transaction date drifted from the loan's actual
        // payment_date — and matching by name alone caused this section to
        // report repayments that don't correspond to anything on the Loans
        // page.
        $payments = LoanPayment::where('user_id', $user->id)
            ->whereBetween('payment_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->with('loan')
            ->get();

        return [
            'count' => $payments->count(),
            'total' => $payments->sum('amount'),
            'items' => $payments->map(fn($p) => [
                'date' => Carbon::parse($p->payment_date)->format('M d, Y'),
                'description' => 'Repayment to ' . ($p->loan->source ?? 'loan'),
                'amount' => $p->amount,
            ])->values()->toArray(),
        ];
    }

    /**
     * Get loans that were completely repaid during the period
     */
    private function getLoansRepaidInPeriod(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $repaidLoans = Loan::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereBetween('repaid_date', [$startDate, $endDate])
            ->get();

        return [
            'count' => $repaidLoans->count(),
            'total' => $repaidLoans->sum('total_amount'),
            'principal_total' => $repaidLoans->sum('principal_amount'),
            'items' => $repaidLoans->map(fn($loan) => [
                'source' => $loan->source,
                'principal' => $loan->principal_amount,
                'total' => $loan->total_amount,
                'repaid_date' => Carbon::parse($loan->repaid_date)->format('M d, Y'),
            ])->values()->toArray(),
        ];
    }

    /**
     * Generate actionable insights from transaction data
     */
    private function generateInsights(User $user, $transactions, Carbon $startDate, Carbon $endDate, string $type): array
    {
        $insights = [];

        $days = $startDate->diffInDays($endDate) + 1;
        $totalExpenses = $transactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
        $income = $transactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
        $avgDaily = $days > 0 ? $totalExpenses / $days : 0;

        $insights[] = [
            'icon' => '📊',
            'title' => 'Average Daily Spending',
            'value' => 'KES ' . number_format($avgDaily, 0),
            'description' => 'You spent an average of KES ' . number_format($avgDaily, 0) . ' per day',
        ];

        $periodLabel = match ($type) {
            'annual' => 'year',
            'monthly' => 'month',
            default => 'period',
        };

        if ($type === 'annual') {
            $prevStart = $startDate->copy()->subYear();
            $prevEnd   = $endDate->copy()->subYear();
        } else {
            $prevStart = $startDate->copy()->subMonthNoOverflow();
            $prevEnd   = $endDate->copy()->subMonthNoOverflow();
        }

        $prevTransactions = $this->getFilteredTransactions($user, $prevStart, $prevEnd);
        $prevExpenses = $prevTransactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');

        $change = $totalExpenses - $prevExpenses;
        $changePercent = $prevExpenses > 0 ? (($change / $prevExpenses) * 100) : 0;

        if ($change > 0) {
            $insights[] = [
                'icon' => '📈',
                'title' => 'Spending Increased',
                'value' => '+' . number_format($changePercent, 1) . '%',
                'description' => 'You spent KES ' . number_format($change, 0) . ' more than last ' . $periodLabel,
                'trend' => 'up',
            ];
        } elseif ($change < 0) {
            $insights[] = [
                'icon' => '📉',
                'title' => 'Spending Decreased',
                'value' => number_format($changePercent, 1) . '%',
                'description' => 'You spent KES ' . number_format(abs($change), 0) . ' less than last ' . $periodLabel,
                'trend' => 'down',
            ];
        }

        $biggestExpense = $transactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->sortByDesc('amount')
            ->first();

        if ($biggestExpense) {
            $insights[] = [
                'icon' => '💸',
                'title' => 'Biggest Expense',
                'value' => 'KES ' . number_format($biggestExpense->amount, 0),
                'description' => $biggestExpense->description . ' (' . $biggestExpense->category->name . ')',
            ];
        }

        if ($income > 0) {
            $savingsRate = (($income - $totalExpenses) / $income) * 100;
            $insights[] = [
                'icon' => $savingsRate > 20 ? '🎯' : '⚠️',
                'value' => number_format($savingsRate, 1) . '%',
                'title' => 'Surplus Rate',
                'description' => $savingsRate > 20
                    ? "Great! You're generating a strong surplus"
                    : 'Consider reducing expenses to improve your surplus rate',
            ];
        }

        return $insights;
    }


    public function getSalarySavingsRate(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $salaryTransactions = Transaction::where('user_id', $user->id)
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->whereHas('category', fn($q) => $q->where('name', 'like', '%salary%'))
            ->where('amount', '>=', self::MIN_SALARY_AMOUNT_FOR_SAVINGS_RATE)
            ->with(['category', 'account'])
            ->orderBy('date')
            ->get();

        if ($salaryTransactions->isEmpty()) {
            return [];
        }

        $savingsAccountIds = Account::where('user_id', $user->id)
            ->where('type', 'savings')
            ->where('is_active', true)
            ->pluck('id');

        $results = [];

        foreach ($salaryTransactions as $salary) {
            $salaryDate = Carbon::parse($salary->date);
            $windowEnd = $salaryDate->copy()->addHours(self::SALARY_TO_SAVINGS_WINDOW_HOURS);

            // Client-fund transfers are excluded — that money was never the
            // user's own salary savings, so it shouldn't count as "saved".
            $transferredToSavings = Transfer::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereIn('to_account_id', $savingsAccountIds)
                ->where('is_client_fund', false)
                ->whereBetween('date', [
                    $salaryDate->toDateTimeString(),
                    $windowEnd->toDateTimeString(),
                ])
                ->sum('amount');

            // Net out any money pulled back OUT of savings within a wider window
            // (8 days from the salary date) — a same-week reversal means the
            // salary was never really "saved". Client-fund withdrawals are
            // excluded too — pulling a client's money back out isn't the user
            // reversing their own savings decision.
            $reversalWindowEnd = $salaryDate->copy()->addDays(self::SAVINGS_REVERSAL_WINDOW_DAYS);

            $transferredFromSavings = Transfer::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereIn('from_account_id', $savingsAccountIds)
                ->where('is_client_fund', false)
                ->whereBetween('date', [
                    $salaryDate->toDateTimeString(),
                    $reversalWindowEnd->toDateTimeString(),
                ])
                ->sum('amount');

            $netSaved = max(0, $transferredToSavings - $transferredFromSavings);

            $results[] = [
                'salary_date' => $salaryDate->format('M d, Y'),
                'salary_amount' => (float)$salary->amount,
                'saved_amount' => (float)$netSaved,
                'gross_saved_amount' => (float)$transferredToSavings,
                'reversed_amount' => (float)$transferredFromSavings,
                'savings_percentage' => $salary->amount > 0
                    ? round(($netSaved / $salary->amount) * 100, 1)
                    : 0,
            ];
        }

        return $results;
    }

    /**
     * Public summary of interest income for an arbitrary period — savings
     * account interest plus interest earned on closed loans given, broken
     * out and totaled. Used by screens (e.g. Reports index) that want this
     * figure without generating a full report via generateReport().
     *
     * Returns:
     *   [
     *       'total'                => float,
     *       'savings_interest'     => float,
     *       'loan_given_interest'  => float,
     *   ]
     */
    public function getInterestIncomeSummary(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $savingsInterest = (float)$this->getInvestmentIncome($user, $startDate, $endDate)['total'];
        $loanGivenInterest = $this->getLoanGivenInterestIncome($user, $startDate, $endDate);

        return [
            'total' => $savingsInterest + $loanGivenInterest,
            'savings_interest' => $savingsInterest,
            'loan_given_interest' => $loanGivenInterest,
        ];
    }

    /**
     * Calculate what a user's outstanding client funds balance was at a specific
     * point in time — i.e. the true total still owed to the client, reconstructed
     * directly from amount_received rather than off today's ClientFund::$balance.
     *
     * Why not just use $fund->balance (received - amount_spent - profit)?
     * Because ClientFund::updateBalance() folds "borrowed for personal use"
     * (recordBorrowed() / reconcileBorrowed()) into amount_spent exactly the
     * same way it folds in a genuine business expense. Borrowing doesn't
     * reduce what's owed to the client though — it converts part of the
     * obligation into a personal debt that still has to be physically repaid
     * (see ClientFundController::returnBorrowed()), and ClientFundController
     * already tracks that separately as "Borrowed (Unreturned)" — it just
     * never made it into this figure. Any caller that subtracts this from an
     * account balance (net worth, "excludes KES X" footnotes, the per-account
     * shortfall check) was silently treating unreturned borrowed money as if
     * it already belonged to the user.
     *
     * Only REAL expenses (is_borrowed = false) and profit actually reduce
     * what's owed; a 'return' transaction just moves cash back into place
     * and doesn't change the total obligation, which is why this formula
     * doesn't need to reference 'return' transactions at all — it's the same
     * total whether or not any given borrowed amount has since been returned.
     */
    private function getClientFundsBalanceAsAt(User $user, Carbon $asAtDate, ?int $accountId = null): float
    {
        $clientFunds = ClientFund::where('user_id', $user->id)
            ->where('received_date', '<=', $asAtDate)
            ->whereNotIn('status', ['cancelled'])
            ->with(['transactions' => function ($q) use ($asAtDate) {
                $q->whereIn('type', ['expense', 'profit'])
                    ->where('date', '<=', $asAtDate);
            }])
            ->get();

        return $clientFunds->sum(function ($fund) use ($accountId, $asAtDate) {
            $realExpenses = $fund->transactions
                ->where('type', 'expense')
                ->where('is_borrowed', false)
                ->sum('amount');

            $profitTaken = $fund->transactions
                ->where('type', 'profit')
                ->sum('amount');

            $balanceAsAt = max(0, (float)$fund->amount_received - $realExpenses - $profitTaken);

            if ($accountId === null) {
                return $balanceAsAt;
            }

            // Trace where this fund's cash actually sat as of $asAtDate by
            // replaying any transfers explicitly tagged to it, rather than
            // trusting the static account_id — which can go stale the moment
            // the money moves without a tagged transfer recording it.
            $currentAccountId = $fund->account_id;

            $movements = Transfer::withoutGlobalScopes()
                ->where('client_fund_id', $fund->id)
                ->where('date', '<=', $asAtDate)
                ->orderBy('date')
                ->get();

            foreach ($movements as $m) {
                if ($m->from_account_id === $currentAccountId) {
                    $currentAccountId = $m->to_account_id;
                }
            }

            return $currentAccountId === $accountId ? $balanceAsAt : 0.0;
        });
    }

    private function getLoansGivenActivityInPeriod(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $disbursed = LoanGiven::where('user_id', $user->id)
            ->whereBetween('disbursed_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $payments = LoanGivenPayment::where('user_id', $user->id)
            ->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('loanGiven')
            ->get();

        $closedInPeriod = LoanGiven::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereBetween('repaid_date', [$startDate, $endDate])
            ->get();

        return [
            'disbursed_count' => $disbursed->count(),
            'disbursed_total' => $disbursed->sum('principal_amount'),
            'repayments_count' => $payments->count(),
            'repayments_total' => $payments->sum('amount'),
            'closed_count' => $closedInPeriod->count(),
            'principal_recovered' => $closedInPeriod->sum('principal_amount'),
            'interest_earned' => $closedInPeriod->sum('interest_amount'),
            'items' => $closedInPeriod->map(fn($l) => [
                'borrower' => $l->borrower_name,
                'principal' => $l->principal_amount,
                'interest' => $l->interest_amount,
                'repaid_date' => Carbon::parse($l->repaid_date)->format('M d, Y'),
            ])->values()->toArray(),
        ];
    }

    /**
     * Direct ledger figure — mirrors ClientFundController::index()'s
     * $summary['total_borrowed'], but as-at a specific date rather than live.
     * Unlike the old per-account shortfall proxy (raw balance vs. reconstructed
     * "true owed"), this reads straight from ClientFundTransaction and isn't
     * blinded by recordBorrowed()/reconcileBorrowed() never touching account
     * balances — those calls create no Transaction, so a borrow that was never
     * separately corrected with a Balance Adjustment was invisible to the old
     * per-account method entirely.
     */
    private function getUnreturnedBorrowedAsAt(User $user, Carbon $asAtDate): float
    {
        $userFundIds = ClientFund::where('user_id', $user->id)->pluck('id');

        $borrowedGross = ClientFundTransaction::whereIn('client_fund_id', $userFundIds)
            ->where('is_borrowed', true)
            ->where('date', '<=', $asAtDate)
            ->sum('amount');

        $returned = ClientFundTransaction::whereIn('client_fund_id', $userFundIds)
            ->where('type', 'return')
            ->where('date', '<=', $asAtDate)
            ->sum('amount');

        return max(0, $borrowedGross - $returned);
    }
}
