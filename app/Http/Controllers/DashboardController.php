<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ============ FINANCIAL DASHBOARD DATA ============

        // ASSETS & LIABILITIES
        $totalAssets = Account::where('is_active', true)->sum('current_balance');
        $totalLiabilities = Loan::where('status', 'active')->sum('balance');
        $netWorth = $totalAssets - $totalLiabilities;
        $debtToAssetRatio = $totalAssets > 0 ? ($totalLiabilities / $totalAssets) * 100 : 0;

        // ACCOUNTS & LOANS
        $accounts = Account::where('is_active', true)->get();
        $activeLoans = Loan::where('status', 'active')
            ->with('account')
            ->orderBy('due_date', 'asc')
            ->get();

        // ============ QUICK DASHBOARD DATA ============

        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Quick Stats
        $totalToday = Transaction::whereDate('date', today())
            ->whereHas('category', function($q) {
                $q->where('type', 'expense');
            })
            ->sum('amount');

        $totalThisWeek = Transaction::whereBetween('date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])
            ->whereHas('category', function($q) {
                $q->where('type', 'expense');
            })
            ->sum('amount');

        // FIXED: Only sum EXPENSES for totalThisMonth
        $totalThisMonth = Transaction::whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->whereHas('category', function($q) {
                $q->where('type', 'expense');
            })
            ->sum('amount');

        // Monthly Income (exclude loan disbursements)
        $monthlyIncome = Transaction::whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->whereHas('category', function($q) {
                $q->where('type', 'income')
                    ->whereNotIn('name', ['Loan Disbursement', 'Balance Adjustment']);
            })
            ->sum('amount');

        $remainingThisMonth = $monthlyIncome - $totalThisMonth;

        // ============ MONTHLY & YEARLY DATA ============

        // Monthly Expenses
        $monthlyExpenses = Transaction::whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->whereHas('category', function($q) {
                $q->where('type', 'expense');
            })
            ->sum('amount');

        $monthlyNet = $monthlyIncome - $monthlyExpenses;

        // Yearly Totals
        $yearlyIncome = Transaction::whereYear('date', $currentYear)
            ->whereHas('category', function($q) {
                $q->where('type', 'income')
                    ->whereNotIn('name', ['Loan Disbursement', 'Balance Adjustment']);
            })
            ->sum('amount');

        $yearlyExpenses = Transaction::whereYear('date', $currentYear)
            ->whereHas('category', function($q) {
                $q->where('type', 'expense');
            })
            ->sum('amount');

        $yearlyNet = $yearlyIncome - $yearlyExpenses;

        // ============ SPENDING ANALYSIS ============

        // Top Expenses This Month
        $topExpenses = Transaction::select('category_id', DB::raw('SUM(amount) as total'))
            ->whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->whereHas('category', function($q) {
                $q->where('type', 'expense');
            })
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->with('category')
            ->limit(5)
            ->get();

        // Daily Spending This Month
        $dailySpending = Transaction::query()
            ->select(DB::raw('DATE(date) as date'), DB::raw('SUM(amount) as total'))
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->whereHas('category', function($q) {
                $q->where('type', 'expense');
            })
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get();

        // ============ MONTH COMPARISON ============

        $lastMonthTotal = Transaction::whereMonth('date', now()->subMonth()->month)
            ->whereYear('date', now()->subMonth()->year)
            ->whereHas('category', function($q) {
                $q->where('type', 'expense');
            })
            ->sum('amount');

        $monthlyComparison = $totalThisMonth - $lastMonthTotal;
        $monthlyComparisonPercent = $lastMonthTotal > 0
            ? round(($monthlyComparison / $lastMonthTotal) * 100, 1)
            : 0;

        // ============ RECENT TRANSACTIONS ============

        $recentTransactions = Transaction::with(['category', 'account'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
        // Financial Dashboard Data
            'totalAssets',
            'totalLiabilities',
            'netWorth',
            'debtToAssetRatio',
            'accounts',
            'activeLoans',

            // Quick Stats
            'totalToday',
            'totalThisWeek',
            'totalThisMonth',
            'monthlyIncome',
            'remainingThisMonth',

            // Monthly & Yearly
            'monthlyExpenses',
            'monthlyNet',
            'yearlyIncome',
            'yearlyExpenses',
            'yearlyNet',

            // Spending Analysis
            'topExpenses',
            'dailySpending',

            // Comparison
            'lastMonthTotal',
            'monthlyComparison',
            'monthlyComparisonPercent',

            // Transactions
            'recentTransactions'
        ));
    }
}
