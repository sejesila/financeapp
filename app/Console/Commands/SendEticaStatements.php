<?php

namespace App\Console\Commands;

use App\Mail\EticaStatementMail;
use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEticaStatements extends Command
{
    protected $signature = 'statements:send-etica
                            {--force         : Force send regardless of schedule}
                            {--month=        : Month to generate for (YYYY-MM), defaults to last month}
                            {--user=         : Only send to this user ID}';

    protected $description = 'Generate and email Etica Fixed Income Fund statements to users (runs on the 1st of each month)';

    public function handle(): int
    {
        $this->info('Starting Etica statement generation...');

        if (! $this->option('force') && now()->day !== 1) {
            $this->info('Not the 1st of the month — skipping. Use --force to override.');
            return Command::SUCCESS;
        }

        $targetMonth = $this->option('month')
            ? Carbon::parse($this->option('month') . '-01')->startOfMonth()
            : now()->subMonth()->startOfMonth();

        $from   = $targetMonth->copy()->startOfMonth();
        $to     = $targetMonth->copy()->endOfMonth();
        $period = $targetMonth->format('F Y');

        $this->info("Period: {$from->toDateString()} → {$to->toDateString()}");

        $query = User::whereHas('accounts', function ($q) {
            $q->where('type', 'savings')
                ->where('is_active', true)
                ->whereRaw("LOWER(name) LIKE '%etica%'");
        })->with(['accounts' => function ($q) {
            $q->where('type', 'savings')
                ->where('is_active', true)
                ->whereRaw("LOWER(name) LIKE '%etica%'");
        }]);

        if ($this->option('user')) {
            $query->where('id', $this->option('user'));
        }

        $users = $query->get();
        $this->info("Found {$users->count()} user(s) with Etica accounts.");

        $successCount = 0;
        $failCount    = 0;

        foreach ($users as $user) {
            foreach ($user->accounts as $account) {
                try {
                    $this->info("  Generating statement for {$user->name} / {$account->name}...");

                    $statementData = $this->buildStatementData($account, $from, $to);

                    // EticaStatementMail now generates the PDF itself via Spatie —
                    // no temp file to create or clean up here.
                    Mail::to($user->email)
                        ->send(new EticaStatementMail(
                            user:          $user,
                            account:       $account,
                            statementData: $statementData,  // ← renamed from 'data'
                            period:        $period,
                        ));

                    $this->info("    ✓ Statement sent to {$user->email}");
                    $successCount++;

                } catch (\Throwable $e) {
                    $this->error("    ✗ Failed for {$user->email} / {$account->name}: {$e->getMessage()}");
                    $failCount++;
                }
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Successfully sent: {$successCount}");
        if ($failCount > 0) {
            $this->error("Failed: {$failCount}");
        }
        $this->info("Total processed: " . ($successCount + $failCount));

        return Command::SUCCESS;
    }

    private function buildStatementData(Account $account, Carbon $from, Carbon $to): array
    {
        $openingBalance = $this->computeBalanceAt($account, $from->copy()->subSecond());

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
                $isPending  = $isIncome
                    && ! empty($txn->value_date)
                    && Carbon::parse($txn->value_date)->isFuture();

                return [
                    'sort_date'      => $txn->date,
                    'sort_id'        => $txn->id,
                    'date'           => Carbon::parse($txn->date)->format('M d, Y'),
                    'narration'      => $txn->description
                        . ($isPending ? ' (pending – eff. ' . Carbon::parse($txn->value_date)->format('M d') . ')' : ''),
                    'inflow'         => ($isIncome && ! $isPending) ? $txn->amount : null,
                    'withdrawal'     => $isExpense ? $txn->amount : null,
                    'net_interest'   => $isInterest ? $txn->amount : null,
                    'pending'        => $isPending,
                    'pending_amount' => $isPending ? $txn->amount : null,
                    'source'         => 'txn',
                ];
            });

        $transfersIn = Transfer::where('to_account_id', $account->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(function ($t) {
                $counterpart = $t->fromAccount?->name ?? 'Transfer';
                $isPending   = ! empty($t->value_date) && Carbon::parse($t->value_date)->isFuture();
                return [
                    'sort_date'      => $t->date,
                    'sort_id'        => $t->id,
                    'date'           => Carbon::parse($t->date)->format('M d, Y'),
                    'narration'      => ($t->description ?: "Transfer from {$counterpart}")
                        . ($isPending ? ' (pending – eff. ' . Carbon::parse($t->value_date)->format('M d') . ')' : ''),
                    'inflow'         => ! $isPending ? $t->amount : null,
                    'withdrawal'     => null,
                    'net_interest'   => null,
                    'pending'        => $isPending,
                    'pending_amount' => $isPending ? $t->amount : null,
                    'source'         => 'transfer_in',
                ];
            });

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

        $merged = $transactions
            ->concat($transfersIn)
            ->concat($transfersOut)
            ->sortBy([['sort_date', 'asc'], ['sort_id', 'asc']])
            ->values();

        $runningBalance  = $openingBalance;
        $totalInflow     = 0;
        $totalWithdrawal = 0;
        $totalInterest   = 0;
        $rows            = [];

        foreach ($merged as $item) {
            if ($item['inflow']       !== null) { $runningBalance += $item['inflow'];       $totalInflow     += $item['inflow']; }
            if ($item['withdrawal']   !== null) { $runningBalance -= $item['withdrawal'];   $totalWithdrawal += $item['withdrawal']; }
            if ($item['net_interest'] !== null) { $runningBalance += $item['net_interest']; $totalInterest   += $item['net_interest']; }
            $rows[] = array_merge($item, ['running_balance' => $runningBalance]);
        }

        return [
            'from'            => $from,
            'to'              => $to,
            'openingBalance'  => $openingBalance,
            'closingBalance'  => $runningBalance,
            'rows'            => $rows,
            'totalInflow'     => $totalInflow,
            'totalWithdrawal' => $totalWithdrawal,
            'totalInterest'   => $totalInterest,
        ];
    }

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
                SUM(CASE WHEN categories.type = 'expense' THEN transactions.amount ELSE 0 END)
                AS net
            ", [$atDate])
            ->value('net');

        $transfersInNet = Transfer::where('to_account_id', $account->id)
            ->where('date', '<=', $atDate)
            ->where(fn($q) => $q->whereNull('value_date')->orWhere('value_date', '<=', $atDate))
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
