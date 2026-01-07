<?php

namespace App\Console\Commands;

use App\Mail\WeeklyReportMail;
use App\Models\User;
use App\Services\ReportDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendWeeklyReports extends Command
{
    protected $signature = 'reports:send-weekly {--force : Force send regardless of schedule}';
    protected $description = 'Send weekly financial reports to users';

    public function handle(ReportDataService $reportService)
    {
        $this->info('Starting weekly report generation...');

        $currentDay = strtolower(now()->format('l')); // e.g., 'monday'
        $currentTime = now()->format('H:i');

        // Get users who want weekly reports
        $users = User::whereHas('emailPreference', function($query) use ($currentDay, $currentTime) {
            $query->where('weekly_reports', true)
                ->where('weekly_day', $currentDay);

            if (!$this->option('force')) {
                $query->where(function($q) use ($currentTime) {
                    $q->whereNull('last_weekly_sent')
                        ->orWhere('last_weekly_sent', '<', now()->subDays(6));
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
                $reportData = $reportService->generateWeeklyReport($user);

                // Send email
                Mail::to($user->email)->send(new WeeklyReportMail($user, $reportData));

                // Update last sent timestamp
                $user->emailPreference->update([
                    'last_weekly_sent' => now()
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
