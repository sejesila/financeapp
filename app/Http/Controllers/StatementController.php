<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatementController extends Controller
{
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

        // ── 1. Transactions (interest, expenses, non-transfer income) ─────────
        //
        // Pending deposit rule (mirrors updateBalance):
        //   income transactions with a future value_date are NOT yet settled,
        //   so we show them on the statement date but flag them as pending —
        //   they should NOT move the running balance until value_date <= today.
        //   Interest is always settled immediately (no value_date gate).
        //
        $transactions = $account->transactions()
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

                // A deposit is "pending" when it has a future value_date
                // (matches the `pending_deposits` exclusion in updateBalance)
                $isPending = $isIncome
                    && ! empty($txn->value_date)
                    && Carbon::parse($txn->value_date)->isFuture();

                return [
                    'sort_date'    => $txn->date,
                    'sort_id'      => $txn->id,
                    'date'         => Carbon::parse($txn->date)->format('M d, Y'),
                    'narration'    => $txn->description
                        . ($isPending
                            ? ' (pending – eff. ' . Carbon::parse($txn->value_date)->format('M d') . ')'
                            : ''),
                    'inflow'       => ($isIncome   && ! $isPending) ? $txn->amount : null,
                    'withdrawal'   => $isExpense                    ? $txn->amount : null,
                    'net_interest' => $isInterest                   ? $txn->amount : null,
                    'pending'      => $isPending,
                    // Pending inflows are shown in the inflow column but bracketed
                    'pending_amount' => $isPending ? $txn->amount : null,
                    'source'       => 'txn',
                ];
            })
        // After the ->map() call on transactions, add this:
        ->groupBy(function ($item) {
        // Group interest rows by year-month, everything else by its unique id
        return $item['net_interest'] !== null
            ? 'interest_' . substr($item['sort_date'], 0, 7)   // e.g. interest_2026-05
            : 'txn_' . $item['sort_id'];
    })
        ->map(function ($group) {
            if ($group->count() === 1) {
                return $group->first();
            }

            // Multiple interest rows → consolidate into the last one
            $last = $group->sortBy('sort_date')->last();

            return array_merge($last, [
                'net_interest' => $group->sum('net_interest'),
                'narration'    => 'Interest earned – ' . Carbon::parse($last['sort_date'])->format('F Y')
                    . ' (consolidated)',
            ]);
        })
        ->values();

        // ── 2. Transfers IN ───────────────────────────────────────────────────
        //
        // Mirrors updateBalance: `value_date IS NULL OR value_date <= CURDATE()`
        // Pending transfers (future value_date) are shown on their transaction
        // date but do NOT affect the running balance yet.
        //
        $transfersIn = Transfer::where('to_account_id', $account->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
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

        // ── 3. Transfers OUT ──────────────────────────────────────────────────
        // Outgoing transfers are always deducted immediately (no value_date gate
        // in updateBalance for transfers_out).
        $transfersOut = Transfer::where('from_account_id', $account->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
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

        // ── 4. Merge & sort chronologically ──────────────────────────────────
        $merged = $transactions
            ->concat($transfersIn)
            ->concat($transfersOut)
            ->sortBy([
                ['sort_date', 'asc'],
                ['sort_id',   'asc'],
            ])
            ->values();

        // ── 5. Build running balance (only settled amounts move the balance) ──
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

        return view('accounts.statement', [
            'account'         => $account,
            'from'            => $from,
            'to'              => $to,
            'openingBalance'  => $openingBalance,
            'closingBalance'  => $runningBalance,
            'rows'            => $rows,
            'totalInflow'     => $totalInflow,
            'totalWithdrawal' => $totalWithdrawal,
            'totalInterest'   => $totalInterest,
            'user'            => auth()->user(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

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

        // Transaction net: mirrors the CASE logic in updateBalance()
        // — income with a future value_date is excluded (pending_deposits)
        // — interest has no value_date gate
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

        // Transfers IN: only settled ones (value_date IS NULL OR <= $at)
        $transfersInNet = Transfer::where('to_account_id', $account->id)
            ->where('date', '<=', $atDate)
            ->where(function ($q) use ($atDate) {
                $q->whereNull('value_date')
                    ->orWhere('value_date', '<=', $atDate);
            })
            ->sum('amount');

        // Transfers OUT: always deducted immediately
        $transfersOutNet = Transfer::where('from_account_id', $account->id)
            ->where('date', '<=', $atDate)
            ->sum('amount');

        return (float) ($account->initial_balance ?? 0)
            + (float) ($txNet ?? 0)
            + (float) $transfersInNet
            - (float) $transfersOutNet;
    }
}
