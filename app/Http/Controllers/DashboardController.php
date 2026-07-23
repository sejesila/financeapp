<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Category names that never represent real income or spending
     * (loan mechanics / balance adjustments / client passthrough),
     * mirrored from BudgetController::index()'s $actualsQuery.
     */
    private const NON_SPENDING_CATEGORY_NAMES = [
        'Loan Disbursement',
        'Loan Receipt',
        'Balance Adjustment',
        'Client Funds',
    ];

    /**
     * Additional category names excluded from *income* only, mirrored
     * from BudgetController::index()'s $incomeCategories filter.
     */
    private const NON_INCOME_ONLY_CATEGORY_NAMES = [
        'Friend Loan Given',
        'Loan Recovery',
    ];

    public function index()
    {
        $userId       = Auth::id();
        $currentMonth = now()->month;
        $currentYear  = now()->year;

        // ============ ACCOUNTS ============

        $accounts = Account::where('is_active', true)
            ->where('user_id', $userId)
            ->whereIn('type', ['cash', 'mpesa', 'airtel_money', 'bank'])
            ->get();

        $savingsAccounts = Account::where('is_active', true)
            ->where('user_id', $userId)
            ->where('type', 'savings')
            ->get();

        $walletAccounts = Account::where('is_active', true)
            ->where('user_id', $userId)
            ->where('type', 'wallet')
            ->get();

        // ============ FINANCIAL OVERVIEW ============

        $totalAssets = $accounts->sum('current_balance')
            + $walletAccounts->sum('current_balance');

        $totalSavings = $savingsAccounts->sum('current_balance');

        $totalLiabilities = Loan::where('status', 'active')
            ->where('user_id', $userId)
            ->sum('balance');

        $netWorth        = $totalAssets - $totalLiabilities;
        $debtToAssetRatio = $totalAssets > 0
            ? ($totalLiabilities / $totalAssets) * 100
            : 0;

        $activeLoans = Loan::where('status', 'active')
            ->where('user_id', $userId)
            ->with('account')
            ->orderBy('due_date', 'asc')
            ->get();

        // ============ QUICK STATS ============

        $totalToday = $this->excludeNonSpending(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->whereDate('date', today())
                    ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            )
        )->sum('amount');

        $totalThisWeek = $this->excludeNonSpending(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            )
        )->sum('amount');

        $totalThisMonth = $this->excludeNonSpending(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->whereMonth('date', $currentMonth)
                    ->whereYear('date', $currentYear)
                    ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            )
        )->sum('amount');

        $monthlyIncome = $this->excludeNonIncome(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->whereMonth('date', $currentMonth)
                    ->whereYear('date', $currentYear)
                    ->whereHas('category', fn($q) => $q->where('type', 'income'))
            )
        )->sum('amount');

        $remainingThisMonth = $monthlyIncome - $totalThisMonth;

        // ============ MONTHLY & YEARLY OVERVIEW ============

        $monthlyExpenses = $this->excludeNonSpending(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->whereYear('date', $currentYear)
                    ->whereMonth('date', $currentMonth)
                    ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            )
        )->sum('amount');

        $monthlyNet = $monthlyIncome - $monthlyExpenses;

        $yearlyIncome = $this->excludeNonIncome(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->whereYear('date', $currentYear)
                    ->whereHas('category', fn($q) => $q->where('type', 'income'))
            )
        )->sum('amount');

        $yearlyExpenses = $this->excludeNonSpending(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->whereYear('date', $currentYear)
                    ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            )
        )->sum('amount');

        $yearlyNet = $yearlyIncome - $yearlyExpenses;

        // ============ SPENDING ANALYSIS ============

        $topExpenses = $this->excludeNonSpending(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->whereYear('date', $currentYear)
                    ->whereMonth('date', $currentMonth)
                    ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            )
        )
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(function ($group) {
                $first        = $group->first();
                $first->total = $group->sum('amount');
                return $first;
            })
            ->sortByDesc('total')
            ->take(5);

        $dailySpending = $this->excludeNonSpending(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->select(DB::raw('DATE(date) as date'), DB::raw('SUM(amount) as total'))
                    ->whereMonth('date', $currentMonth)
                    ->whereYear('date', $currentYear)
                    ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            )
        )
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get();

        // ============ MONTH COMPARISON ============

        $lastMonthTotal = $this->excludeNonSpending(
            $this->excludeClientFunds(
                Transaction::where('user_id', $userId)
                    ->whereMonth('date', now()->subMonth()->month)
                    ->whereYear('date', now()->subMonth()->year)
                    ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            )
        )->sum('amount');

        $monthlyComparison        = $totalThisMonth - $lastMonthTotal;
        $monthlyComparisonPercent = $lastMonthTotal > 0
            ? round(($monthlyComparison / $lastMonthTotal) * 100, 1)
            : 0;

        // ============ RECENT TRANSACTIONS ============

        $recentTransactions = Transaction::with(['category', 'account'])
            ->where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // ============ BUDGETS ============

        $budgets = Budget::where('user_id', $userId)
            ->where('year', $currentYear)
            ->where('month', $currentMonth)
            ->with('category')
            ->get();

        return view('dashboard.index', compact(
            'accounts',
            'savingsAccounts',
            'walletAccounts',
            'totalAssets',
            'totalSavings',
            'totalLiabilities',
            'netWorth',
            'debtToAssetRatio',
            'activeLoans',
            'totalToday',
            'totalThisWeek',
            'totalThisMonth',
            'monthlyIncome',
            'remainingThisMonth',
            'monthlyExpenses',
            'monthlyNet',
            'yearlyIncome',
            'yearlyExpenses',
            'yearlyNet',
            'topExpenses',
            'dailySpending',
            'lastMonthTotal',
            'monthlyComparison',
            'monthlyComparisonPercent',
            'recentTransactions',
            'budgets',
        ));
    }

    /**
     * Exclude transactions that represent client-fund pass-through money
     * (i.e. not the user's own income/spending) from a query — but keep
     * Client Commission transactions booked to an income category, since
     * those are real earned income, not passthrough.
     *
     * Mirrors the exclusion applied in BudgetController::index()'s
     * $actualsQuery so dashboard totals stay consistent with the budgets
     * and transactions summary views.
     */
    private function excludeClientFunds(Builder $query): Builder
    {
        return $query->where(function ($q) {
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
        });
    }

    /**
     * Exclude categories that don't represent real spending or income
     * (loan mechanics, balance adjustments, client passthrough).
     * Mirrors BudgetController::index()'s $actualsQuery category filter.
     */
    private function excludeNonSpending(Builder $query): Builder
    {
        return $query->whereHas('category', function ($q) {
            $q->whereNotIn('name', self::NON_SPENDING_CATEGORY_NAMES);
        });
    }

    /**
     * Same as excludeNonSpending, plus the extra names BudgetController
     * strips specifically from its $incomeCategories list (Friend Loan
     * Given, Loan Recovery are loan mechanics, not earned income).
     */
    private function excludeNonIncome(Builder $query): Builder
    {
        return $query->whereHas('category', function ($q) {
            $q->whereNotIn('name', array_merge(
                self::NON_SPENDING_CATEGORY_NAMES,
                self::NON_INCOME_ONLY_CATEGORY_NAMES
            ));
        });
    }
}
