<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Bot activity simulation - runs every minute
        $schedule->command('bbs:simulate-bots')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Release timed out nodes - runs every 5 minutes
        $schedule->command('bbs:release-timed-out --minutes=15')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Reset daily time limits - runs at midnight
        $schedule->command('bbs:reset-daily-time')
            ->dailyAt('00:00')
            ->timezone('Europe/Oslo');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
