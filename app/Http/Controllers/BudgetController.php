<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    /** Hop must complete within this many hours to be treated as a
     *  savings→savings transfer routed through an intermediate account,
     *  rather than a genuine withdrawal for spending. */
    private const SAVINGS_HOP_WINDOW_HOURS = 24;

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
                $q->whereIn('type', ['income', 'expense'])
                    ->whereNotIn('name', [
                        'Loan Disbursement',
                        'Loan Receipt',
                        'Balance Adjustment',
                        'Client Funds',
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

        // Get loan statistics for the year
        $loanStats = $this->getLoanStats($year);

        // Get savings withdrawals by month, netted against any amount that
        // was actually a savings→savings hop through an intermediate account
        // (e.g. Sanlam → M-Pesa → Etica), not real spending.
        $savingsWithdrawals = $this->calculateNetSavingsWithdrawals($year);

        // Get accounts for the FAB component
        $accounts = Account::where('user_id', Auth::id())
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
            'savingsWithdrawals',
            'minYear',
            'maxYear',
            'accounts'
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
            'year'        => 'required|integer|min:2000|max:2100',
            'month'       => 'required|integer|min:1|max:12',
            'amount'      => 'required|numeric|min:0',
        ]);

        // Ensure the category belongs to the authenticated user
        $category = Category::where('id', $validated['category_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $budget = Budget::updateOrCreate(
            [
                'user_id'     => Auth::id(),
                'category_id' => $validated['category_id'],
                'year'        => $validated['year'],
                'month'       => $validated['month'],
            ],
            [
                'amount' => $validated['amount'],
            ]
        );

        return response()->json([
            'success' => true,
            'budget'  => $budget,
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
            'disbursed'      => $loansDisbursed,
            'payments'       => $loanPayments,
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
     * Without this netting, that single logical transfer would be counted
     * as a full withdrawal from Savings A (spending), even though the money
     * never left savings — it just changed which savings account holds it.
     *
     * Approach:
     *   1. Collect every "savings → non-savings" transfer (a candidate
     *      withdrawal) and every "non-savings → savings" transfer (a
     *      candidate re-deposit) for the year.
     *   2. For each withdrawal, look for re-deposits through the SAME
     *      intermediate account within a 24-hour window and consume their
     *      amounts against the withdrawal (oldest-deposit-first, partial
     *      matches allowed).
     *   3. Only the unmatched remainder of each withdrawal counts as real
     *      "Savings Used" for that month.
     *
     * This also naturally covers "moved out and back into the SAME savings
     * account" — the re-deposit doesn't have to target a different account.
     */
    private function calculateNetSavingsWithdrawals(int $year): \Illuminate\Support\Collection
    {
        $withdrawals = DB::table('transfers')
            ->join('accounts as from_acc', 'transfers.from_account_id', '=', 'from_acc.id')
            ->join('accounts as to_acc', 'transfers.to_account_id', '=', 'to_acc.id')
            ->where('transfers.user_id', Auth::id())
            ->whereYear('transfers.date', $year)
            ->where('from_acc.type', 'savings')
            ->where('to_acc.type', '!=', 'savings')
            ->where('transfers.is_client_fund', false)
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
            ->map(fn($d) => (object) [
                'id'                      => $d->id,
                'intermediate_account_id' => $d->intermediate_account_id,
                'date'                    => Carbon::parse($d->date),
                'remaining'               => (float) $d->amount,
            ])
            ->keyBy('id');

        $netByMonth = [];

        foreach ($withdrawals as $w) {
            $withdrawalDate   = Carbon::parse($w->date);
            $remainingToMatch = (float) $w->amount;

            // Candidate re-deposits: same intermediate account, on/after the
            // withdrawal, within the hop window, and not fully consumed yet.
            $candidates = $deposits
                ->filter(fn($d) =>
                    $d->intermediate_account_id === $w->intermediate_account_id
                    && $d->remaining > 0
                    && $d->date->greaterThanOrEqualTo($withdrawalDate)
                    && $withdrawalDate->diffInHours($d->date) <= self::SAVINGS_HOP_WINDOW_HOURS
                )
                ->sortBy('date');

            foreach ($candidates as $d) {
                if ($remainingToMatch <= 0) {
                    break;
                }

                $matched = min($remainingToMatch, $d->remaining);
                $remainingToMatch   -= $matched;
                $deposits[$d->id]->remaining -= $matched;
            }

            $month = $withdrawalDate->month;
            $netByMonth[$month] = ($netByMonth[$month] ?? 0) + $remainingToMatch;
        }

        return collect($netByMonth)
            ->map(fn($total, $month) => (object) [
                'month' => $month,
                'total' => $total,
            ])
            ->keyBy('month');
    }
}
