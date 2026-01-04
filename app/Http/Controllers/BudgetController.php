<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function index(Request $request, $year = null)
    {
        $year = $year ?? date('Y');
        $currentMonth = date('n');

        // Calculate dynamic year range based on actual data
        $minYear = Transaction::where('user_id', Auth::id())
            ->min(DB::raw('YEAR(COALESCE(period_date, date))'));
        $minYear = $minYear ?? date('Y');
        $maxYear = date('Y') + 1;

        // Load ONLY true income categories (exclude loans and adjustments)
        $incomeCategories = Category::where('user_id', Auth::id())
            ->where('type', 'income')
            ->whereNotIn('name', ['Loan Disbursement', 'Loan Receipt', 'Balance Adjustment'])
            ->orderBy('name')
            ->get();

        // Load expense categories (include loan repayments)
        $expenseCategories = Category::where('user_id', Auth::id())
            ->where('type', 'expense')
            ->orderBy('name')
            ->get();

        // Load budgets for the year (auto-generated from transactions)
        $budgets = Budget::where('user_id', Auth::id())
            ->where('year', $year)
            ->get()
            ->keyBy(function($b) {
                return $b->category_id . '-' . $b->month;
            });

        // Compute actual totals grouped by category & month using period_date
        $actualsQuery = Transaction::query()
            ->selectRaw('category_id, MONTH(COALESCE(period_date, date)) as month, SUM(amount) as total')
            ->where('user_id', Auth::id())
            ->whereYear(DB::raw('COALESCE(period_date, date)'), $year)
            ->whereHas('category', function($q) {
                $q->whereIn('type', ['income', 'expense'])
                    ->whereNotIn('name', [
                        'Loan Disbursement',
                        'Loan Receipt',
                        'Balance Adjustment'
                    ]);
            })
            ->groupBy('category_id', DB::raw('MONTH(COALESCE(period_date, date))'))
            ->get();

        // Convert to lookup: [category_id][month] => total
        $actuals = [];
        foreach ($actualsQuery as $row) {
            $actuals[$row->category_id][$row->month] = (float)$row->total;
        }

        // Calculate yearly totals for income categories
        $incomeCategories = $incomeCategories->map(function($category) use ($actuals, $budgets) {
            $yearlyTotal = 0;
            $yearlyBudget = 0;
            for ($m = 1; $m <= 12; $m++) {
                $yearlyTotal += $actuals[$category->id][$m] ?? 0;
                $key = $category->id . '-' . $m;
                $yearlyBudget += $budgets->get($key)->amount ?? 0;
            }
            $category->yearly_total = $yearlyTotal;
            $category->yearly_budget = $yearlyBudget;
            $category->budget_percentage = $yearlyBudget > 0
                ? round(($yearlyTotal / $yearlyBudget) * 100, 1)
                : 0;
            return $category;
        })->sortByDesc('yearly_total');

        // Calculate yearly totals for expense categories
        $expenseCategories = $expenseCategories->map(function($category) use ($actuals, $budgets) {
            $yearlyTotal = 0;
            $yearlyBudget = 0;
            for ($m = 1; $m <= 12; $m++) {
                $yearlyTotal += $actuals[$category->id][$m] ?? 0;
                $key = $category->id . '-' . $m;
                $yearlyBudget += $budgets->get($key)->amount ?? 0;
            }
            $category->yearly_total = $yearlyTotal;
            $category->yearly_budget = $yearlyBudget;
            $category->budget_percentage = $yearlyBudget > 0
                ? round(($yearlyTotal / $yearlyBudget) * 100, 1)
                : 0;
            return $category;
        })->sortByDesc('yearly_total');

        // Get loan statistics for the year
        $loanStats = $this->getLoanStats($year);
        // Get accounts for the FAB component
        $accounts = \App\Models\Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('budgets.index', compact(
            'incomeCategories',
            'expenseCategories',
            'budgets',
            'actuals',
            'year',
            'currentMonth',
            'loanStats',
            'minYear',
            'maxYear',
            'accounts'
        ));
    }

    /**
     * Get loan statistics for display in budget
     */
    private function getLoanStats($year)
    {
        // Loans disbursed this year (principal amount)
        $loansDisbursed = Loan::where('user_id', Auth::id())
            ->whereYear('disbursed_date', $year)
            ->sum('principal_amount');

        // Loan repayments made this year
        $loanPayments = DB::table('loan_payments')
            ->join('loans', 'loan_payments.loan_id', '=', 'loans.id')
            ->where('loans.user_id', Auth::id())
            ->whereYear('loan_payments.payment_date', $year)
            ->sum('loan_payments.amount');

        // Active loan balance
        $activeLoanBalance = Loan::where('user_id', Auth::id())
            ->where('status', 'active')
            ->sum('balance');

        return [
            'disbursed' => $loansDisbursed,
            'payments' => $loanPayments,
            'active_balance' => $activeLoanBalance,
        ];
    }
}
