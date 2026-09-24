<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    /**
     * A withdrawal from savings that gets re-deposited back into savings
     * (through the same intermediate wallet) within this many days is
     * treated as a reversal, not real spending — same 8-day window used
     * for the salary→savings reversal check in ReportDataService, for
     * consistency across the app.
     */
    private const SAVINGS_REVERSAL_WINDOW_DAYS = 8;

    /**
     * Loan principal movements are never real income/expense — lending money
     * converts cash into a receivable, and getting it back converts the
     * receivable back into cash. Only interest (a separate, dedicated
     * category — see LoanGivenController::splitInterestOutOfFinalPayment)
     * is real profit and is allowed to show under Income.
     */
    private const EXCLUDED_LOAN_CATEGORIES = [
        'Loan Disbursement', 'Loan Receipt', 'Balance Adjustment',
        'Friend Loan Given', 'Loan Recovery',
        // 'Loan Interest' intentionally NOT excluded here — Budget is the one
        // view that shows every real income/expense category unfiltered, with
        // no separate "Interest Income" card the way Reports/PDF reports have.
        // Excluding it would make closed loan-given interest invisible on
        // this page entirely, not just move it elsewhere. See LoanGivenTest
        // > 'excludes principal recovery but includes Loan Interest as real
        // income once closed' for the asserted behavior.
    ];
    private const EXCLUDED_ROLLING_FUND_CATEGORIES = [
        'Rolling Funds',
    ];

    /**
     * Expense categories that count as "Wants" under the 50/30/20 rule. Any
     * expense category NOT listed here defaults to "Needs" — see
     * calculate503020Breakdown() below. Fare, Airtime & Data, Rent, Groceries,
     * School Fees & Supplies, Electricity, Cooking Gas, and Transaction Fees
     * are deliberately NOT here (they're Needs, via the default). This is
     * intentionally a hardcoded list for now (same pattern as
     * EXCLUDED_LOAN_CATEGORIES above) rather than a categories.budget_group
     * column, so the buckets can be tuned here without a migration while the
     * numbers get validated against real data.
     */
    private const WANTS_CATEGORY_NAMES = [
        'Family',
        'Other Expenses',
        'Better Half',
        'Clothing',
        'Loan Repayment',
    ];

    public function index(Request $request, $year = null)
    {
        $year = $year ?? date('Y');
        $currentMonth = (int) date('n');

        // Calculate dynamic year range based on actual data
        $minYear = Transaction::where('user_id', Auth::id())
            ->min(DB::raw('YEAR(COALESCE(period_date, date))'));
        $minYear = $minYear ?? date('Y');
        $maxYear = date('Y') + 1;

        $incomeCategories = Category::where('user_id', Auth::id())
            ->where('type', 'income')
            ->whereNotIn('name', array_merge(self::EXCLUDED_LOAN_CATEGORIES, self::EXCLUDED_ROLLING_FUND_CATEGORIES))
            ->orderBy('name')
            ->get();

        $expenseCategories = Category::where('user_id', Auth::id())
            ->where('type', 'expense')
            ->whereNotIn('name', array_merge(self::EXCLUDED_LOAN_CATEGORIES, self::EXCLUDED_ROLLING_FUND_CATEGORIES))
            ->orderBy('name')
            ->get();

        // Load budgets for the year
        $budgets = Budget::where('user_id', Auth::id())
            ->where('year', $year)
            ->get()
            ->keyBy(function ($b) {
                return $b->category_id . '-' . $b->month;
            });

        // Compute actual totals excluding client fund transactions
        $actualsQuery = Transaction::query()
            ->selectRaw('category_id, MONTH(COALESCE(period_date, date)) as month, SUM(amount) as total')
            ->where('user_id', Auth::id())
            ->whereYear(DB::raw('COALESCE(period_date, date)'), $year)
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
                $q->whereIn('type', ['income', 'expense'])
                    ->whereNotIn('name', array_merge(
                        self::EXCLUDED_LOAN_CATEGORIES,
                        self::EXCLUDED_ROLLING_FUND_CATEGORIES,
                        ['Client Funds']
                    ));
            })
            ->groupBy('category_id', DB::raw('MONTH(COALESCE(period_date, date))'))
            ->get();

        // Convert to lookup: [category_id][month] => total
        $actuals = [];
        foreach ($actualsQuery as $row) {
            $actuals[$row->category_id][$row->month] = (float)$row->total;
        }

        // Calculate yearly totals for income categories
        $incomeCategories = $incomeCategories->map(function ($category) use ($actuals, $budgets) {
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
        })
            ->filter(fn($c) => $c->yearly_total > 0)
            ->sortByDesc('yearly_total');

        // Calculate yearly totals for expense categories
        $expenseCategories = $expenseCategories->map(function ($category) use ($actuals, $budgets) {
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
        })
            ->filter(fn($c) => $c->yearly_total > 0)
            ->sortByDesc('yearly_total');
        // Calculate yearly totals for expense categories
        $wantsSet = $this->wantsCategoryNameSet();

        $expenseCategories = $expenseCategories->map(function ($category) use ($actuals, $budgets, $wantsSet) {
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
            // Tags this category for the 50/30/20 color-coding in the table —
            // see wantsCategoryNameSet() / WANTS_CATEGORY_NAMES above.
            $category->rule_group = $wantsSet->has(strtolower($category->name)) ? 'wants' : 'needs';
            return $category;
        })
            ->filter(fn($c) => $c->yearly_total > 0)
            ->sortByDesc('yearly_total');

        // Each category's share of its own type's yearly total — income
        // categories are compared against total yearly income, expense
        // categories against total yearly expenses, so the two sides never
        // get mixed into one misleading denominator.
        $incomeYearlyTotal = $incomeCategories->sum('yearly_total');
        $incomeCategories = $incomeCategories->map(function ($category) use ($incomeYearlyTotal) {
            $category->yearly_percentage = $incomeYearlyTotal > 0
                ? round(($category->yearly_total / $incomeYearlyTotal) * 100, 1)
                : 0;
            return $category;
        });

        $expenseYearlyTotal = $expenseCategories->sum('yearly_total');
        $expenseCategories = $expenseCategories->map(function ($category) use ($expenseYearlyTotal) {
            $category->yearly_percentage = $expenseYearlyTotal > 0
                ? round(($category->yearly_total / $expenseYearlyTotal) * 100, 1)
                : 0;
            return $category;
        });

        // Categories under 0.05% of their type's yearly total are noise as
        // individual line items — this filter only trims which rows are
        // *displayed*; $incomeCategories/$expenseCategories (used for the
        // TOTAL INCOME/EXPENSES rows) keep every category so totals stay
        // exact.
        $incomeCategoriesDisplay = $incomeCategories
            ->filter(fn($c) => $incomeYearlyTotal <= 0 || ($c->yearly_total / $incomeYearlyTotal) * 100 >= 0.05);

        $expenseCategoriesDisplay = $expenseCategories
            ->filter(fn($c) => $expenseYearlyTotal <= 0 || ($c->yearly_total / $expenseYearlyTotal) * 100 >= 0.05);

        // Get loan statistics for the year
        $loanStats = $this->getLoanStats($year);

        // Get savings withdrawals by month, netted against any amount that
        // was actually a savings→savings hop through an intermediate account
        // (e.g. Sanlam → M-Pesa → Etica), not real spending, and against
        // any withdrawal reversed back into savings within the window.
        $savingsWithdrawals = $this->calculateNetSavingsWithdrawals($year);

        // 50/30/20 rule breakdown per month — see calculate503020Breakdown()
        // for how each bucket is derived.
        $budgetRule = $this->calculate503020Breakdown($year);

        // Get accounts for the FAB component
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('budgets.index', compact(
            'incomeCategories',
            'expenseCategories',
            'incomeCategoriesDisplay',
            'expenseCategoriesDisplay',
            'budgets',
            'actuals',
            'year',
            'currentMonth',
            'loanStats',
            'savingsWithdrawals',
            'minYear',
            'maxYear',
            'accounts',
            'budgetRule'
        ));
    }

    /**
     * Save (create or update) a single budget cell value.
     * Called via AJAX from the inline budget input.
     *
     * POST /budgets/save-cell
     * Body: { category_id, year, month, amount }
     */
    public function saveCell(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'amount' => 'required|numeric|min:0',
        ]);

        // Ensure the category belongs to the authenticated user
        $category = Category::where('id', $validated['category_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $budget = Budget::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'category_id' => $validated['category_id'],
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            [
                'amount' => $validated['amount'],
            ]
        );

        return response()->json([
            'success' => true,
            'budget' => $budget,
        ]);
    }

    /**
     * Get loan statistics for display in budget.
     */
    private function getLoanStats($year)
    {
        $loansDisbursed = Loan::where('user_id', Auth::id())
            ->whereYear('disbursed_date', $year)
            ->sum('principal_amount');

        $loanPayments = DB::table('loan_payments')
            ->join('loans', 'loan_payments.loan_id', '=', 'loans.id')
            ->where('loans.user_id', Auth::id())
            ->whereYear('loan_payments.payment_date', $year)
            ->sum('loan_payments.amount');

        $activeLoanBalance = Loan::where('user_id', Auth::id())
            ->where('status', 'active')
            ->sum('balance');

        return [
            'disbursed' => $loansDisbursed,
            'payments' => $loanPayments,
            'active_balance' => $activeLoanBalance,
        ];
    }

    /**
     * Calculate monthly "Savings Used" figures, netting out any transfer
     * that was really a savings→savings move routed through an intermediate
     * wallet — since Savings accounts can't transfer to each other directly
     * (see TransferService::enforceTransferRules()), moving money between
     * two savings accounts always looks like: Savings A → Wallet → Savings B.
     *
     * Also nets out any withdrawal that was simply reversed — moved back
     * into savings through the same intermediate account — within an
     * 8-day window, even if that reversal isn't an immediate same-day hop.
     * Without this, a withdrawal that gets undone a few days later would
     * still be counted as spending, overstating "Savings Used".
     *
     * Withdrawals flagged as a client fund OR as lending are excluded
     * entirely up front — they're not the account owner's personal spend,
     * so they never enter the reversal-matching process below at all.
     *
     * Approach:
     *   1. Collect every "savings → non-savings" transfer (a candidate
     *      withdrawal) and every "non-savings → savings" transfer (a
     *      candidate re-deposit) for the year.
     *   2. For each withdrawal, look for re-deposits through the SAME
     *      intermediate account within SAVINGS_REVERSAL_WINDOW_DAYS and
     *      consume their amounts against the withdrawal (oldest-deposit-
     *      first, partial matches allowed).
     *   3. Only the unmatched remainder of each withdrawal counts as real
     *      "Savings Used" for that month.
     *
     * This covers both:
     *   - Same-day hops (Savings A → Wallet → Savings B), and
     *   - Slower reversals (withdraw, then change your mind and put it
     *     back within the week), without needing separate logic for each.
     */
    private function calculateNetSavingsWithdrawals(int $year): Collection
    {
        $withdrawals = DB::table('transfers')
            ->join('accounts as from_acc', 'transfers.from_account_id', '=', 'from_acc.id')
            ->join('accounts as to_acc', 'transfers.to_account_id', '=', 'to_acc.id')
            ->where('transfers.user_id', Auth::id())
            ->whereYear('transfers.date', $year)
            ->where('from_acc.type', 'savings')
            ->where('to_acc.type', '!=', 'savings')
            ->where('transfers.is_client_fund', false)
            ->where('transfers.is_lending', false)
            ->select(
                'transfers.id',
                'transfers.to_account_id as intermediate_account_id',
                'transfers.amount',
                'transfers.date'
            )
            ->orderBy('transfers.date')
            ->get();

        if ($withdrawals->isEmpty()) {
            return collect();
        }

        $deposits = DB::table('transfers')
            ->join('accounts as from_acc', 'transfers.from_account_id', '=', 'from_acc.id')
            ->join('accounts as to_acc', 'transfers.to_account_id', '=', 'to_acc.id')
            ->where('transfers.user_id', Auth::id())
            ->whereYear('transfers.date', $year)
            ->where('from_acc.type', '!=', 'savings')
            ->where('to_acc.type', 'savings')
            ->where('transfers.is_client_fund', false)
            ->select(
                'transfers.id',
                'transfers.from_account_id as intermediate_account_id',
                'transfers.amount',
                'transfers.date'
            )
            ->orderBy('transfers.date')
            ->get()
            ->map(fn($d) => (object)[
                'id' => $d->id,
                'intermediate_account_id' => $d->intermediate_account_id,
                'date' => Carbon::parse($d->date),
                'remaining' => (float)$d->amount,
            ])
            ->keyBy('id');

        $netByMonth = [];

        foreach ($withdrawals as $w) {
            $withdrawalDate = Carbon::parse($w->date);
            $reversalWindowEnd = $withdrawalDate->copy()->addDays(self::SAVINGS_REVERSAL_WINDOW_DAYS);
            $remainingToMatch = (float)$w->amount;

            $candidates = $deposits
                ->filter(fn($d) => $d->intermediate_account_id === $w->intermediate_account_id
                    && $d->remaining > 0
                    && $d->date->greaterThanOrEqualTo($withdrawalDate)
                    && $d->date->lessThanOrEqualTo($reversalWindowEnd)
                )
                ->sortBy('date');

            foreach ($candidates as $d) {
                if ($remainingToMatch <= 0) {
                    break;
                }

                $matched = min($remainingToMatch, $d->remaining);
                $remainingToMatch -= $matched;
                $deposits[$d->id]->remaining -= $matched;
            }

            $month = $withdrawalDate->month;
            $netByMonth[$month] = ($netByMonth[$month] ?? 0) + $remainingToMatch;
        }

        return collect($netByMonth)
            ->map(fn($total, $month) => (object)[
                'month' => $month,
                'total' => $total,
            ])
            ->keyBy('month');
    }

    /**
     * 50/30/20 breakdown per month for the given year: Needs (expense
     * categories not in WANTS_CATEGORY_NAMES), Wants (expense categories in
     * WANTS_CATEGORY_NAMES), and Savings.
     *
     * Savings is NOT a category sum — this app tracks savings as transfers
     * into the dedicated Etica savings account specifically (see
     * ReportDataService::isEticaAccount() / getSalarySavingsRate() and
     * calculateNetSavingsWithdrawals() above), so "Savings" here is net money
     * that actually moved into Etica this month (deposits minus genuine
     * withdrawals), with client-fund and lending transfers excluded — same
     * rules as calculateNetSavingsWithdrawals(). Deliberately scoped to Etica
     * by name, NOT to every account of type 'savings' — a second savings-type
     * account (e.g. Sanlam MMF) must never be counted here, or "Savings Used"
     * ends up inflated by money that never actually left for genuine savings.
     *
     * Uses the same exclusion constants and payment_method/Client Commission
     * handling as index()'s $actualsQuery, so figures here always agree with
     * the main budget table's TOTAL INCOME / TOTAL EXPENSES rows.
     *
     * Returns a collection keyed by month (1-12), each value an object with
     * income, needs, wants, savings (amounts) and needs_pct/wants_pct/
     * savings_pct (share of that month's income) plus needs_target/
     * wants_target/savings_target (50/30/20 of that month's income).
     */
    private function calculate503020Breakdown(int $year): Collection
    {
        $wantsSet = $this->wantsCategoryNameSet();

        $expenseCategoryGroup = Category::where('user_id', Auth::id())
            ->where('type', 'expense')
            ->whereNotIn('name', array_merge(self::EXCLUDED_LOAN_CATEGORIES, self::EXCLUDED_ROLLING_FUND_CATEGORIES))
            ->get()
            ->mapWithKeys(fn($c) => [$c->id => $wantsSet->has(strtolower($c->name)) ? 'wants' : 'needs']);

        $expenseActuals = Transaction::query()
            ->selectRaw('category_id, MONTH(COALESCE(period_date, date)) as month, SUM(amount) as total')
            ->where('user_id', Auth::id())
            ->whereYear(DB::raw('COALESCE(period_date, date)'), $year)
            ->where(function ($q) {
                $q->where('payment_method', '!=', 'Client Fund')
                    ->where('payment_method', '!=', 'Client Commission')
                    ->orWhereNull('payment_method');
            })
            ->whereHas('category', function ($q) {
                $q->where('type', 'expense')
                    ->whereNotIn('name', array_merge(
                        self::EXCLUDED_LOAN_CATEGORIES,
                        self::EXCLUDED_ROLLING_FUND_CATEGORIES,
                        ['Client Funds']
                    ));
            })
            ->groupBy('category_id', DB::raw('MONTH(COALESCE(period_date, date))'))
            ->get();

        $needsByMonth = array_fill(1, 12, 0.0);
        $wantsByMonth = array_fill(1, 12, 0.0);

        foreach ($expenseActuals as $row) {
            $group = $expenseCategoryGroup[$row->category_id] ?? 'needs';
            if ($group === 'wants') {
                $wantsByMonth[$row->month] += (float)$row->total;
            } else {
                $needsByMonth[$row->month] += (float)$row->total;
            }
        }

        $incomeByMonth = Transaction::query()
            ->selectRaw('MONTH(COALESCE(period_date, date)) as month, SUM(amount) as total')
            ->where('user_id', Auth::id())
            ->whereYear(DB::raw('COALESCE(period_date, date)'), $year)
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
                $q->where('type', 'income')
                    ->whereNotIn('name', array_merge(self::EXCLUDED_LOAN_CATEGORIES, self::EXCLUDED_ROLLING_FUND_CATEGORIES));
            })
            ->groupBy(DB::raw('MONTH(COALESCE(period_date, date))'))
            ->pluck('total', 'month');

        // Scoped to Etica specifically — see the method docblock above. A second
        // savings-type account (e.g. Sanlam MMF) must never contribute here.
        $savingsIn = DB::table('transfers')
            ->join('accounts as to_acc', 'transfers.to_account_id', '=', 'to_acc.id')
            ->where('transfers.user_id', Auth::id())
            ->whereYear('transfers.date', $year)
            ->where('to_acc.type', 'savings')
            ->whereRaw("LOWER(to_acc.name) LIKE '%etica%'")
            ->where('transfers.is_client_fund', false)
            ->selectRaw('MONTH(transfers.date) as month, SUM(transfers.amount) as total')
            ->groupBy(DB::raw('MONTH(transfers.date)'))
            ->pluck('total', 'month');

        $savingsOut = DB::table('transfers')
            ->join('accounts as from_acc', 'transfers.from_account_id', '=', 'from_acc.id')
            ->where('transfers.user_id', Auth::id())
            ->whereYear('transfers.date', $year)
            ->where('from_acc.type', 'savings')
            ->whereRaw("LOWER(from_acc.name) LIKE '%etica%'")
            ->where('transfers.is_client_fund', false)
            ->where('transfers.is_lending', false)
            ->selectRaw('MONTH(transfers.date) as month, SUM(transfers.amount) as total')
            ->groupBy(DB::raw('MONTH(transfers.date)'))
            ->pluck('total', 'month');

        $breakdown = collect();

        for ($m = 1; $m <= 12; $m++) {
            $income  = (float)($incomeByMonth[$m] ?? 0);
            $needs   = $needsByMonth[$m];
            $wants   = $wantsByMonth[$m];
            $savings = max(0, (float)($savingsIn[$m] ?? 0) - (float)($savingsOut[$m] ?? 0));

            $breakdown->put($m, (object)[
                'income'         => $income,
                'needs'          => $needs,
                'wants'          => $wants,
                'savings'        => $savings,
                'needs_pct'      => $income > 0 ? round(($needs / $income) * 100, 1) : 0,
                'wants_pct'      => $income > 0 ? round(($wants / $income) * 100, 1) : 0,
                'savings_pct'    => $income > 0 ? round(($savings / $income) * 100, 1) : 0,
                'needs_target'   => round($income * 0.50, 0),
                'wants_target'   => round($income * 0.30, 0),
                'savings_target' => round($income * 0.20, 0),
            ]);
        }

        return $breakdown;
    }
    /**
     * Lowercased set of WANTS_CATEGORY_NAMES for fast lookup. Shared by
     * calculate503020Breakdown() and index()'s category-group tagging, so the
     * table's color-coding and the 50/30/20 card's totals are always based on
     * the exact same classification.
     */
    private function wantsCategoryNameSet(): Collection
    {
        return collect(self::WANTS_CATEGORY_NAMES)
            ->map(fn($n) => strtolower($n))
            ->flip();
    }
}
