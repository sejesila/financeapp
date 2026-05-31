<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
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

        // Default: current month
        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        // Opening balance = sum of all transactions (income - expense) strictly BEFORE $from
        $openingBalance = $this->computeBalanceAt($account, $from->copy()->subSecond());

        // All transactions within the range, ordered by date then id
        $transactions = $account->transactions()
            ->with('category')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.date', [$from->toDateString(), $to->toDateString()])
            ->select('transactions.*', 'categories.type as cat_type', 'categories.name as cat_name')
            ->orderBy('transactions.date')
            ->orderBy('transactions.id')
            ->get();

        // Build statement rows with running balance
        $runningBalance = $openingBalance;
        $rows = [];

        $totalInflow    = 0;
        $totalWithdrawal = 0;
        $totalInterest  = 0;

        foreach ($transactions as $txn) {
            $isInterest   = $txn->cat_name === 'Interest';
            $isExpense    = $txn->cat_type === 'expense';
            $isIncome     = in_array($txn->cat_type, ['income', 'liability']) && ! $isInterest;

            if ($isExpense) {
                $runningBalance -= $txn->amount;
                $totalWithdrawal += $txn->amount;
            } elseif ($isInterest) {
                $runningBalance += $txn->amount;
                $totalInterest  += $txn->amount;
            } else {
                $runningBalance += $txn->amount;
                $totalInflow    += $txn->amount;
            }

            $rows[] = [
                'date'            => Carbon::parse($txn->date)->format('M d, Y'),
                'narration'       => $txn->description,
                'inflow'          => $isIncome   ? $txn->amount : null,
                'withdrawal'      => $isExpense  ? $txn->amount : null,
                'net_interest'    => $isInterest ? $txn->amount : null,
                'running_balance' => $runningBalance,
            ];
        }

        $closingBalance = $runningBalance;

        return view('accounts.statement', [
            'account'          => $account,
            'from'             => $from,
            'to'               => $to,
            'openingBalance'   => $openingBalance,
            'closingBalance'   => $closingBalance,
            'rows'             => $rows,
            'totalInflow'      => $totalInflow,
            'totalWithdrawal'  => $totalWithdrawal,
            'totalInterest'    => $totalInterest,
            'user'             => auth()->user(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compute the account balance at a given point in time by summing all
     * non-deleted transactions up to (and including) that moment, plus the
     * initial_balance seeded at account creation.
     */
    private function computeBalanceAt(Account $account, Carbon $at): float
    {
        $result = $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.date', '<=', $at->toDateString())
            ->selectRaw("
                SUM(CASE WHEN categories.type IN ('income','liability') THEN transactions.amount ELSE 0 END) -
                SUM(CASE WHEN categories.type = 'expense'              THEN transactions.amount ELSE 0 END)
                AS net
            ")
            ->value('net');

        return (float) ($account->initial_balance ?? 0) + (float) ($result ?? 0);
    }
}
