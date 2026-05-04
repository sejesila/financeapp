<?php

namespace App\Services;

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
    public function generateAnnualReport(User $user): array
    {
        $year      = now()->subYear()->year;
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate   = Carbon::create($year, 12, 31)->endOfDay();

        Log::info('Generating annual report for user: ' . $user->email . ' | year: ' . $year);

        $report = $this->generateReport($user, $startDate, $endDate, 'annual');

        // Month-by-month breakdown
        $monthlyBreakdown = [];
        for ($month = 1; $month <= 12; $month++) {
            $mStart = Carbon::create($year, $month, 1)->startOfDay();
            $mEnd   = $mStart->copy()->endOfMonth()->endOfDay();

            // ✅ FIXED: Use same filtering as main report generation
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
        $profitMonths = $collection->where('net_flow', '>=', 0)->count();

        // Loans paid down during the year
        $loansPaidInYear = $this->getLoanPaymentsInPeriod($user, $startDate, $endDate);

        // Prior year income for trend
        $priorYearStart  = Carbon::create($year - 1, 1, 1)->startOfDay();
        $priorYearEnd    = Carbon::create($year - 1, 12, 31)->endOfDay();
        $priorYearIncome = $this->getFilteredTransactions($user, $priorYearStart, $priorYearEnd)
            ->filter(fn($t) => $t->category->type === 'income')
            ->sum('amount');

        $report['monthly_breakdown']    = $monthlyBreakdown;
        $report['best_month']           = $bestMonth;
        $report['worst_month']          = $worstMonth;
        $report['profitable_months']    = $profitMonths;
        $report['loans_paid_in_period'] = $loansPaidInYear;
        $report['prior_period_income']  = $priorYearIncome;
        $report['income_trend']         = $priorYearIncome > 0
            ? (($report['income'] - $priorYearIncome) / $priorYearIncome) * 100
            : null;
        $report['period_type']          = 'annual';
        $report['year']                 = $year;

        return $report;
    }

    public function generateMonthlyReport(User $user): array
    {
        $startDate = now()->startOfMonth();
        $endDate   = now()->endOfMonth();

        $report = $this->generateReport($user, $startDate, $endDate, 'monthly');

        // Loans paid this month
        $report['loans_paid_in_period'] = $this->getLoanPaymentsInPeriod($user, $startDate, $endDate);

        // Prior month income for trend
        $prevStart = $startDate->copy()->subMonth()->startOfMonth();
        $prevEnd   = $prevStart->copy()->endOfMonth();

        $priorMonthIncome = $this->getFilteredTransactions($user, $prevStart, $prevEnd)
            ->filter(fn($t) => $t->category->type === 'income')
            ->sum('amount');

        $report['prior_period_income'] = $priorMonthIncome;
        $report['income_trend']        = $priorMonthIncome > 0
            ? (($report['income'] - $priorMonthIncome) / $priorMonthIncome) * 100
            : null;

        // Budget adherence
        $budgetPerf               = collect($report['budget_performance']);
        $report['budgets_under']  = $budgetPerf->where('percentage', '<=', 100)->count();
        $report['budgets_over']   = $budgetPerf->where('percentage', '>', 100)->count();
        $report['budgets_total']  = $budgetPerf->count();

        return $report;
    }

    public function generateCustomReport(User $user, Carbon $startDate, Carbon $endDate): array
    {
        return $this->generateReport($user, $startDate, $endDate, 'custom');
    }

    /**
     * ✅ NEW: Extract shared filtering logic to ensure consistency
     * Matches BudgetController's transaction filtering exactly
     */
    private function getFilteredTransactions(User $user, Carbon $startDate, Carbon $endDate)
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->whereBetween(DB::raw('COALESCE(period_date, date)'), [$startDate, $endDate])
            ->where(function($q) {
                // Include regular transactions (not related to client funds)
                $q->where(function($q2) {
                    $q2->where('payment_method', '!=', 'Client Fund')
                        ->where('payment_method', '!=', 'Client Commission')
                        ->orWhereNull('payment_method');
                })
                    // OR include client fund profit income (payment_method = 'Client Commission' AND type = 'income')
                    ->orWhereExists(function($query) {
                        $query->select(DB::raw(1))
                            ->from('categories')
                            ->whereColumn('categories.id', 'transactions.category_id')
                            ->where('categories.type', 'income')
                            ->where('transactions.payment_method', 'Client Commission');
                    });
            })
            ->whereHas('category', function ($q) {
                $q->whereIn('type', ['income', 'expense'])
                    ->whereNotIn('name', [
                        'Loan Disbursement',
                        'Loan Receipt',
                        'Balance Adjustment',
                        'Client Funds'  // ✅ Exclude liability category
                    ]);
            })
            ->with(['category', 'account'])
            ->get();
    }

    private function generateReport(User $user, Carbon $startDate, Carbon $endDate, string $type): array
    {
        // Accounts
        $accounts     = Account::where('user_id', $user->id)->where('is_active', true)->get();
        $totalBalance = $accounts->sum('current_balance');

        // ✅ FIXED: Use consistent filtering
        $transactions = $this->getFilteredTransactions($user, $startDate, $endDate)
            ->sortBy(fn($t) => $t->date)
            ->reverse()
            ->values();

        // Calculate income and expenses
        $income   = $transactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
        $expenses = $transactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
        $netFlow  = $income - $expenses;

        // Top Spending Categories
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

        // Largest individual expense transactions (top 5)
        $largestTransactions = $transactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->sortByDesc('amount')
            ->take(5)
            ->values();

        // Daily Spending (for charts)
        $dailySpending = Transaction::where('user_id', $user->id)
            ->whereBetween(DB::raw('COALESCE(period_date, date)'), [$startDate, $endDate])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->where(function($q) {
                $q->where(function($q2) {
                    $q2->where('payment_method', '!=', 'Client Fund')
                        ->where('payment_method', '!=', 'Client Commission')
                        ->orWhereNull('payment_method');
                })
                    ->orWhereExists(function($query) {
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
                    'Client Funds'
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

        // Active Loans
        $activeLoans      = Loan::where('user_id', $user->id)->where('status', 'active')->with('account')->get();
        $totalLoanBalance = $activeLoans->sum('balance');

        // Budget Performance (monthly only)
        $budgetPerformance = [];
        if ($type === 'monthly') {
            $budgets = Budget::where('user_id', $user->id)
                ->where('year', $startDate->year)
                ->where('month', $startDate->month)
                ->with('category')
                ->get();

            foreach ($budgets as $budget) {
                $spent = $transactions
                    ->where('category_id', $budget->category_id)
                    ->filter(fn($t) => $t->category->type === 'expense')
                    ->sum('amount');

                $budgetPerformance[] = [
                    'category'   => $budget->category->name,
                    'budgeted'   => $budget->amount,
                    'spent'      => $spent,
                    'remaining'  => $budget->amount - $spent,
                    'percentage' => $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0,
                ];
            }

            // Sort: over budget first, then by percentage desc
            usort($budgetPerformance, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
        }

        // Insights
        $insights = $this->generateInsights($user, $transactions, $startDate, $endDate, $type);

        return [
            'period_type'          => $type,
            'start_date'           => $startDate->format('M d, Y'),
            'end_date'             => $endDate->format('M d, Y'),
            'user'                 => $user,
            'accounts'             => $accounts,
            'total_balance'        => $totalBalance,
            'total_loans'          => $totalLoanBalance,
            'net_worth'            => $totalBalance - $totalLoanBalance,
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

    private function getLoanPaymentsInPeriod(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // ✅ Loan payments are specifically categorized, so use direct query
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

        // Compare with previous period
        if ($type === 'annual') {
            $prevStart = $startDate->copy()->subYear();
            $prevEnd   = $endDate->copy()->subYear();
        } else {
            $prevStart = $startDate->copy()->subMonth();
            $prevEnd   = $endDate->copy()->subMonth();
        }

        $periodLabel = match ($type) {
            'annual'  => 'year',
            'monthly' => 'month',
            default   => 'period',
        };

        // ✅ FIXED: Use filtered transactions for comparison
        $prevTransactions = $this->getFilteredTransactions($user, $prevStart, $prevEnd);
        $prevExpenses = $prevTransactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');

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

        // Biggest single expense
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

        // Savings rate
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
