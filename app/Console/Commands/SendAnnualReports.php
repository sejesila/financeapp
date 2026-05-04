<?php

namespace App\Console\Commands;

use App\Mail\AnnualReportMail;
use App\Models\User;
use App\Services\ReportDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAnnualReports extends Command
{
    protected $signature   = 'reports:send-annual {--force : Force send regardless of schedule}';
    protected $description = 'Send annual financial reports to users (runs on Jan 1st)';

    public function handle(ReportDataService $reportService)
    {
        $this->info('Starting annual report generation...');

        // Annual reports are always sent on January 1st.
        // When not forced, enforce that constraint so this command is safe to
        // schedule daily without accidentally sending on the wrong date.
        if (!$this->option('force') && !(now()->month === 1 && now()->day === 1)) {
            $this->info('Not January 1st — skipping. Use --force to override.');
            return Command::SUCCESS;
        }

        $users = User::whereHas('emailPreference', function ($query) {
            $query->where('annual_reports', true);

            if (!$this->option('force')) {
                $query->where(function ($q) {
                    $q->whereNull('last_annual_sent')
                        ->orWhere('last_annual_sent', '<', now()->subDays(360));
                });
            }
        })
            ->with('emailPreference')
            ->get();

        $this->info("Found {$users->count()} users to send reports to.");

        $successCount = 0;
        $failCount    = 0;

        foreach ($users as $user) {
            try {
                $this->info("Generating annual report for {$user->name} ({$user->email})...");

                $reportData = $reportService->generateAnnualReport($user);

                Mail::to($user->email)->send(new AnnualReportMail($user, $reportData));

                $user->emailPreference->update(['last_annual_sent' => now()]);

                $this->info("  Report sent to {$user->email}");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("  Failed for {$user->email}: {$e->getMessage()}");
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
