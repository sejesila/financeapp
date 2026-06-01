<?php

namespace App\Console\Commands;

use App\Mail\MonthlyReportMail;
use App\Models\User;
use App\Services\ReportDataService;
use App\Services\StatementDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMonthlyReports extends Command
{
    protected $signature = 'reports:send-monthly
                            {--force  : Force send regardless of schedule}
                            {--month= : Month to generate for (YYYY-MM), defaults to last month}
                            {--user=  : Only send to this user ID}';

    protected $description = 'Send monthly financial reports to all users; attaches Etica statement PDF for users who have an Etica savings account';

    public function handle(ReportDataService $reportService, StatementDataService $statementService): int
    {
        $this->info('Starting monthly report generation...');

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
        $this->info("Found {$users->count()} user(s) to send reports to.");

        $successCount = 0;
        $failCount    = 0;

        foreach ($users as $user) {
            try {
                $this->info("  Processing {$user->name} ({$user->email})...");

                $reportData = $reportService->generateMonthlyReport($user);

                // Build Etica statement data for each account the user holds.
                // The Mailable owns PDF generation — no temp files here.
                $eticaStatements = $user->accounts
                    ->map(fn($account) => [
                        'account'       => $account,
                        'statementData' => $statementService->buildStatementData($account, $from, $to),
                        'period'        => $period,
                    ])
                    ->all();

                $mailable = (new MonthlyReportMail($user, $reportData))
                    ->withEticaStatements($eticaStatements);

                Mail::to($user->email)->send($mailable);

                $user->emailPreference->update(['last_monthly_sent' => now()]);

                $label = empty($eticaStatements) ? '' : ' + Etica statement';
                $this->info("    ✓ Report{$label} sent to {$user->email}");
                $successCount++;

            } catch (\Throwable $e) {
                $this->error("    ✗ Failed for {$user->email}: {$e->getMessage()}");
                $failCount++;
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
}
