<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StatementDataService
{
    /**
     * Build the full statement data array for a given account and date range.
     * This is the single source of truth used by the controller, the Etica
     * statement command, and the combined monthly-report command.
     */
    public function buildStatementData(Account $account, Carbon $from, Carbon $to): array
    {
        $openingBalance = $this->computeBalanceAt($account, $from->copy()->subSecond());

        $transactions = $this->fetchTransactions($account, $from, $to);
        $transfersIn  = $this->fetchTransfersIn($account, $from, $to);
        $transfersOut = $this->fetchTransfersOut($account, $from, $to);

        $merged = $transactions
            ->concat($transfersIn)
            ->concat($transfersOut)
            ->sortBy([['sort_date', 'asc'], ['sort_id', 'asc']])
            ->values();

        [
            'rows'            => $rows,
            'totalInflow'     => $totalInflow,
            'totalWithdrawal' => $totalWithdrawal,
            'totalInterest'   => $totalInterest,
            'closingBalance'  => $closingBalance,
        ] = $this->buildRunningBalance($merged, $openingBalance);

        return [
            'from'            => $from,
            'to'              => $to,
            'openingBalance'  => $openingBalance,
            'closingBalance'  => $closingBalance,
            'rows'            => $rows,
            'totalInflow'     => $totalInflow,
            'totalWithdrawal' => $totalWithdrawal,
            'totalInterest'   => $totalInterest,
        ];
    }

    /**
     * Compute the settled balance of an account at a given point in time.
     * Pending income (value_date in the future relative to $at) is excluded,
     * but Interest-category transactions are always counted as settled.
     */
    public function computeBalanceAt(Account $account, Carbon $at): float
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
                        categories.name NOT IN ('Interest')
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

    // -------------------------------------------------------------------------
    // Private fetch helpers
    // -------------------------------------------------------------------------

    private function fetchTransactions(Account $account, Carbon $from, Carbon $to): Collection
    {
        return $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->whereRaw('DATE(transactions.date) BETWEEN ? AND ?', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->select('transactions.*', 'categories.type as cat_type', 'categories.name as cat_name')
            ->orderBy('transactions.date')
            ->orderBy('transactions.id')
            ->get()
            ->map(function ($txn) {
                $isInterest = $txn->cat_name === 'Interest';
                $isExpense  = $txn->cat_type === 'expense';
                $isIncome   = ! $isExpense && ! $isInterest;
                $isPending  = $isIncome
                    && ! empty($txn->value_date)
                    && Carbon::parse($txn->value_date)->isFuture();

                return [
                    'sort_date'        => $txn->date,
                    'sort_id'          => $txn->id,
                    'date'             => Carbon::parse($txn->date)->format('M d, Y'),
                    'narration'        => $txn->description
                        . ($isPending
                            ? ' (pending – eff. ' . Carbon::parse($txn->value_date)->format('M d') . ')'
                            : ''),
                    'inflow'           => ($isIncome && ! $isPending) ? $txn->amount : null,
                    'withdrawal'       => $isExpense                  ? $txn->amount : null,
                    'net_interest'     => $isInterest                 ? $txn->amount : null,
                    'pending'          => $isPending,
                    'pending_amount'   => $isPending ? $txn->amount : null,
                    'source'           => 'txn',
                    // Group key used only for display consolidation, applied AFTER running balance is built
                    'interest_group'   => $isInterest ? substr($txn->date, 0, 7) : null,
                ];
            })
            ->values();
    }

    private function fetchTransfersIn(Account $account, Carbon $from, Carbon $to): Collection
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
                    'interest_group' => null,
                ];
            });
    }

    private function fetchTransfersOut(Account $account, Carbon $from, Carbon $to): Collection
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
                    'interest_group' => null,
                ];
            });
    }

    private function buildRunningBalance(Collection $merged, float $openingBalance): array
    {
        $runningBalance  = $openingBalance;
        $totalInflow     = 0;
        $totalWithdrawal = 0;
        $totalInterest   = 0;
        $rawRows         = [];

        // Step 1: compute true running balance per individual transaction
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

            $rawRows[] = array_merge($item, ['running_balance' => $runningBalance]);
        }

        // Step 2: consolidate interest rows for display only, after balances are correct.
        // Each group's displayed row uses the LAST individual row's running_balance
        // (which is already correct) and the SUMMED amount for that month.
        $rows = collect($rawRows)
            ->groupBy(function ($item) {
                return $item['interest_group'] !== null
                    ? 'interest_' . $item['interest_group']
                    : 'txn_' . $item['sort_id'];
            })
            ->map(function ($group) {
                if ($group->count() === 1) {
                    return $group->first();
                }

                $last = $group->sortBy('sort_date')->last();

                return array_merge($last, [
                    'net_interest' => $group->sum('net_interest'),
                    'narration'    => 'Interest earned – '
                        . Carbon::parse($last['sort_date'])->format('F Y')
                        . ' (consolidated)',
                ]);
            })
            // groupBy() keeps each group at the position of its FIRST occurrence,
            // which for interest rows is often earlier in the month than the
            // consolidated row's display date (the last posting). Re-sort by date
            // so the row appears where it belongs instead of near the top.
            ->sortBy([['sort_date', 'asc'], ['sort_id', 'asc']])
            ->values()
            ->all();

        return [
            'rows'            => $rows,
            'totalInflow'     => $totalInflow,
            'totalWithdrawal' => $totalWithdrawal,
            'totalInterest'   => $totalInterest,
            'closingBalance'  => $runningBalance,
        ];
    }
}
