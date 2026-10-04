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
     *
     * FIX: every date comparison here is now DATE-only. `date` / `value_date`
     * columns carry a time component (transfers cast `date` as datetime), so
     * a plain `date <= '2026-09-30'` is really `<= '2026-09-30 00:00:00'` and
     * silently dropped everything posted later on the last day of the month
     * from the next month's opening balance (B/F). Only midnight-stamped rows
     * (the interest postings) survived, which is why the opening balance was
     * overstated by exactly the month-end withdrawals.
     */
    public function computeBalanceAt(Account $account, Carbon $at): float
    {
        $atDate = $at->toDateString();

        $txNet = $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->whereRaw('DATE(transactions.date) <= ?', [$atDate])
            ->selectRaw("
            SUM(CASE
                WHEN categories.type IN ('income', 'liability')
                 AND NOT (
                        categories.name NOT IN ('Interest')
                        AND transactions.value_date IS NOT NULL
                        AND DATE(transactions.value_date) > ?
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
            ->whereDate('date', '<=', $atDate)
            ->where(function ($q) use ($atDate) {
                $q->whereNull('value_date')
                    ->orWhereDate('value_date', '<=', $atDate);
            })
            ->sum('amount');

        $transfersOutNet = Transfer::where('from_account_id', $account->id)
            ->whereDate('date', '<=', $atDate)
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

        // Step 2: consolidate interest rows for display only.
        // Each group's displayed row uses the SUMMED amount for that month,
        // dated at the LAST posting.
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

        // Step 3 (FIX): recompute running balances over the rows AS DISPLAYED.
        // Step 1's balances were computed before consolidation, so any interest
        // posted earlier in the month was already baked into the rows that
        // followed it, even though that interest is only shown later as one
        // consolidated line. The visible rows then didn't foot (previous
        // balance + this row's amount != this row's balance). Recomputing here
        // makes every line equal the previous balance plus its own amount.
        // The closing balance is unchanged: the amounts being summed are the same.
        $running = $openingBalance;
        $rows = array_map(function ($row) use (&$running) {
            $running += ($row['inflow'] ?? 0)
                - ($row['withdrawal'] ?? 0)
                + ($row['net_interest'] ?? 0);

            $row['running_balance'] = $running;

            return $row;
        }, $rows);

        return [
            'rows'            => $rows,
            'totalInflow'     => $totalInflow,
            'totalWithdrawal' => $totalWithdrawal,
            'totalInterest'   => $totalInterest,
            'closingBalance'  => $runningBalance,
        ];
    }
}
