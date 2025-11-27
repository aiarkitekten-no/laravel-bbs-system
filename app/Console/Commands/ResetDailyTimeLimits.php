<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetDailyTimeLimits extends Command
{
    protected $signature = 'bbs:reset-daily-time';
    protected $description = 'Reset daily time limits for all users (run at midnight)';

    public function handle(): int
    {
        $updated = User::query()->update(['daily_time_used' => 0]);
        
        $this->info("Reset daily time limits for {$updated} users.");

        return Command::SUCCESS;
    }
}
