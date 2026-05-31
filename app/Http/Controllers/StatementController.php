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

        // Opening balance = everything before the period start
        $openingBalance = $this->computeBalanceAt($account, $from->copy()->subSecond());

        // ── 1. Transactions (interest + expenses) ─────────────────────────────
        $transactions = $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.date', [$from->toDateString(), $to->toDateString()])
            ->select('transactions.*', 'categories.type as cat_type', 'categories.name as cat_name')
            ->get()
            ->map(function ($txn) {
                $isInterest = $txn->cat_name === 'Interest';
                $isExpense  = $txn->cat_type === 'expense';

                return [
                    'sort_date'    => $txn->date,
                    'sort_id'      => $txn->id,
                    'date'         => Carbon::parse($txn->date)->format('M d, Y'),
                    'narration'    => $txn->description,
                    'inflow'       => null,
                    'withdrawal'   => $isExpense  ? $txn->amount : null,
                    'net_interest' => $isInterest ? $txn->amount : null,
                    // non-interest income treated as inflow
                    'inflow'       => (! $isExpense && ! $isInterest) ? $txn->amount : null,
                    'source'       => 'txn',
                ];
            });

        // ── 2. Transfers IN (money arriving at this account) ──────────────────
        $transfersIn = Transfer::where('to_account_id', $account->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(function ($t) {
                $counterpart = $t->fromAccount?->name ?? 'Transfer';
                return [
                    'sort_date'    => $t->date,
                    'sort_id'      => $t->id,
                    'date'         => Carbon::parse($t->date)->format('M d, Y'),
                    'narration'    => $t->description ?: "Transfer from {$counterpart}",
                    'inflow'       => $t->amount,
                    'withdrawal'   => null,
                    'net_interest' => null,
                    'source'       => 'transfer_in',
                ];
            });

        // ── 3. Transfers OUT (money leaving this account) ─────────────────────
        $transfersOut = Transfer::where('from_account_id', $account->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(function ($t) {
                $counterpart = $t->toAccount?->name ?? 'Transfer';
                return [
                    'sort_date'    => $t->date,
                    'sort_id'      => $t->id,
                    'date'         => Carbon::parse($t->date)->format('M d, Y'),
                    'narration'    => $t->description ?: "Transfer to {$counterpart}",
                    'inflow'       => null,
                    'withdrawal'   => $t->amount,
                    'net_interest' => null,
                    'source'       => 'transfer_out',
                ];
            });

        // ── 4. Merge & sort by date asc, then id asc ─────────────────────────
        $merged = $transactions
            ->concat($transfersIn)
            ->concat($transfersOut)
            ->sortBy([
                ['sort_date', 'asc'],
                ['sort_id',   'asc'],
            ])
            ->values();

        // ── 5. Build running balance ──────────────────────────────────────────
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
     * Balance at a given moment = initial_balance
     *   + all income/liability transactions up to that date
     *   - all expense transactions up to that date
     *   + all transfers IN up to that date
     *   - all transfers OUT up to that date
     */
    private function computeBalanceAt(Account $account, Carbon $at): float
    {
        // Transactions component
        $txNet = $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.date', '<=', $at->toDateString())
            ->selectRaw("
                SUM(CASE WHEN categories.type IN ('income','liability') THEN transactions.amount ELSE 0 END) -
                SUM(CASE WHEN categories.type = 'expense'              THEN transactions.amount ELSE 0 END)
                AS net
            ")
            ->value('net');

        // Transfers IN
        $transfersInNet = Transfer::where('to_account_id', $account->id)
            ->where('date', '<=', $at->toDateString())
            ->sum('amount');

        // Transfers OUT
        $transfersOutNet = Transfer::where('from_account_id', $account->id)
            ->where('date', '<=', $at->toDateString())
            ->sum('amount');

        return (float) ($account->initial_balance ?? 0)
            + (float) ($txNet ?? 0)
            + (float) $transfersInNet
            - (float) $transfersOutNet;
    }
}
