<?php

namespace App\Console\Commands;

use App\Models\Node;
use App\Models\User;
use Illuminate\Console\Command;

class SimulateBotActivity extends Command
{
    protected $signature = 'bbs:simulate-bots';
    protected $description = 'Simulate bot user activity on nodes';

    // Activities bots can perform
    protected array $activities = [
        'Reading messages',
        'Writing a message',
        'Browsing files',
        'Playing Trade Wars',
        'Playing Legend of the Red Dragon',
        'Playing Barren Realms Elite',
        'In OneLiners',
        'Reading stories',
        'Viewing user list',
        'In chat',
        'Reading bulletins',
        'Checking new files',
        'Reading AI Story',
        'Playing Trivia',
        'Viewing ANSI art',
    ];

    // Time-based activity weights (Norwegian timezone)
    protected array $timeWeights = [
        // Night (00-06): Low activity
        0 => 10, 1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 10,
        // Morning (06-12): Medium activity
        6 => 20, 7 => 30, 8 => 40, 9 => 50, 10 => 60, 11 => 65,
        // Afternoon (12-18): High activity
        12 => 70, 13 => 75, 14 => 80, 15 => 85, 16 => 90, 17 => 95,
        // Evening (18-24): Peak activity
        18 => 100, 19 => 100, 20 => 95, 21 => 90, 22 => 80, 23 => 50,
    ];

    public function handle(): int
    {
        $currentHour = (int) now()->format('H');
        $activityChance = $this->timeWeights[$currentHour];

        $this->info("Bot simulation running at hour {$currentHour} with {$activityChance}% activity chance");

        // Get all bots
        $bots = User::bots()->get();
        $availableNodes = Node::available()->count();
        $occupiedByBots = Node::whereHas('currentUser', fn($q) => $q->where('is_bot', true))->count();

        // Calculate desired bot count based on time
        $maxBots = min(4, $availableNodes + $occupiedByBots); // Max 4 bots, leave 2 nodes for humans
        $desiredBots = (int) ceil($maxBots * ($activityChance / 100));

        $this->info("Currently {$occupiedByBots} bots online, desired: {$desiredBots}");

        // Login more bots if needed
        if ($occupiedByBots < $desiredBots) {
            $this->loginBots($bots, $desiredBots - $occupiedByBots);
        }
        // Logout some bots if too many
        elseif ($occupiedByBots > $desiredBots) {
            $this->logoutBots($occupiedByBots - $desiredBots);
        }

        // Update activity for online bots
        $this->updateBotActivities();

        // Random bot logout (natural behavior)
        if (rand(1, 100) <= 15) { // 15% chance
            $this->randomBotLogout();
        }

        return Command::SUCCESS;
    }

    protected function loginBots($bots, int $count): void
    {
        $offlineBots = $bots->where('is_online', false)->shuffle()->take($count);

        foreach ($offlineBots as $bot) {
            $node = Node::getFirstAvailable();
            if (!$node) {
                break;
            }

            $node->assignUser($bot);
            $bot->updateLastLogin('127.0.0.1');
            $this->info("Bot '{$bot->handle}' logged in to node {$node->node_number}");
        }
    }

    protected function logoutBots(int $count): void
    {
        $onlineBots = Node::with('currentUser')
            ->whereHas('currentUser', fn($q) => $q->where('is_bot', true))
            ->orderBy('user_connected_at')
            ->take($count)
            ->get();

        foreach ($onlineBots as $node) {
            $handle = $node->currentUser->handle;
            $node->releaseUser();
            $this->info("Bot '{$handle}' logged out from node {$node->node_number}");
        }
    }

    protected function updateBotActivities(): void
    {
        $botNodes = Node::with('currentUser')
            ->whereHas('currentUser', fn($q) => $q->where('is_bot', true))
            ->get();

        foreach ($botNodes as $node) {
            // 30% chance to change activity
            if (rand(1, 100) <= 30) {
                $activity = $this->activities[array_rand($this->activities)];
                $node->updateActivity($activity);
                $this->info("Bot '{$node->currentUser->handle}' now: {$activity}");
            }
        }
    }

    protected function randomBotLogout(): void
    {
        $botNode = Node::with('currentUser')
            ->whereHas('currentUser', fn($q) => $q->where('is_bot', true))
            ->inRandomOrder()
            ->first();

        if ($botNode) {
            $handle = $botNode->currentUser->handle;
            $botNode->releaseUser();
            $this->info("Bot '{$handle}' randomly logged out");
        }
    }
}
