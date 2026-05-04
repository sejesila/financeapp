<?php
namespace App\Console\Commands;
use App\Mail\AnnualReportMail;
use App\Models\User;
use App\Services\ReportDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAnnualReports extends Command
{
    protected $signature = 'reports:send-annual {--force : Force send regardless of schedule}';
    protected $description = 'Send annual financial reports to users';

    public function handle(ReportDataService $reportService)
    {
        $this->info('Starting annual report generation...');

        $currentDay   = now()->day;
        $currentMonth = now()->month;

        // Get users who want annual reports
        $users = User::whereHas('emailPreference', function ($query) use ($currentDay, $currentMonth) {
            $query->where('annual_reports', true)
                ->where('annual_day', $currentDay)
                ->where('annual_month', $currentMonth);

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

                $user->emailPreference->update([
                    'last_annual_sent' => now()
                ]);

                $this->info("✓ Annual report sent to {$user->email}");
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
