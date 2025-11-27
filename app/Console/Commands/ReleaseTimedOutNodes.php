<?php

namespace App\Console\Commands;

use App\Models\Node;
use Illuminate\Console\Command;

class ReleaseTimedOutNodes extends Command
{
    protected $signature = 'bbs:release-timed-out {--minutes=15 : Minutes of inactivity before timeout}';
    protected $description = 'Release nodes with timed out users';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $released = Node::releaseTimedOutNodes($minutes);

        if ($released > 0) {
            $this->info("Released {$released} timed out node(s).");
        } else {
            $this->info("No timed out nodes found.");
        }

        return Command::SUCCESS;
    }
}
