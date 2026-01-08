<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\SendWeeklyReports::class,
        Commands\SendMonthlyReports::class,
        Commands\SendCustomReport::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Check for weekly reports every hour (commands handle day/time filtering)
        $schedule->command('reports:send-weekly')
            ->hourly()
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('Weekly reports sent successfully');
            })
            ->onFailure(function () {
                \Log::error('Weekly reports failed');
            });

        // Check for monthly reports every hour
        $schedule->command('reports:send-monthly')
            ->hourly()
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('Monthly reports sent successfully');
            })
            ->onFailure(function () {
                \Log::error('Monthly reports failed');
            });
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
