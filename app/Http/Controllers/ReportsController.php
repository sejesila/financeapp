<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\MobileMoneyTypeUsage;
use App\Models\Transaction;
use App\Services\ReportDataService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    /**
     * Category names that never represent real income or spending
     * (loan mechanics / balance adjustments / client passthrough),
     * mirrored from BudgetController::index()'s $actualsQuery and
     * DashboardController::NON_SPENDING_CATEGORY_NAMES.
     */
    private const NON_SPENDING_CATEGORY_NAMES = [
        'Loan Disbursement',
        'Loan Receipt',
        'Balance Adjustment',
        'Client Funds',
    ];

    /**
     * Additional category names excluded from *income* only, mirrored
     * from BudgetController::index()'s $incomeCategories filter and
     * DashboardController::NON_INCOME_ONLY_CATEGORY_NAMES.
     */
    private const NON_INCOME_ONLY_CATEGORY_NAMES = [
        'Friend Loan Given',
        'Loan Recovery',
    ];

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'this_month');
        $startDate = null;
        $endDate = null;

        // Apply date filters
        switch ($filter) {
            case 'this_month':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
            case 'last_month':
                $startDate = now()->subMonth()->startOfMonth();
                $endDate = now()->subMonth()->endOfMonth();
                break;
            case 'this_year':
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
                break;
            case 'last_year':
                $startDate = now()->subYear()->startOfYear();
                $endDate = now()->subYear()->endOfYear();
                break;
            case 'custom':
                $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->startOfMonth();
                $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now()->endOfMonth();
                break;
            default:
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
        }

        // 1. Spending by Category
        $spendingByCategory = $this->excludeNonSpendingCategories(
            $this->excludeClientFunds(
                Transaction::query()
                    ->join('categories', 'transactions.category_id', '=', 'categories.id')
                    ->select('categories.name', 'categories.type', DB::raw('SUM(transactions.amount) as total'))
                    ->whereBetween('transactions.date', [$startDate, $endDate])
                    ->where('transactions.user_id', auth()->id())
            )
        )
            ->groupBy('categories.id', 'categories.name', 'categories.type')
            ->orderByDesc('total')
            ->get();

        $expensesByCategory = $spendingByCategory->where('type', 'expense');
        $incomeByCategory = $spendingByCategory->where('type', 'income');

        // 2. Cash Flow Summary
        $totalIncome = $incomeByCategory->sum('total');
        $totalExpenses = $expensesByCategory->sum('total');
        $netCashFlow = $totalIncome - $totalExpenses;

        // 3. Top Spending Categories
        $topCategories = $expensesByCategory->take(5);

        // 4. Previous Period Comparison
        $previousStartDate = null;
        $previousEndDate = null;

        switch ($filter) {
            case 'this_month':
                $previousStartDate = now()->subMonth()->startOfMonth();
                $previousEndDate = now()->subMonth()->endOfMonth();
                break;
            case 'last_month':
                $previousStartDate = now()->subMonths(2)->startOfMonth();
                $previousEndDate = now()->subMonths(2)->endOfMonth();
                break;
            case 'this_year':
                $previousStartDate = now()->subYear()->startOfYear();
                $previousEndDate = now()->subYear()->endOfYear();
                break;
        }

        $previousExpenses = 0;

        if ($previousStartDate) {
            $previousExpenses = $this->excludeNonSpendingCategories(
                $this->excludeClientFunds(
                    Transaction::query()
                        ->join('categories', 'transactions.category_id', '=', 'categories.id')
                        ->where('categories.type', 'expense')
                        ->whereBetween('transactions.date', [$previousStartDate, $previousEndDate])
                        ->where('transactions.user_id', auth()->id())
                )
            )->sum('transactions.amount');
        }

        $expenseChange = $previousExpenses > 0
            ? round((($totalExpenses - $previousExpenses) / $previousExpenses) * 100, 1)
            : 0;

        // 5. Transaction Type Usage Statistics
        $transactionTypeStats = $this->getTransactionTypeStats($startDate, $endDate);

        // Get accounts for the FAB component
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $reportDataService = app(ReportDataService::class);
        $salarySavingsRate = $reportDataService->getSalarySavingsRate(
            auth()->user(), $startDate, $endDate
        );

        return view('reports.index', compact(
            'filter',
            'startDate',
            'endDate',
            'expensesByCategory',
            'incomeByCategory',
            'totalIncome',
            'totalExpenses',
            'netCashFlow',
            'topCategories',
            'previousExpenses',
            'expenseChange',
            'transactionTypeStats',
            'accounts',
            'salarySavingsRate'
        ));
    }

    /**
     * Get transaction type usage statistics for the current date range
     */
    private function getTransactionTypeStats($startDate, $endDate)
    {
        // Get M-Pesa transaction type usage for the period
        $mpesaStats = Transaction::query()
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->select(
                'transactions.mobile_money_type as type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(transactions.amount) as total')
            )
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->where('transactions.user_id', Auth::id())
            ->where('accounts.type', 'mpesa')
            ->whereNotNull('transactions.mobile_money_type')
            ->groupBy('transactions.mobile_money_type')
            ->orderByDesc('count')
            ->get();

        // Get Airtel Money transaction type usage for the period
        $airtelStats = Transaction::query()
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->select(
                'transactions.mobile_money_type as type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(transactions.amount) as total')
            )
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->where('transactions.user_id', Auth::id())
            ->where('accounts.type', 'airtel_money')
            ->whereNotNull('transactions.mobile_money_type')
            ->groupBy('transactions.mobile_money_type')
            ->orderByDesc('count')
            ->get();

        // Get overall usage frequency (not limited by date)
        $mpesaUsageFrequency = MobileMoneyTypeUsage::where('user_id', Auth::id())
            ->where('account_type', 'mpesa')
            ->orderByDesc('usage_count')
            ->get();

        $airtelUsageFrequency = MobileMoneyTypeUsage::where('user_id', Auth::id())
            ->where('account_type', 'airtel_money')
            ->orderByDesc('usage_count')
            ->get();

        return [
            'mpesa' => [
                'period' => $mpesaStats,
                'frequency' => $mpesaUsageFrequency,
            ],
            'airtel_money' => [
                'period' => $airtelStats,
                'frequency' => $airtelUsageFrequency,
            ],
        ];
    }

    /**
     * Helper to format transaction type names
     */
    private function formatTransactionType($type)
    {
        return match ($type) {
            'send_money' => 'Send Money',
            'paybill' => 'PayBill',
            'buy_goods' => 'Buy Goods/Till',
            'pochi_la_biashara' => 'Pochi La Biashara',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Exclude transactions that represent client-fund pass-through money
     * (i.e. not the user's own income/spending) from a query — but keep
     * Client Commission transactions booked to an income category, since
     * those are real earned income, not passthrough.
     *
     * Mirrors the exclusion applied in BudgetController::index()'s
     * $actualsQuery and DashboardController::excludeClientFunds() so
     * report totals stay consistent with the budgets, dashboard, and
     * transactions summary views.
     *
     * Note: unlike Dashboard/Budget (which rely on whereHas/relations and
     * therefore need a correlated EXISTS against `categories`), every query
     * this method is applied to already joins `categories` directly, so we
     * can reference `categories.type` inline instead of a subquery.
     */
    private function excludeClientFunds(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('transactions.payment_method', '!=', 'Client Fund')
                    ->where('transactions.payment_method', '!=', 'Client Commission')
                    ->orWhereNull('transactions.payment_method');
            })
                ->orWhere(function ($q3) {
                    $q3->where('categories.type', 'income')
                        ->where('transactions.payment_method', 'Client Commission');
                });
        });
    }

    /**
     * Exclude categories that don't represent real spending or income
     * (loan mechanics, balance adjustments, client passthrough), plus the
     * extra names that only get stripped from *income* totals (Friend Loan
     * Given, Loan Recovery are loan mechanics, not earned income).
     *
     * Mirrors BudgetController::index()'s $actualsQuery / $incomeCategories
     * filters and DashboardController::excludeNonSpending() /
     * excludeNonIncome(). Since the queries here mix income and expense
     * rows together (grouped by category, split afterwards), both name
     * lists are excluded up front — the income-only names don't collide
     * with real expense category names.
     */
    private function excludeNonSpendingCategories(Builder $query): Builder
    {
        return $query->whereNotIn('categories.name', array_merge(
            self::NON_SPENDING_CATEGORY_NAMES,
            self::NON_INCOME_ONLY_CATEGORY_NAMES
        ));
    }
}
