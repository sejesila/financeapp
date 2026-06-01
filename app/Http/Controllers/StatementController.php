<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatementController extends Controller
{
    // =========================================================================
    // MONTHLY / DATE-RANGE STATEMENT  (existing, unchanged)
    // =========================================================================

    public function show(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        if ($account->type !== 'savings') {
            return redirect()->route('accounts.show', $account)
                ->with('error', 'Statements are only available for savings accounts.');
        }

        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        // Opening balance = everything settled before the period start
        $openingBalance = $this->computeBalanceAt($account, $from->copy()->subSecond());

        // ── 1. Transactions ───────────────────────────────────────────────────
        $transactions = $this->fetchTransactions($account, $from, $to);

        // ── 2. Transfers IN ───────────────────────────────────────────────────
        $transfersIn = $this->fetchTransfersIn($account, $from, $to);

        // ── 3. Transfers OUT ──────────────────────────────────────────────────
        $transfersOut = $this->fetchTransfersOut($account, $from, $to);

        // ── 4. Merge & sort chronologically ──────────────────────────────────
        $merged = $transactions
            ->concat($transfersIn)
            ->concat($transfersOut)
            ->sortBy([
                ['sort_date', 'asc'],
                ['sort_id',   'asc'],
            ])
            ->values();

        // ── 5. Build running balance ──────────────────────────────────────────
        [
            'rows'            => $rows,
            'totalInflow'     => $totalInflow,
            'totalWithdrawal' => $totalWithdrawal,
            'totalInterest'   => $totalInterest,
            'closingBalance'  => $closingBalance,
        ] = $this->buildRunningBalance($merged, $openingBalance);

        return view('accounts.statement', [
            'account'         => $account,
            'from'            => $from,
            'to'              => $to,
            'openingBalance'  => $openingBalance,
            'closingBalance'  => $closingBalance,
            'rows'            => $rows,
            'totalInflow'     => $totalInflow,
            'totalWithdrawal' => $totalWithdrawal,
            'totalInterest'   => $totalInterest,
            'user'            => auth()->user(),
        ]);
    }

    // =========================================================================
    // ANNUAL STATEMENT  (new)
    // =========================================================================

    /**
     * Generate a full-year statement broken down by month.
     *
     * Route example:
     *   GET /accounts/{account}/statement/annual?year=2025
     *
     * View receives:
     *   $account, $year (int), $openingBalance, $closingBalance,
     *   $months  (array of monthly summary objects – see below),
     *   $annual  (year-level totals),
     *   $user
     *
     * Each $months entry:
     *   [
     *     'label'          => 'January 2025',
     *     'from'           => Carbon,
     *     'to'             => Carbon,
     *     'openingBalance' => float,
     *     'closingBalance' => float,
     *     'totalInflow'    => float,
     *     'totalWithdrawal'=> float,
     *     'totalInterest'  => float,
     *     'netChange'      => float,   // closing - opening
     *     'rows'           => array,   // same row structure as show()
     *   ]
     */
    public function annual(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        if ($account->type !== 'savings') {
            return redirect()->route('accounts.show', $account)
                ->with('error', 'Statements are only available for savings accounts.');
        }

        // ── Resolve the target year ───────────────────────────────────────────
        $year = (int) $request->input('year', now()->year);

        // Guard: don't allow future years
        if ($year > now()->year) {
            $year = now()->year;
        }

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();

        // Clamp the end to today so future months are excluded from data
        // (but we still render them as empty months for the current year)
        $dataEnd = $yearEnd->copy()->min(now()->endOfDay());

        // Opening balance of the year = settled balance on Dec 31 of prior year
        $openingBalance = $this->computeBalanceAt($account, $yearStart->copy()->subSecond());

        // ── Fetch ALL data for the year in one pass ───────────────────────────
        $allTransactions = $this->fetchTransactions($account, $yearStart, $dataEnd);
        $allTransfersIn  = $this->fetchTransfersIn($account, $yearStart, $dataEnd);
        $allTransfersOut = $this->fetchTransfersOut($account, $yearStart, $dataEnd);

        $allMerged = $allTransactions
            ->concat($allTransfersIn)
            ->concat($allTransfersOut)
            ->sortBy([['sort_date', 'asc'], ['sort_id', 'asc']])
            ->values();

        // ── Slice per month and accumulate running balance ────────────────────
        $months          = [];
        $runningBalance  = $openingBalance;

        // Year-level accumulators
        $annualInflow     = 0;
        $annualWithdrawal = 0;
        $annualInterest   = 0;

        for ($m = 1; $m <= 12; $m++) {
            $monthStart = Carbon::create($year, $m, 1)->startOfDay();
            $monthEnd   = $monthStart->copy()->endOfMonth()->endOfDay();

            // Rows that fall in this calendar month
            $monthItems = $allMerged->filter(function ($item) use ($monthStart, $monthEnd) {
                $d = Carbon::parse($item['sort_date']);
                return $d->between($monthStart, $monthEnd);
            })->values();

            $monthOpeningBalance = $runningBalance;

            [
                'rows'            => $rows,
                'totalInflow'     => $totalInflow,
                'totalWithdrawal' => $totalWithdrawal,
                'totalInterest'   => $totalInterest,
                'closingBalance'  => $closingBalance,
            ] = $this->buildRunningBalance($monthItems, $runningBalance);

            $runningBalance = $closingBalance;

            $annualInflow     += $totalInflow;
            $annualWithdrawal += $totalWithdrawal;
            $annualInterest   += $totalInterest;

            $months[] = [
                'label'           => $monthStart->format('F Y'),
                'month'           => $m,
                'from'            => $monthStart,
                'to'              => $monthEnd,
                'isFuture'        => $monthStart->isAfter(now()),
                'openingBalance'  => $monthOpeningBalance,
                'closingBalance'  => $closingBalance,
                'totalInflow'     => $totalInflow,
                'totalWithdrawal' => $totalWithdrawal,
                'totalInterest'   => $totalInterest,
                'netChange'       => $closingBalance - $monthOpeningBalance,
                'rows'            => $rows,
            ];
        }

        // ── Year-level summary ────────────────────────────────────────────────
        $annual = [
            'year'            => $year,
            'openingBalance'  => $openingBalance,
            'closingBalance'  => $runningBalance,
            'totalInflow'     => $annualInflow,
            'totalWithdrawal' => $annualWithdrawal,
            'totalInterest'   => $annualInterest,
            'netChange'       => $runningBalance - $openingBalance,
        ];

        // ── Available years for the year-picker (account creation year → now) ─
        $accountCreatedYear = Carbon::parse($account->created_at)->year;
        $availableYears     = range(now()->year, $accountCreatedYear);  // desc order

        return view('accounts.statement-annual', [
            'account'        => $account,
            'year'           => $year,
            'yearStart'      => $yearStart,
            'yearEnd'        => $yearEnd,
            'openingBalance' => $openingBalance,
            'closingBalance' => $runningBalance,
            'months'         => $months,
            'annual'         => $annual,
            'availableYears' => $availableYears,
            'user'           => auth()->user(),
        ]);
    }

    // =========================================================================
    // SHARED HELPERS  (extracted so show() and annual() both use them)
    // =========================================================================

    /**
     * Fetch, map, and consolidate transactions (interest, expenses, income)
     * for a given account and date window.
     *
     * Pending-deposit logic and interest consolidation are identical to the
     * original show() implementation.
     */
    private function fetchTransactions(Account $account, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.date', [$from->toDateString(), $to->toDateString()])
            ->select('transactions.*', 'categories.type as cat_type', 'categories.name as cat_name')
            ->orderBy('transactions.date')
            ->orderBy('transactions.id')
            ->get()
            ->map(function ($txn) {
                $isInterest = $txn->cat_name === 'Interest';
                $isExpense  = $txn->cat_type === 'expense';
                $isIncome   = ! $isExpense && ! $isInterest;

                $isPending = $isIncome
                    && ! empty($txn->value_date)
                    && Carbon::parse($txn->value_date)->isFuture();

                return [
                    'sort_date'      => $txn->date,
                    'sort_id'        => $txn->id,
                    'date'           => Carbon::parse($txn->date)->format('M d, Y'),
                    'narration'      => $txn->description
                        . ($isPending
                            ? ' (pending – eff. ' . Carbon::parse($txn->value_date)->format('M d') . ')'
                            : ''),
                    'inflow'         => ($isIncome && ! $isPending) ? $txn->amount : null,
                    'withdrawal'     => $isExpense                  ? $txn->amount : null,
                    'net_interest'   => $isInterest                 ? $txn->amount : null,
                    'pending'        => $isPending,
                    'pending_amount' => $isPending ? $txn->amount : null,
                    'source'         => 'txn',
                ];
            })
            ->groupBy(function ($item) {
                return $item['net_interest'] !== null
                    ? 'interest_' . substr($item['sort_date'], 0, 7)
                    : 'txn_' . $item['sort_id'];
            })
            ->map(function ($group) {
                if ($group->count() === 1) {
                    return $group->first();
                }

                // Multiple interest rows in the same month → consolidate
                $last = $group->sortBy('sort_date')->last();

                return array_merge($last, [
                    'net_interest' => $group->sum('net_interest'),
                    'narration'    => 'Interest earned – '
                        . Carbon::parse($last['sort_date'])->format('F Y')
                        . ' (consolidated)',
                ]);
            })
            ->values();
    }

    /**
     * Fetch and map incoming transfers for an account within the date window.
     * Pending transfers (future value_date) are shown but do not move the balance.
     */

    private function fetchTransfersIn(Account $account, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return Transfer::where('to_account_id', $account->id)
            ->whereRaw('DATE(date) BETWEEN ? AND ?', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->get()
            ->map(function ($t) {
                $counterpart = $t->fromAccount?->name ?? 'Transfer';
                $isPending   = ! empty($t->value_date)
                    && Carbon::parse($t->value_date)->isFuture();

                return [
                    'sort_date'      => $t->date,
                    'sort_id'        => $t->id,
                    'date'           => Carbon::parse($t->date)->format('M d, Y'),
                    'narration'      => ($t->description ?: "Transfer from {$counterpart}")
                        . ($isPending
                            ? ' (pending – eff. ' . Carbon::parse($t->value_date)->format('M d') . ')'
                            : ''),
                    'inflow'         => ! $isPending ? $t->amount : null,
                    'withdrawal'     => null,
                    'net_interest'   => null,
                    'pending'        => $isPending,
                    'pending_amount' => $isPending ? $t->amount : null,
                    'source'         => 'transfer_in',
                ];
            });
    }

    private function fetchTransfersOut(Account $account, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return Transfer::where('from_account_id', $account->id)
            ->whereRaw('DATE(date) BETWEEN ? AND ?', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->get()
            ->map(function ($t) {
                $counterpart = $t->toAccount?->name ?? 'Transfer';
                return [
                    'sort_date'      => $t->date,
                    'sort_id'        => $t->id,
                    'date'           => Carbon::parse($t->date)->format('M d, Y'),
                    'narration'      => $t->description ?: "Transfer to {$counterpart}",
                    'inflow'         => null,
                    'withdrawal'     => $t->amount,
                    'net_interest'   => null,
                    'pending'        => false,
                    'pending_amount' => null,
                    'source'         => 'transfer_out',
                ];
            });
    }
    /**
     * Walk a sorted collection of merged rows, accumulate totals, and attach a
     * running balance to every row.  Returns totals + annotated rows.
     *
     * @param  \Illuminate\Support\Collection  $merged
     * @param  float  $openingBalance
     * @return array{rows: array, totalInflow: float, totalWithdrawal: float, totalInterest: float, closingBalance: float}
     */
    private function buildRunningBalance(\Illuminate\Support\Collection $merged, float $openingBalance): array
    {
        $runningBalance  = $openingBalance;
        $totalInflow     = 0;
        $totalWithdrawal = 0;
        $totalInterest   = 0;
        $rows            = [];

        foreach ($merged as $item) {
            if ($item['inflow'] !== null) {
                $runningBalance += $item['inflow'];
                $totalInflow    += $item['inflow'];
            }
            if ($item['withdrawal'] !== null) {
                $runningBalance  -= $item['withdrawal'];
                $totalWithdrawal += $item['withdrawal'];
            }
            if ($item['net_interest'] !== null) {
                $runningBalance += $item['net_interest'];
                $totalInterest  += $item['net_interest'];
            }

            $rows[] = array_merge($item, ['running_balance' => $runningBalance]);
        }

        return [
            'rows'            => $rows,
            'totalInflow'     => $totalInflow,
            'totalWithdrawal' => $totalWithdrawal,
            'totalInterest'   => $totalInterest,
            'closingBalance'  => $runningBalance,
        ];
    }

    // =========================================================================
    // BALANCE HELPER  (unchanged logic, shared by both statement types)
    // =========================================================================

    /**
     * Compute the settled balance at a given point in time.
     *
     * Mirrors updateBalance() exactly:
     *
     *   + initial_balance
     *   + income transactions (excl. future value_date deposits)
     *   + liability transactions (loan receipts, client funds)
     *   - expense transactions
     *   + transfers IN  where value_date IS NULL OR value_date <= $at
     *   - transfers OUT (always immediate)
     */
    private function computeBalanceAt(Account $account, Carbon $at): float
    {
        $atDate = $at->toDateString();

        $txNet = $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.date', '<=', $atDate)
            ->selectRaw("
                SUM(CASE
                    WHEN categories.type IN ('income', 'liability')
                     AND NOT (
                            categories.type = 'income'
                            AND categories.name NOT IN ('Interest')
                            AND transactions.value_date IS NOT NULL
                            AND transactions.value_date > ?
                         )
                    THEN transactions.amount
                    ELSE 0
                END) -
                SUM(CASE
                    WHEN categories.type = 'expense'
                    THEN transactions.amount
                    ELSE 0
                END) AS net
            ", [$atDate])
            ->value('net');

        $transfersInNet = Transfer::where('to_account_id', $account->id)
            ->where('date', '<=', $atDate)
            ->where(function ($q) use ($atDate) {
                $q->whereNull('value_date')
                    ->orWhere('value_date', '<=', $atDate);
            })
            ->sum('amount');

        $transfersOutNet = Transfer::where('from_account_id', $account->id)
            ->where('date', '<=', $atDate)
            ->sum('amount');

        return (float) ($account->initial_balance ?? 0)
            + (float) ($txNet ?? 0)
            + (float) $transfersInNet
            - (float) $transfersOutNet;
    }
}
