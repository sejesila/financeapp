<?php

namespace App\Console\Commands;

use App\Mail\AnnualReportMail;
use App\Mail\MonthlyReportMail;
use App\Models\User;
use App\Services\ReportDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendCustomReport extends Command
{
    protected $signature   = 'reports:send-custom {user_id} {start_date} {end_date}';
    protected $description = 'Send a custom date-range financial report to a specific user';

    public function handle(ReportDataService $reportService)
    {
        $userId    = $this->argument('user_id');
        $startDate = Carbon::parse($this->argument('start_date'))->startOfDay();
        $endDate   = Carbon::parse($this->argument('end_date'))->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            $this->error('start_date must be before end_date.');
            return Command::FAILURE;
        }

        $user = User::with('emailPreference')->find($userId);

        if (!$user) {
            $this->error("User {$userId} not found.");
            return Command::FAILURE;
        }

        $this->info("Generating custom report for {$user->name} ({$user->email})");
        $this->info("Period: {$startDate->format('M d, Y')} to {$endDate->format('M d, Y')}");

        try {
            $reportData = $reportService->generateCustomReport($user, $startDate, $endDate);

            // Use the annual mail template for year-length ranges, monthly otherwise.
            // Both templates accept the same data shape.
            $daySpan  = $startDate->diffInDays($endDate);
            $mailable = $daySpan >= 300
                ? new AnnualReportMail($user, $reportData)
                : new MonthlyReportMail($user, $reportData);

            Mail::to($user->email)->send($mailable);

            $this->info("Custom report sent successfully.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
