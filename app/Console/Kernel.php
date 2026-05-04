<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\SendMonthlyReports::class,
        Commands\SendAnnualReports::class,
        Commands\SendCustomReport::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Check for weekly reports every hour (commands handle day/time filtering)
        $schedule->command('reports:send-annual')
            ->hourly()
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('Annual reports sent successfully');
            })
            ->onFailure(function () {
                \Log::error('Annual reports failed');
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
