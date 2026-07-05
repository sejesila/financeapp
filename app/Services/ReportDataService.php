<?php

namespace App\Services;

use App\Models\ClientFund;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Budget;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ReportDataService
{
    private const MIN_SALARY_AMOUNT_FOR_SAVINGS_RATE = 40000;
    private const SALARY_TO_SAVINGS_WINDOW_HOURS = 72;
    private const SAVINGS_REVERSAL_WINDOW_DAYS = 7;
    /**
     * Generate annual report for the prior full year
     */
    public function generateAnnualReport(User $user): array
    {
        $year      = now()->subYear()->year;
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate   = Carbon::create($year, 12, 31)->endOfDay();

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
        $lookbackEnd   = Carbon::create($year, 1, 1)->startOfDay()->subDay()->endOfDay();
        $lookbackTx    = $this->getFilteredTransactions($user, $lookbackStart, $lookbackEnd);

        $monthlyBreakdown = [];
        for ($month = 1; $month <= 12; $month++) {
            $mStart = Carbon::create($year, $month, 1)->startOfDay();
            $mEnd   = $mStart->copy()->endOfMonth()->endOfDay();

            $monthTransactions = $this->getFilteredTransactions($user, $mStart, $mEnd);

            $mIncome   = $monthTransactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
            $mExpenses = $monthTransactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
            $mNet      = $mIncome - $mExpenses;

            // Stored budgets for this month, keyed by category_id
            $monthBudgets = $allBudgets->get($month, collect());

            // Actual spend per category
            $actualByCat = $monthTransactions
                ->filter(fn($t) => $t->category->type === 'expense')
                ->groupBy('category_id')
                ->map(fn($group) => [
                    'name'  => $group->first()->category->name,
                    'spent' => $group->sum('amount'),
                ]);

            // Use pre-loaded lookback transactions instead of re-querying
            $baselines = $this->buildRollingBaselines($user, $mStart, lookbackMonths: 3, preloadedTx: $lookbackTx);

            $allCatIds = $actualByCat->keys()
                ->merge($monthBudgets->keys())
                ->unique();

            $categoryPerformance = [];
            $totalBudgeted       = 0;
            $totalSpent          = 0;
            $catsOver            = 0;
            $catsUnder           = 0;

            foreach ($allCatIds as $catId) {
                $spent = $actualByCat[$catId]['spent'] ?? 0;
                if ($spent === 0) continue;

                $catName   = $actualByCat[$catId]['name']
                    ?? $baselines[$catId]['name']
                    ?? 'Unknown';
                $hasBudget = $monthBudgets->has($catId);
                $budgeted  = $hasBudget
                    ? (float) $monthBudgets[$catId]->amount
                    : ($baselines[$catId]['baseline'] ?? $spent);

                $remaining  = $budgeted - $spent;
                $percentage = $budgeted > 0 ? ($spent / $budgeted) * 100 : 100;

                $categoryPerformance[] = [
                    'category'    => $catName,
                    'budgeted'    => round($budgeted, 2),
                    'spent'       => round($spent, 2),
                    'remaining'   => round($remaining, 2),
                    'percentage'  => round($percentage, 1),
                    'has_budget'  => $hasBudget,
                    'months_used' => $baselines[$catId]['months_used'] ?? 0,
                    'is_new'      => ($baselines[$catId]['months_used'] ?? 0) === 0,
                ];

                $totalBudgeted += $budgeted;
                $totalSpent    += $spent;
                $percentage >= 100 ? $catsOver++ : $catsUnder++;
            }

            usort($categoryPerformance, fn($a, $b) => $b['percentage'] <=> $a['percentage']);

            // Income budgets for the month
            $mIncomeBudgeted = $monthBudgets
                ->filter(fn($b) => optional($b->category)->type === 'income')
                ->sum('amount');

            $monthlyBreakdown[] = [
                'month'                => $mStart->format('F Y'),
                'month_short'          => $mStart->format('M'),
                'income'               => $mIncome,
                'expenses'             => $mExpenses,
                'net_flow'             => $mNet,
                'savings_rate'         => $mIncome > 0 ? ($mNet / $mIncome) * 100 : 0,
                'transaction_count'    => $monthTransactions->count(),
                'budgeted_expenses'    => round($totalBudgeted, 2),
                'budgeted_income'      => round($mIncomeBudgeted, 2),
                'budget_variance'      => round($totalBudgeted - $totalSpent, 2),
                'cats_over_budget'     => $catsOver,
                'cats_under_budget'    => $catsUnder,
                'category_performance' => $categoryPerformance,
            ];
        }

        $collection   = collect($monthlyBreakdown);
        $bestMonth    = $collection->sortByDesc('net_flow')->first();
        $worstMonth   = $collection->sortBy('net_flow')->first();
        $profitMonths = $collection->where('net_flow', '>', 0)->count();

        // Annual budget summary across all months
        $annualBudgetedExpenses = $collection->sum('budgeted_expenses');
        $annualActualExpenses   = $collection->sum('expenses');
        $annualBudgetVariance   = $annualBudgetedExpenses - $annualActualExpenses;
        $monthsOverBudget       = $collection->where('cats_over_budget', '>', 0)->count();

        $loansPaidInYear       = $this->getLoanPaymentsInPeriod($user, $startDate, $endDate);
        $loansRepaidDuringYear = $this->getLoansRepaidInPeriod($user, $startDate, $endDate);

        $priorYearStart  = Carbon::create($year - 1, 1, 1)->startOfDay();
        $priorYearEnd    = Carbon::create($year - 1, 12, 31)->endOfDay();
        $priorYearIncome = $this->getFilteredTransactions($user, $priorYearStart, $priorYearEnd)
            ->filter(fn($t) => $t->category->type === 'income')
            ->sum('amount');

        $report['monthly_breakdown']        = $monthlyBreakdown;
        $report['best_month']               = $bestMonth;
        $report['worst_month']              = $worstMonth;
        $report['profitable_months']        = $profitMonths;
        $report['loans_paid_in_period']     = $loansPaidInYear;
        $report['loans_repaid_in_period']   = $loansRepaidDuringYear;
        $report['prior_period_income']      = $priorYearIncome;
        $report['income_trend']             = $priorYearIncome > 0
            ? (($report['income'] - $priorYearIncome) / $priorYearIncome) * 100
            : null;
        $report['period_type']              = 'annual';
        $report['year']                     = $year;
        $report['salary_savings_rate']      = $this->getSalarySavingsRate($user, $startDate, $endDate);
        $report['annual_budgeted_expenses'] = $annualBudgetedExpenses;
        $report['annual_budget_variance']   = $annualBudgetVariance;
        $report['months_over_budget']       = $monthsOverBudget;

        return $report;
    }

    /**
     * Generate monthly report for the prior month
     */
    public function generateMonthlyReport(User $user): array
    {
        $startDate = now()->subMonth()->startOfMonth();
        $endDate   = now()->subMonth()->endOfMonth();

        $report = $this->generateReport($user, $startDate, $endDate, 'monthly');

        $report['loans_paid_in_period']   = $this->getLoanPaymentsInPeriod($user, $startDate, $endDate);
        $report['loans_repaid_in_period'] = $this->getLoansRepaidInPeriod($user, $startDate, $endDate);

        $prevStart = $startDate->copy()->subMonth()->startOfMonth();
        $prevEnd   = $prevStart->copy()->endOfMonth();

        $priorMonthIncome = $this->getFilteredTransactions($user, $prevStart, $prevEnd)
            ->filter(fn($t) => $t->category->type === 'income')
            ->sum('amount');

        $report['prior_period_income'] = $priorMonthIncome;
        $report['income_trend']        = $priorMonthIncome > 0
            ? (($report['income'] - $priorMonthIncome) / $priorMonthIncome) * 100
            : null;

        $budgetPerf              = collect($report['budget_performance']);
        $report['budgets_under'] = $budgetPerf->where('percentage', '<=', 100)->count();
        $report['budgets_over']  = $budgetPerf->where('percentage', '>', 100)->count();
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
                    ->whereNotIn('name', [
                        'Loan Disbursement',
                        'Loan Receipt',
                        'Balance Adjustment',
                        'Client Funds',
                    ]);
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
            $mEnd   = $mStart->copy()->endOfMonth();

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
                        'name'        => $group->first()->category->name,
                        'total'       => 0.0,
                        'months_used' => 0,
                    ];
                }
                $baselines[$catId]['total']       += $group->sum('amount');
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
        $accountsAsAt = $accounts->map(function ($account) use ($endDate) {
            $account->balance_as_at = $this->getAccountBalanceAsAt($account, $endDate);
            return $account;
        });

        $totalBalance = $accountsAsAt->sum('balance_as_at');

        $activeLoans      = Loan::where('user_id', $user->id)->where('status', 'active')->with('account')->get();
        $totalLoanBalance = $activeLoans->sum('balance');

        $totalClientFunds = ClientFund::where('user_id', $user->id)
            ->where('received_date', '<=', $endDate)
            ->where(function ($q) use ($endDate) {
                $q->whereNull('completed_date')
                    ->orWhere('completed_date', '>', $endDate)
                    ->orWhereIn('status', ['pending', 'partial']); // safety net for inconsistent data
            })
            ->whereNotIn('status', ['cancelled'])
            ->sum('balance');

        // Historical savings balance — what was actually in savings at period end, not today
        $savingsBalance = $this->getSavingsBalanceAsAt($user, $endDate);
        $ownedSavings = max(0, $savingsBalance - $totalClientFunds);
        $netWorth     = max(0, $ownedSavings - $totalLoanBalance);

        // --- Transactions ---
        $transactions = $this->getFilteredTransactions($user, $startDate, $endDate)
            ->sortBy(fn($t) => $t->date)
            ->reverse()
            ->values();

        $income   = $transactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
        $expenses = $transactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
        $netFlow  = $income - $expenses;

        $topCategories = $transactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->groupBy('category_id')
            ->map(fn($group) => [
                'category' => $group->first()->category->name,
                'amount'   => $group->sum('amount'),
                'count'    => $group->count(),
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
                'date'   => Carbon::parse($date)->format('M d'),
                'amount' => $group->sum('amount'),
            ])
            ->sortKeys()
            ->values();

        // --- Budget Performance (monthly only) ---
        $budgetPerformance = [];

        if ($type === 'monthly') {
            $reportMonth = $startDate->month;
            $reportYear  = $startDate->year;

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
                    'name'  => $group->first()->category->name,
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

                $hasBudget  = isset($storedBudgets[$catId]);
                $baseline   = $hasBudget
                    ? (float) $storedBudgets[$catId]->amount
                    : ($baselines[$catId]['baseline'] ?? $spent);
                $monthsUsed = $baselines[$catId]['months_used'] ?? 0;
                $remaining  = $baseline - $spent;
                $percentage = $baseline > 0 ? ($spent / $baseline) * 100 : ($spent > 0 ? 100 : 0);

                $budgetPerformance[] = [
                    'category'    => $catName,
                    'budgeted'    => round($baseline, 2),
                    'spent'       => round($spent, 2),
                    'remaining'   => round($remaining, 2),
                    'percentage'  => round($percentage, 1),
                    'months_used' => $monthsUsed,
                    'is_new'      => $monthsUsed === 0,
                    'has_budget'  => $hasBudget,
                ];
            }

            usort($budgetPerformance, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
        }

        $insights = $this->generateInsights($user, $transactions, $startDate, $endDate, $type);

        return [
            'period_type'          => $type,
            'start_date'           => $startDate->format('M d, Y'),
            'end_date'             => $endDate->format('M d, Y'),
            'user'                 => $user,
            'accounts'             => $accountsAsAt,
            'total_balance'        => $totalBalance,
            'savings_balance'      => $savingsBalance,
            'total_loans'          => $totalLoanBalance,
            'total_client_funds'   => $totalClientFunds,
            'net_worth'            => $netWorth,
            'transactions'         => match ($type) {
                'annual'  => $transactions->take(50),
                'monthly' => $transactions->take(30),
                default   => $transactions->take(25),
            },
            'transaction_count'    => $transactions->count(),
            'income'               => $income,
            'expenses'             => $expenses,
            'net_flow'             => $netFlow,
            'savings_rate'         => $income > 0 ? ($netFlow / $income) * 100 : 0,
            'top_categories'       => $topCategories,
            'largest_transactions' => $largestTransactions,
            'daily_spending'       => $dailySpending,
            'active_loans'         => $activeLoans,
            'budget_performance'   => $budgetPerformance,
            'insights'             => $insights,
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

        // Income transactions AND "Client Funds" liability transactions both increase
        // the account balance, so both must be reversed the same way.
        $incomingAfter = $txAfter
            ->filter(fn($t) => $t->category->type === 'income' || $t->category->name === 'Client Funds')
            ->sum('amount');

        $outgoingAfter = $txAfter
            ->filter(fn($t) => $t->category->type === 'expense')
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
            + $transfersOutAfter;

        return max(0, $balanceAsAt);
    }

    /**
     * Calculate what a single account's balance was at a specific point in time
     * by taking current_balance and reversing transactions/transfers that
     * occurred after $asAtDate.
     */
    private function getAccountBalanceAsAt(Account $account, Carbon $asAtDate): float
    {
        $currentBalance = (float) $account->current_balance;

        $txAfter = Transaction::where('user_id', $account->user_id)
            ->where('account_id', $account->id)
            ->where('date', '>', $asAtDate->toDateString())
            ->with('category')
            ->get();

        $incomingAfter = $txAfter
            ->filter(fn($t) => $t->category->type === 'income' || $t->category->name === 'Client Funds')
            ->sum('amount');

        $outgoingAfter = $txAfter
            ->filter(fn($t) => $t->category->type === 'expense')
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
            + $transfersOutAfter;

        return $balanceAsAt; // no max(0,...) here — non-savings accounts can legitimately be negative
    }

    /**
     * Get loan payment transactions in a period
     */
    private function getLoanPaymentsInPeriod(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $payments = Transaction::where('user_id', $user->id)
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->whereHas('category', fn($q) => $q->where('name', 'Loan Repayment'))
            ->with(['category', 'account'])
            ->get();

        return [
            'count' => $payments->count(),
            'total' => $payments->sum('amount'),
            'items' => $payments->map(fn($t) => [
                'date'        => Carbon::parse($t->date)->format('M d, Y'),
                'description' => $t->description,
                'amount'      => $t->amount,
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
            'count'           => $repaidLoans->count(),
            'total'           => $repaidLoans->sum('total_amount'),
            'principal_total' => $repaidLoans->sum('principal_amount'),
            'items'           => $repaidLoans->map(fn($loan) => [
                'source'      => $loan->source,
                'principal'   => $loan->principal_amount,
                'total'       => $loan->total_amount,
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

        $days          = $startDate->diffInDays($endDate) + 1;
        $totalExpenses = $transactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
        $income        = $transactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
        $avgDaily      = $days > 0 ? $totalExpenses / $days : 0;

        $insights[] = [
            'icon'        => '📊',
            'title'       => 'Average Daily Spending',
            'value'       => 'KES ' . number_format($avgDaily, 0),
            'description' => 'You spent an average of KES ' . number_format($avgDaily, 0) . ' per day',
        ];

        $periodLabel = match ($type) {
            'annual'  => 'year',
            'monthly' => 'month',
            default   => 'period',
        };

        if ($type === 'annual') {
            $prevStart = $startDate->copy()->subYear();
            $prevEnd   = $endDate->copy()->subYear();
        } else {
            $prevStart = $startDate->copy()->subMonth();
            $prevEnd   = $endDate->copy()->subMonth();
        }

        $prevTransactions = $this->getFilteredTransactions($user, $prevStart, $prevEnd);
        $prevExpenses     = $prevTransactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');

        $change        = $totalExpenses - $prevExpenses;
        $changePercent = $prevExpenses > 0 ? (($change / $prevExpenses) * 100) : 0;

        if ($change > 0) {
            $insights[] = [
                'icon'        => '📈',
                'title'       => 'Spending Increased',
                'value'       => '+' . number_format($changePercent, 1) . '%',
                'description' => 'You spent KES ' . number_format($change, 0) . ' more than last ' . $periodLabel,
                'trend'       => 'up',
            ];
        } elseif ($change < 0) {
            $insights[] = [
                'icon'        => '📉',
                'title'       => 'Spending Decreased',
                'value'       => number_format($changePercent, 1) . '%',
                'description' => 'You spent KES ' . number_format(abs($change), 0) . ' less than last ' . $periodLabel,
                'trend'       => 'down',
            ];
        }

        $biggestExpense = $transactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->sortByDesc('amount')
            ->first();

        if ($biggestExpense) {
            $insights[] = [
                'icon'        => '💸',
                'title'       => 'Biggest Expense',
                'value'       => 'KES ' . number_format($biggestExpense->amount, 0),
                'description' => $biggestExpense->description . ' (' . $biggestExpense->category->name . ')',
            ];
        }

        if ($income > 0) {
            $savingsRate = (($income - $totalExpenses) / $income) * 100;
            $insights[]  = [
                'icon'        => $savingsRate > 20 ? '🎯' : '⚠️',
                'value'       => number_format($savingsRate, 1) . '%',
                'title'       => 'Surplus Rate',
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
            $windowEnd  = $salaryDate->copy()->addHours(self::SALARY_TO_SAVINGS_WINDOW_HOURS);

            $transferredToSavings = Transfer::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereIn('to_account_id', $savingsAccountIds)
                ->whereBetween('date', [
                    $salaryDate->toDateTimeString(),
                    $windowEnd->toDateTimeString(),
                ])
                ->sum('amount');

            // Net out any money pulled back OUT of savings within a wider window
            // (7 days from the salary date) — a same-week reversal means the
            // salary was never really "saved".
            $reversalWindowEnd = $salaryDate->copy()->addDays(self::SAVINGS_REVERSAL_WINDOW_DAYS);

            $transferredFromSavings = Transfer::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereIn('from_account_id', $savingsAccountIds)
                ->whereBetween('date', [
                    $salaryDate->toDateTimeString(),
                    $reversalWindowEnd->toDateTimeString(),
                ])
                ->sum('amount');

            $netSaved = max(0, $transferredToSavings - $transferredFromSavings);

            $results[] = [
                'salary_date'        => $salaryDate->format('M d, Y'),
                'salary_amount'      => (float) $salary->amount,
                'saved_amount'       => (float) $netSaved,
                'gross_saved_amount' => (float) $transferredToSavings,
                'reversed_amount'    => (float) $transferredFromSavings,
                'savings_percentage' => $salary->amount > 0
                    ? round(($netSaved / $salary->amount) * 100, 1)
                    : 0,
            ];
        }

        return $results;
    }
}
