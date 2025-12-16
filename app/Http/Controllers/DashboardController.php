<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // ============ FINANCIAL DASHBOARD DATA ============

        $totalAssets = Account::where('is_active', true)
            ->where('user_id', $userId)
            ->sum('current_balance');

        $totalLiabilities = Loan::where('status', 'active')
            ->where('user_id', $userId)
            ->sum('balance');

        $netWorth = $totalAssets - $totalLiabilities;
        $debtToAssetRatio = $totalAssets > 0 ? ($totalLiabilities / $totalAssets) * 100 : 0;

        $accounts = Account::where('is_active', true)
            ->where('user_id', $userId)
            ->get();

        $activeLoans = Loan::where('status', 'active')
            ->where('user_id', $userId)
            ->with('account')
            ->orderBy('due_date', 'asc')
            ->get();

        // ============ QUICK DASHBOARD DATA ============

        $totalToday = Transaction::where('user_id', $userId)
            ->whereDate('date', today())
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->sum('amount');

        $totalThisWeek = Transaction::where('user_id', $userId)
            ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->sum('amount');

        $totalThisMonth = Transaction::where('user_id', $userId)
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->sum('amount');

        $monthlyIncome = Transaction::where('user_id', $userId)
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->whereHas('category', fn($q) =>
            $q->where('type', 'income')
                ->whereNotIn('name', ['Loan Disbursement', 'Balance Adjustment'])
            )
            ->sum('amount');

        $remainingThisMonth = $monthlyIncome - $totalThisMonth;

        // ============ MONTHLY & YEARLY DATA ============

        $monthlyExpenses = Transaction::where('user_id', $userId)
            ->whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->sum('amount');

        $monthlyNet = $monthlyIncome - $monthlyExpenses;

        $yearlyIncome = Transaction::where('user_id', $userId)
            ->whereYear('date', $currentYear)
            ->whereHas('category', fn($q) =>
            $q->where('type', 'income')
                ->whereNotIn('name', ['Loan Disbursement', 'Balance Adjustment'])
            )
            ->sum('amount');

        $yearlyExpenses = Transaction::where('user_id', $userId)
            ->whereYear('date', $currentYear)
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->sum('amount');

        $yearlyNet = $yearlyIncome - $yearlyExpenses;

        // ============ SPENDING ANALYSIS ============

        $topExpenses = Transaction::where('user_id', $userId)
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->with('category')
            ->limit(5)
            ->get();

        $dailySpending = Transaction::where('user_id', $userId)
            ->select(DB::raw('DATE(date) as date'), DB::raw('SUM(amount) as total'))
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get();

        // ============ MONTH COMPARISON ============

        $lastMonthTotal = Transaction::where('user_id', $userId)
            ->whereMonth('date', now()->subMonth()->month)
            ->whereYear('date', now()->subMonth()->year)
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->sum('amount');

        $monthlyComparison = $totalThisMonth - $lastMonthTotal;
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
            'totalAssets',
            'totalLiabilities',
            'netWorth',
            'debtToAssetRatio',
            'accounts',
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
            'budgets'
        ));
    }
}
