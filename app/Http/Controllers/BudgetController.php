<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function index(Request $request, $year = null)
    {
        $year = $year ?? date('Y');
        $currentMonth = date('n');

        // Calculate dynamic year range based on actual data
        $minYear = Transaction::min(DB::raw('YEAR(COALESCE(period_date, date))'));
        $minYear = $minYear ?? date('Y'); // Fallback if no transactions
        $maxYear = date('Y') + 1; // Allow planning for next year

        // Load ONLY true income categories (exclude loans and adjustments)
        $incomeCategories = Category::where('type', 'income')
            ->whereNotIn('name', ['Loan Disbursement', 'Loan Receipt', 'Balance Adjustment'])
            ->orderBy('name')
            ->get();

        // Load expense categories (include loan repayments)
        $expenseCategories = Category::where('type', 'expense')
            ->get();

        // Load budgets for the year
        $budgets = Budget::where('year', $year)
            ->get()
            ->keyBy(function($b) { return $b->category_id . '-' . $b->month; });

        // Compute actual totals grouped by category & month using period_date
        // This ensures Jan salary received in Dec counts toward January budget
        $actualsQuery = Transaction::query()
            ->selectRaw('category_id, MONTH(COALESCE(period_date, date)) as month, SUM(amount) as total')
            ->whereYear(DB::raw('COALESCE(period_date, date)'), $year)
            ->whereHas('category', function($q) {
                $q->whereIn('type', ['income', 'expense'])
                    ->whereNotIn('name', [
                        'Loan Disbursement',
                        'Loan Receipt',
                        'Balance Adjustment',
                        'Excise Duty'
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

        return view('budgets.index', compact(
            'incomeCategories',
            'expenseCategories',
            'budgets',
            'actuals',
            'year',
            'currentMonth',
            'loanStats',
            'minYear',
            'maxYear'
        ));
    }

    /**
     * Get loan statistics for display in budget
     * Shows loans disbursed, repayments made, and active balances
     */
    private function getLoanStats($year)
    {
        // Loans disbursed this year (principal amount)
        $loansDisbursed = Loan::whereYear('disbursed_date', $year)
            ->sum('principal_amount');

        // Loan repayments made this year (from LoanPayment records)
        $loanPayments = DB::table('loan_payments')
            ->whereYear('payment_date', $year)
            ->sum('amount');

        // Active loan balance (remaining to repay)
        $activeLoanBalance = Loan::where('status', 'active')
            ->sum('balance');

        return [
            'disbursed' => $loansDisbursed,
            'payments' => $loanPayments,
            'active_balance' => $activeLoanBalance,
        ];
    }

    public function updateBulk(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:2099',
            'budgets' => 'required|array',
        ]);

        $year = $data['year'];
        $upsertRows = [];
        $now = now();

        foreach ($data['budgets'] as $compositeKey => $vals) {
            $categoryId = $vals['category_id'] ?? null;
            $month = $vals['month'] ?? null;
            $amount = $vals['amount'] ?? 0;

            if (!$categoryId || !$month) continue;

            $amount = str_replace(',', '', $amount);
            $amount = $amount === '' ? 0 : $amount;

            $upsertRows[] = [
                'category_id' => $categoryId,
                'year' => $year,
                'month' => $month,
                'amount' => $amount,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        Budget::upsert($upsertRows, ['category_id','year','month'], ['amount','updated_at']);

        return redirect()->back()->with('success','Budgets saved.');
    }
}
