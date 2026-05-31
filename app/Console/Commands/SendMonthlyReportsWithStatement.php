<?php

namespace App\Console\Commands;

use App\Mail\MonthlyReportMail;
use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use App\Services\ReportDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelPdf\Facades\Pdf;          // ← Spatie, not DomPDF

class SendMonthlyReportsWithStatement extends Command
{
    protected $signature = 'reports:send-monthly-with-statement
                            {--force  : Force send regardless of schedule}
                            {--month= : Month to generate for (YYYY-MM), defaults to last month}
                            {--user=  : Only send to this user ID}';

    protected $description = 'Send monthly financial report + Etica statement as a single email with two PDF attachments';

    public function handle(ReportDataService $reportService): int
    {
        $this->info('Starting combined monthly report + Etica statement generation...');

        $currentDay = now()->day;

        $targetMonth = $this->option('month')
            ? Carbon::parse($this->option('month') . '-01')->startOfMonth()
            : now()->subMonth()->startOfMonth();

        $from   = $targetMonth->copy()->startOfMonth();
        $to     = $targetMonth->copy()->endOfMonth();
        $period = $targetMonth->format('F Y');

        $this->info("Period: {$from->toDateString()} → {$to->toDateString()}");

        $query = User::whereHas('emailPreference', function ($q) use ($currentDay) {
            $q->where('monthly_reports', true)
                ->where('monthly_day', $currentDay);

            if (! $this->option('force')) {
                $q->where(function ($inner) {
                    $inner->whereNull('last_monthly_sent')
                        ->orWhere('last_monthly_sent', '<', now()->subDays(25));
                });
            }
        })
            ->whereHas('accounts', function ($q) {
                $q->where('type', 'savings')
                    ->where('is_active', true)
                    ->whereRaw("LOWER(name) LIKE '%etica%'");
            })
            ->with([
                'emailPreference',
                'accounts' => fn($q) => $q->where('type', 'savings')
                    ->where('is_active', true)
                    ->whereRaw("LOWER(name) LIKE '%etica%'"),
            ]);

        if ($this->option('user')) {
            $query->where('id', $this->option('user'));
        }

        $users = $query->get();
        $this->info("Found {$users->count()} user(s) to send combined reports to.");

        $successCount = 0;
        $failCount    = 0;

        foreach ($users as $user) {
            $tmpFiles = [];

            try {
                $this->info("  Processing {$user->name} ({$user->email})...");

                // ── 1. Monthly report PDF via Spatie ──────────────────────────
                $reportData  = $reportService->generateMonthlyReport($user);
                $reportPath  = sys_get_temp_dir()
                    . "/monthly_report_{$user->id}_{$targetMonth->format('Y_m')}.pdf";

                Pdf::view('reports.monthly-pdf', [
                    'user' => $user,
                    'data' => $reportData,
                ])
                    ->format('a4')
                    ->save($reportPath);                 // ← Spatie API

                $tmpFiles[] = $reportPath;

                // ── 2. Etica statement PDFs via Spatie ────────────────────────
                $statementAttachments = [];

                foreach ($user->accounts as $account) {
                    $statementData = $this->buildStatementData($account, $from, $to);
                    $statementPath = sys_get_temp_dir()
                        . "/etica_statement_{$user->id}_{$account->id}_{$targetMonth->format('Y_m')}.pdf";

                    Pdf::view('accounts.statement', array_merge($statementData, [
                        'account' => $account,
                        'user'    => $user,
                        'from'    => $from,
                        'to'      => $to,
                    ]))
                        ->format('a4')
                        ->save($statementPath);          // ← Spatie API

                    $tmpFiles[]             = $statementPath;
                    $statementAttachments[] = [
                        'path'     => $statementPath,
                        'filename' => "{$account->name}_Statement_{$period}.pdf",
                    ];

                    $this->info("    Statement ready for {$account->name}");
                }

                // ── 3. One email, all attachments ─────────────────────────────
                Mail::to($user->email)->send(
                    (new MonthlyReportMail($user, $reportData))
                        ->withAttachments(
                            collect($statementAttachments)
                                ->map(fn($s) => Attachment::fromPath($s['path'])
                                    ->as($s['filename'])
                                    ->withMime('application/pdf'))
                                ->all()
                        )
                );

                $user->emailPreference->update(['last_monthly_sent' => now()]);
                $this->info("    ✓ Combined email sent to {$user->email}");
                $successCount++;

            } catch (\Throwable $e) {
                $this->error("    ✗ Failed for {$user->email}: {$e->getMessage()}");
                $failCount++;
            } finally {
                foreach ($tmpFiles as $path) {
                    if (file_exists($path)) {
                        @unlink($path);
                    }
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

    // ── Statement builder & balance helper ────────────────────────────────────

    private function buildStatementData(Account $account, Carbon $from, Carbon $to): array
    {
        $openingBalance = $this->computeBalanceAt($account, $from->copy()->subSecond());

        $transactions = $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.date', [$from->toDateString(), $to->toDateString()])
            ->select('transactions.*', 'categories.type as cat_type', 'categories.name as cat_name')
            ->orderBy('transactions.date')->orderBy('transactions.id')
            ->get()
            ->map(function ($txn) {
                $isInterest = $txn->cat_name === 'Interest';
                $isExpense  = $txn->cat_type === 'expense';
                $isIncome   = ! $isExpense && ! $isInterest;
                $isPending  = $isIncome && ! empty($txn->value_date) && Carbon::parse($txn->value_date)->isFuture();

                return [
                    'sort_date'      => $txn->date,
                    'sort_id'        => $txn->id,
                    'date'           => Carbon::parse($txn->date)->format('M d, Y'),
                    'narration'      => $txn->description . ($isPending ? ' (pending – eff. ' . Carbon::parse($txn->value_date)->format('M d') . ')' : ''),
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
                    'sort_date'      => $t->date, 'sort_id' => $t->id,
                    'date'           => Carbon::parse($t->date)->format('M d, Y'),
                    'narration'      => ($t->description ?: "Transfer from {$counterpart}") . ($isPending ? ' (pending – eff. ' . Carbon::parse($t->value_date)->format('M d') . ')' : ''),
                    'inflow'         => ! $isPending ? $t->amount : null,
                    'withdrawal'     => null, 'net_interest' => null,
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
                    'sort_date' => $t->date, 'sort_id' => $t->id,
                    'date'      => Carbon::parse($t->date)->format('M d, Y'),
                    'narration' => $t->description ?: "Transfer to {$counterpart}",
                    'inflow'    => null, 'withdrawal' => $t->amount, 'net_interest' => null,
                    'pending'   => false, 'pending_amount' => null,
                    'source'    => 'transfer_out',
                ];
            });

        $merged = $transactions->concat($transfersIn)->concat($transfersOut)
            ->sortBy([['sort_date', 'asc'], ['sort_id', 'asc']])->values();

        $runningBalance = $openingBalance;
        $totalInflow = $totalWithdrawal = $totalInterest = 0;
        $rows = [];

        foreach ($merged as $item) {
            if ($item['inflow']       !== null) { $runningBalance += $item['inflow'];       $totalInflow     += $item['inflow']; }
            if ($item['withdrawal']   !== null) { $runningBalance -= $item['withdrawal'];   $totalWithdrawal += $item['withdrawal']; }
            if ($item['net_interest'] !== null) { $runningBalance += $item['net_interest']; $totalInterest   += $item['net_interest']; }
            $rows[] = array_merge($item, ['running_balance' => $runningBalance]);
        }

        return compact('openingBalance', 'rows', 'totalInflow', 'totalWithdrawal', 'totalInterest')
            + ['closingBalance' => $runningBalance];
    }

    private function computeBalanceAt(Account $account, Carbon $at): float
    {
        $atDate = $at->toDateString();

        $txNet = $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.date', '<=', $atDate)
            ->selectRaw("
                SUM(CASE WHEN categories.type IN ('income','liability')
                         AND NOT (categories.type='income' AND categories.name NOT IN ('Interest')
                                  AND transactions.value_date IS NOT NULL AND transactions.value_date > ?)
                    THEN transactions.amount ELSE 0 END) -
                SUM(CASE WHEN categories.type='expense' THEN transactions.amount ELSE 0 END) AS net
            ", [$atDate])->value('net');

        $transfersInNet  = Transfer::where('to_account_id', $account->id)->where('date', '<=', $atDate)
            ->where(fn($q) => $q->whereNull('value_date')->orWhere('value_date', '<=', $atDate))->sum('amount');
        $transfersOutNet = Transfer::where('from_account_id', $account->id)->where('date', '<=', $atDate)->sum('amount');

        return (float)($account->initial_balance ?? 0) + (float)($txNet ?? 0) + (float)$transfersInNet - (float)$transfersOutNet;
    }
}
