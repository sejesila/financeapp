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
    public function generateWeeklyReport(User $user): array
    {
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();
        Log::info('Generating report for user: ' . $user->email);


        return $this->generateReport($user, $startDate, $endDate, 'weekly');
    }

    public function generateMonthlyReport(User $user): array
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        return $this->generateReport($user, $startDate, $endDate, 'monthly');
    }

    public function generateCustomReport(User $user, Carbon $startDate, Carbon $endDate): array
    {
        return $this->generateReport($user, $startDate, $endDate, 'custom');
    }

    private function generateReport(User $user, Carbon $startDate, Carbon $endDate, string $type): array
    {
        // Account Balances
        $accounts = Account::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $totalBalance = $accounts->sum('current_balance');

        // Transactions
        $transactions = Transaction::where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['category', 'account'])
            ->orderBy('date', 'desc')
            ->get();

        $income = $transactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
        $expenses = $transactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
        $netFlow = $income - $expenses;

        // Top Spending Categories
        $topCategories = $transactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->groupBy('category_id')
            ->map(function($group) {
                return [
                    'category' => $group->first()->category->name,
                    'amount' => $group->sum('amount'),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->take(5)
            ->values();

        // Daily Spending (for charts)
        $dailySpending = Transaction::where('user_id', $user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->select(DB::raw('DATE(date) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => Carbon::parse($item->date)->format('M d'),
                'amount' => $item->total
            ]);

        // Active Loans
        $activeLoans = Loan::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('account')
            ->get();

        $totalLoanBalance = $activeLoans->sum('balance');

        // Budget Performance (for monthly reports)
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
                    ->where('category.type', 'expense')
                    ->sum('amount');

                $budgetPerformance[] = [
                    'category' => $budget->category->name,
                    'budgeted' => $budget->amount,
                    'spent' => $spent,
                    'remaining' => $budget->amount - $spent,
                    'percentage' => $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0,
                ];
            }
        }

        // Insights
        $insights = $this->generateInsights($user, $transactions, $startDate, $endDate, $type);

        return [
            'period_type' => $type,
            'start_date' => $startDate->format('M d, Y'),
            'end_date' => $endDate->format('M d, Y'),
            'user' => $user,
            'accounts' => $accounts,
            'total_balance' => $totalBalance,
            'total_loans' => $totalLoanBalance,
            'net_worth' => $totalBalance - $totalLoanBalance,
            'transactions' => $transactions->take(20), // Latest 20
            'transaction_count' => $transactions->count(),
            'income' => $income,
            'expenses' => $expenses,
            'net_flow' => $netFlow,
            'top_categories' => $topCategories,
            'daily_spending' => $dailySpending,
            'active_loans' => $activeLoans,
            'budget_performance' => $budgetPerformance,
            'insights' => $insights,
        ];
    }

    private function generateInsights(User $user, $transactions, Carbon $startDate, Carbon $endDate, string $type): array
    {
        $insights = [];

        // Average daily spending
        $days = $startDate->diffInDays($endDate) + 1;
        $totalExpenses = $transactions->filter(fn($t) => $t->category->type === 'expense')->sum('amount');
        $avgDaily = $days > 0 ? $totalExpenses / $days : 0;

        $insights[] = [
            'icon' => '📊',
            'title' => 'Average Daily Spending',
            'value' => 'KES ' . number_format($avgDaily, 0),
            'description' => "You spent an average of KES " . number_format($avgDaily, 0) . " per day"
        ];

        // Compare with previous period
        if ($type === 'weekly') {
            $prevStart = $startDate->copy()->subWeek();
            $prevEnd = $endDate->copy()->subWeek();
        } else {
            $prevStart = $startDate->copy()->subMonth();
            $prevEnd = $endDate->copy()->subMonth();
        }

        $prevExpenses = Transaction::where('user_id', $user->id)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->sum('amount');

        $change = $totalExpenses - $prevExpenses;
        $changePercent = $prevExpenses > 0 ? (($change / $prevExpenses) * 100) : 0;

        if ($change > 0) {
            $insights[] = [
                'icon' => '📈',
                'title' => 'Spending Increased',
                'value' => '+' . number_format($changePercent, 1) . '%',
                'description' => "You spent KES " . number_format($change, 0) . " more than last " . $type,
                'trend' => 'up'
            ];
        } else if ($change < 0) {
            $insights[] = [
                'icon' => '📉',
                'title' => 'Spending Decreased',
                'value' => number_format($changePercent, 1) . '%',
                'description' => "You spent KES " . number_format(abs($change), 0) . " less than last " . $type,
                'trend' => 'down'
            ];
        }

        // Biggest expense
        $biggestExpense = $transactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->sortByDesc('amount')
            ->first();

        if ($biggestExpense) {
            $insights[] = [
                'icon' => '💸',
                'title' => 'Biggest Expense',
                'value' => 'KES ' . number_format($biggestExpense->amount, 0),
                'description' => $biggestExpense->description . ' (' . $biggestExpense->category->name . ')'
            ];
        }

        // Savings rate
        $income = $transactions->filter(fn($t) => $t->category->type === 'income')->sum('amount');
        if ($income > 0) {
            $savingsRate = (($income - $totalExpenses) / $income) * 100;
            $insights[] = [
                'icon' => $savingsRate > 20 ? '🎯' : '⚠️',
                'title' => 'Savings Rate',
                'value' => number_format($savingsRate, 1) . '%',
                'description' => $savingsRate > 20
                    ? 'Great! You\'re saving well'
                    : 'Consider reducing expenses to save more'
            ];
        }

        return $insights;
    }

}
