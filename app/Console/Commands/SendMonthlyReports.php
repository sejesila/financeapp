<?php

namespace App\Console\Commands;

use App\Mail\MonthlyReportMail;
use App\Models\User;
use App\Services\ReportDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMonthlyReports extends Command
{
    protected $signature = 'reports:send-monthly {--force : Force send regardless of schedule}';
    protected $description = 'Send monthly financial reports to users';

    public function handle(ReportDataService $reportService)
    {
        $this->info('Starting monthly report generation...');

        $currentDay = now()->day;

        // Get users who want monthly reports
        $users = User::whereHas('emailPreference', function($query) use ($currentDay) {
            $query->where('monthly_reports', true)
                ->where('monthly_day', $currentDay);

            if (!$this->option('force')) {
                $query->where(function($q) {
                    $q->whereNull('last_monthly_sent')
                        ->orWhere('last_monthly_sent', '<', now()->subDays(25));
                });
            }
        })
            ->with('emailPreference')
            ->get();

        $this->info("Found {$users->count()} users to send reports to.");

        $successCount = 0;
        $failCount = 0;

        foreach ($users as $user) {
            try {
                $this->info("Generating report for {$user->name} ({$user->email})...");

                // Generate report data
                $reportData = $reportService->generateMonthlyReport($user);

                // Send email
                Mail::to($user->email)->send(new MonthlyReportMail($user, $reportData));

                // Update last sent timestamp
                $user->emailPreference->update([
                    'last_monthly_sent' => now()
                ]);

                $this->info("✓ Report sent to {$user->email}");
                $successCount++;

            } catch (\Exception $e) {
                $this->error("✗ Failed to send report to {$user->email}: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Successfully sent: {$successCount}");
        $this->error("Failed: {$failCount}");
        $this->info("Total processed: " . ($successCount + $failCount));

        return Command::SUCCESS;
    }
}
