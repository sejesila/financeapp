<?php

namespace App\Services;

use App\Models\ClientFund;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Budget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportDataService
{
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

        $monthlyBreakdown = [];
        for ($month = 1; $month <= 12; $month++) {
            $mStart = Carbon::create($year, $month, 1)->startOfDay();
            $mEnd   = $mStart->copy()->endOfMonth()->endOfDay();

            $monthTransactions = $this->getFilteredTransactions($user, $mStart, $mEnd);

            $mIncome   = $monthTransactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
            $mExpenses = $monthTransactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
            $mNet      = $mIncome - $mExpenses;

            $monthlyBreakdown[] = [
                'month'             => $mStart->format('F Y'),
                'month_short'       => $mStart->format('M'),
                'income'            => $mIncome,
                'expenses'          => $mExpenses,
                'net_flow'          => $mNet,
                'savings_rate'      => $mIncome > 0 ? ($mNet / $mIncome) * 100 : 0,
                'transaction_count' => $monthTransactions->count(),
            ];
        }

        $collection   = collect($monthlyBreakdown);
        $bestMonth    = $collection->sortByDesc('net_flow')->first();
        $worstMonth   = $collection->sortBy('net_flow')->first();
        $profitMonths = $collection->where('net_flow', '>', 0)->count();

        $loansPaidInYear       = $this->getLoanPaymentsInPeriod($user, $startDate, $endDate);
        $loansRepaidDuringYear = $this->getLoansRepaidInPeriod($user, $startDate, $endDate);

        $priorYearStart  = Carbon::create($year - 1, 1, 1)->startOfDay();
        $priorYearEnd    = Carbon::create($year - 1, 12, 31)->endOfDay();
        $priorYearIncome = $this->getFilteredTransactions($user, $priorYearStart, $priorYearEnd)
            ->filter(fn($t) => $t->category->type === 'income')
            ->sum('amount');

        $report['monthly_breakdown']      = $monthlyBreakdown;
        $report['best_month']             = $bestMonth;
        $report['worst_month']            = $worstMonth;
        $report['profitable_months']      = $profitMonths;
        $report['loans_paid_in_period']   = $loansPaidInYear;
        $report['loans_repaid_in_period'] = $loansRepaidDuringYear;
        $report['prior_period_income']    = $priorYearIncome;
        $report['income_trend']           = $priorYearIncome > 0
            ? (($report['income'] - $priorYearIncome) / $priorYearIncome) * 100
            : null;
        $report['period_type'] = 'annual';
        $report['year']        = $year;

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
            ->whereBetween(DB::raw('COALESCE(period_date, date)'), [
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
    private function buildRollingBaselines(User $user, Carbon $startDate, int $lookbackMonths = 3): array
    {
        $baselines = [];

        for ($i = 1; $i <= $lookbackMonths; $i++) {
            $mStart = $startDate->copy()->subMonths($i)->startOfMonth();
            $mEnd   = $mStart->copy()->endOfMonth();

            $monthTx = $this->getFilteredTransactions($user, $mStart, $mEnd)
                ->filter(fn($t) => $t->category->type === 'expense');

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

        // Convert running totals to averages
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
        $accounts     = Account::where('user_id', $user->id)->where('is_active', true)->get();
        $totalBalance = $accounts->sum('current_balance');

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

        $dailySpending = Transaction::where('user_id', $user->id)
            ->whereBetween(DB::raw('COALESCE(period_date, date)'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('payment_method', '!=', 'Client Fund')
                        ->where('payment_method', '!=', 'Client Commission')
                        ->orWhereNull('payment_method');
                })
                    ->orWhereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('categories')
                            ->whereColumn('categories.id', 'transactions.category_id')
                            ->where('categories.type', 'income')
                            ->where('transactions.payment_method', 'Client Commission');
                    });
            })
            ->whereHas('category', function ($q) {
                $q->whereNotIn('name', [
                    'Loan Disbursement',
                    'Loan Receipt',
                    'Balance Adjustment',
                    'Client Funds',
                ]);
            })
            ->select(DB::raw('DATE(COALESCE(period_date, date)) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(COALESCE(period_date, date))'))
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date'   => Carbon::parse($item->date)->format('M d'),
                'amount' => $item->total,
            ]);

        $activeLoans      = Loan::where('user_id', $user->id)->where('status', 'active')->with('account')->get();
        $totalLoanBalance = $activeLoans->sum('balance');
        $totalClientFunds = ClientFund::where('user_id', $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->sum('balance');

        // -------------------------------------------------------------------------
        // Budget Performance — uses rolling 3-month average as the baseline target
        // instead of stored Budget records.  This means every expense category that
        // had spend in the period is automatically benchmarked against its own
        // historical average, so the percentage is always a meaningful signal.
        // -------------------------------------------------------------------------
        $budgetPerformance = [];

        if ($type === 'monthly') {
            $baselines = $this->buildRollingBaselines($user, $startDate, lookbackMonths: 3);

            // Group actual spend for the period by category
            $actualByCat = $transactions
                ->filter(fn($t) => $t->category->type === 'expense')
                ->groupBy('category_id')
                ->map(fn($group) => [
                    'name'  => $group->first()->category->name,
                    'spent' => $group->sum('amount'),
                ]);

            // Union of all categories: those with a baseline AND those that are
            // brand-new this month (no prior history yet).
            $allCategoryIds = $actualByCat->keys()
                ->merge(collect($baselines)->keys())
                ->unique();

            foreach ($allCategoryIds as $catId) {
                $spent    = $actualByCat[$catId]['spent']   ?? 0;
                $catName  = $actualByCat[$catId]['name']
                    ?? $baselines[$catId]['name']
                    ?? 'Unknown';

                // If no prior history exists yet, treat last month's spend as
                // a provisional baseline (100 % — neutral, not alarming).
                $baseline    = $baselines[$catId]['baseline'] ?? $spent;
                $monthsUsed  = $baselines[$catId]['months_used'] ?? 0;
                $remaining   = $baseline - $spent;
                $percentage  = $baseline > 0 ? ($spent / $baseline) * 100 : ($spent > 0 ? 100 : 0);

                $budgetPerformance[] = [
                    'category'    => $catName,
                    'budgeted'    => round($baseline, 2),   // the rolling average
                    'spent'       => round($spent, 2),
                    'remaining'   => round($remaining, 2),
                    'percentage'  => round($percentage, 1),
                    'months_used' => $monthsUsed,           // exposed for template use
                    'is_new'      => $monthsUsed === 0,     // flag for "no prior data"
                ];
            }

            // Sort: most over-budget first, then by percentage descending
            usort($budgetPerformance, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
        }

        $insights = $this->generateInsights($user, $transactions, $startDate, $endDate, $type);

        return [
            'period_type'        => $type,
            'start_date'         => $startDate->format('M d, Y'),
            'end_date'           => $endDate->format('M d, Y'),
            'user'               => $user,
            'accounts'           => $accounts,
            'total_balance'      => $totalBalance,
            'total_loans'        => $totalLoanBalance,
            'total_client_funds' => $totalClientFunds,
            'net_worth'          => $totalBalance - $totalLoanBalance - $totalClientFunds,
            'transactions'       => match ($type) {
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
     * Get loan payment transactions in a period
     */
    private function getLoanPaymentsInPeriod(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $payments = Transaction::where('user_id', $user->id)
            ->whereBetween(DB::raw('COALESCE(period_date, date)'), [$startDate, $endDate])
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
                'value'       => 'KES ' . $biggestExpense->amount,
                'description' => $biggestExpense->description . ' (' . $biggestExpense->category->name . ')',
            ];
        }

        if ($income > 0) {
            $savingsRate = (($income - $totalExpenses) / $income) * 100;
            $insights[]  = [
                'icon'        => $savingsRate > 20 ? '🎯' : '⚠️',
                'title'       => 'Savings Rate',
                'value'       => number_format($savingsRate, 1) . '%',
                'description' => $savingsRate > 20
                    ? "Great! You're saving well"
                    : 'Consider reducing expenses to save more',
            ];
        }

        return $insights;
    }
}
