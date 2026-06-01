<?php

namespace App\Console\Commands;

use App\Mail\AnnualReportMail;
use App\Models\User;
use App\Services\ReportDataService;
use App\Services\StatementDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAnnualReports extends Command
{
    protected $signature   = 'reports:send-annual
                              {--force : Force send regardless of schedule}
                              {--user= : Only send to this user ID}';

    protected $description = 'Send annual financial reports to users (runs on Jan 1st); attaches Etica statement PDF for users who have an Etica savings account';

    public function handle(ReportDataService $reportService, StatementDataService $statementService): int
    {
        $this->info('Starting annual report generation...');

        if (! $this->option('force') && ! (now()->month === 1 && now()->day === 1)) {
            $this->info('Not January 1st — skipping. Use --force to override.');
            return Command::SUCCESS;
        }

        // Annual statements cover the full previous year.
        $from   = now()->subYear()->startOfYear();
        $to     = now()->subYear()->endOfYear();
        $period = $from->format('Y');

        $query = User::whereHas('emailPreference', function ($q) {
            $q->where('annual_reports', true);

            if (! $this->option('force')) {
                $q->where(function ($inner) {
                    $inner->whereNull('last_annual_sent')
                        ->orWhere('last_annual_sent', '<', now()->subDays(360));
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
        $this->info("Found {$users->count()} users to send reports to.");

        $successCount = 0;
        $failCount    = 0;

        foreach ($users as $user) {
            try {
                $this->info("Generating annual report for {$user->name} ({$user->email})...");

                $reportData = $reportService->generateAnnualReport($user);

                // Build Etica statement data for each account the user holds.
                // The Mailable owns PDF generation — no temp files here.
                $eticaStatements = $user->accounts
                    ->map(fn($account) => [
                        'account'       => $account,
                        'statementData' => $statementService->buildStatementData($account, $from, $to),
                        'period'        => $period,
                    ])
                    ->all();

                $mailable = (new AnnualReportMail($user, $reportData))
                    ->withEticaStatements($eticaStatements);

                Mail::to($user->email)->send($mailable);

                $user->emailPreference->update(['last_annual_sent' => now()]);

                $label = empty($eticaStatements) ? '' : ' + Etica statement';
                $this->info("  ✓ Report{$label} sent to {$user->email}");
                $successCount++;

            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for {$user->email}: {$e->getMessage()}");
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
