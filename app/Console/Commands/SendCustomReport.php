<?php

namespace App\Console\Commands;

use App\Mail\MonthlyReportMail;
use App\Models\User;
use App\Services\ReportDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendCustomReport extends Command
{
    protected $signature = 'reports:send-custom {user_id} {start_date} {end_date}';
    protected $description = 'Send custom date range financial report to a specific user';

    public function handle(ReportDataService $reportService)
    {
        $userId = $this->argument('user_id');
        $startDate = Carbon::parse($this->argument('start_date'));
        $endDate = Carbon::parse($this->argument('end_date'));

        $user = User::with('emailPreference')->find($userId);

        if (!$user) {
            $this->error("User not found!");
            return Command::FAILURE;
        }

        $this->info("Generating custom report for {$user->name} ({$user->email})...");
        $this->info("Period: {$startDate->format('M d, Y')} to {$endDate->format('M d, Y')}");

        try {
            // Generate report data
            $reportData = $reportService->generateCustomReport($user, $startDate, $endDate);

            // Send email using monthly report template (similar structure)
            Mail::to($user->email)->send(new MonthlyReportMail($user, $reportData));

            $this->info("✓ Custom report sent successfully!");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("✗ Failed to send report: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
