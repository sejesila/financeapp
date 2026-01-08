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
        // Send weekly reports every day at the user's preferred time
        // The command internally checks if it's the right day (Monday, Tuesday, etc.)
        $schedule->command('reports:send-weekly')
            ->dailyAt('00:00')  // Run at midnight, command handles user's preferred times
            ->timezone('Africa/Nairobi')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('Weekly reports check completed successfully');
            })
            ->onFailure(function () {
                \Log::error('Weekly reports check failed');
            });

        // Send monthly reports every day at midnight
        // The command checks if it's the user's chosen day of the month
        $schedule->command('reports:send-monthly')
            ->dailyAt('00:00')  // Run at midnight, command handles user's preferred times
            ->timezone('Africa/Nairobi')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('Monthly reports check completed successfully');
            })
            ->onFailure(function () {
                \Log::error('Monthly reports check failed');
            });
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
