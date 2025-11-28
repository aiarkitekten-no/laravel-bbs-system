<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiLifeService;
use App\Services\AiNodeService;

class AiLifeSimulator extends Command
{
    protected $signature = 'ai:life 
        {--once : Run once and exit}
        {--setup : Setup AI users only}
        {--story : Generate a story now}
        {--status : Show AI status}
        {--daemon : Run as background daemon (default)}';

    protected $description = 'Run AI life simulation - creates natural BBS activity';

    protected AiLifeService $lifeService;
    protected AiNodeService $nodeService;

    public function handle(): int
    {
        $this->lifeService = app(AiLifeService::class);
        $this->nodeService = app(AiNodeService::class);

        if ($this->option('setup')) {
            return $this->setupAiUsers();
        }

        if ($this->option('story')) {
            return $this->generateStoryNow();
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('once')) {
            return $this->runOnce();
        }

        return $this->runDaemon();
    }

    protected function setupAiUsers(): int
    {
        $this->info('Setting up AI users...');
        
        $created = $this->lifeService->ensureAiUsersExist();
        
        if (empty($created)) {
            $this->info('All AI users already exist.');
        } else {
            $this->info('Created ' . count($created) . ' AI users:');
            foreach ($created as $name) {
                $this->line("  - {$name}");
            }
        }

        return 0;
    }

    protected function generateStoryNow(): int
    {
        $this->info('Generating a story...');
        
        // Force create a story
        $result = $this->lifeService->runLifeCycle();
        
        $this->info('Done! Check /api/stories/today');
        $this->table(['Key', 'Value'], collect($result)->map(fn($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->toArray());

        return 0;
    }

    protected function showStatus(): int
    {
        $this->info('AI Life Status');
        $this->line('');

        // Get AI users
        $aiUsers = \App\Models\User::where('is_bot', true)->get();
        $onlineAi = \App\Models\Node::whereHas('currentUser', fn($q) => $q->where('is_bot', true))->count();
        
        $this->table(['Metric', 'Value'], [
            ['Total AI Users', $aiUsers->count()],
            ['Online AI Users', $onlineAi],
            ['Stories Today', \App\Models\Story::whereDate('story_date', today())->count()],
            ['Oneliners Today', \App\Models\Oneliner::whereDate('created_at', today())->count()],
            ['Forum Posts Today', \App\Models\Message::whereDate('created_at', today())->count()],
        ]);

        $this->line('');
        $this->info('Online AI Users:');
        
        $nodes = \App\Models\Node::with('currentUser')
            ->whereHas('currentUser', fn($q) => $q->where('is_bot', true))
            ->get();

        foreach ($nodes as $node) {
            $this->line("  Node {$node->node_number}: {$node->currentUser->handle} - {$node->current_activity}");
        }

        return 0;
    }

    protected function runOnce(): int
    {
        $this->info('Running single AI life cycle...');
        
        // Ensure nodes are populated
        $this->nodeService->simulateActivity();
        
        // Run life actions
        $result = $this->lifeService->runLifeCycle();
        
        $this->info("Completed {$result['actions']} actions.");
        
        if (!empty($result['details'])) {
            foreach ($result['details'] as $action) {
                $this->line("  [{$action['type']}] {$action['user']}: " . ($action['content'] ?? $action['title'] ?? $action['message'] ?? ''));
            }
        }

        return 0;
    }

    protected function runDaemon(): int
    {
        $this->info('Starting AI Life Daemon');
        $this->info('Running every 6-10 minutes (5-11 actions per hour)');
        $this->info('Press Ctrl+C to stop.');
        $this->line('');

        // Ensure AI users exist
        try {
            $this->lifeService->ensureAiUsersExist();
        } catch (\Exception $e) {
            $this->error('Failed to setup AI users: ' . $e->getMessage());
        }

        $errorCount = 0;
        $maxErrors = 10;

        while (true) {
            try {
                $hour = (int) date('H');
                
                // Night mode (23:00 - 06:00)
                if ($hour >= 23 || $hour < 6) {
                    $this->line('[' . date('H:i:s') . '] Night mode - sleeping...');
                    sleep(300); // Check every 5 minutes during night
                    continue;
                }

                // Update node activity
                try {
                    $this->nodeService->simulateActivity();
                } catch (\Exception $e) {
                    $this->warn('[' . date('H:i:s') . '] Node activity error: ' . substr($e->getMessage(), 0, 100));
                }
                
                // Run life cycle
                $result = $this->lifeService->runLifeCycle();
                
                $actionsStr = $result['actions'] > 0 ? "{$result['actions']} actions" : "no actions";
                $this->line('[' . date('H:i:s') . "] {$actionsStr}");
                
                if (!empty($result['details'])) {
                    foreach ($result['details'] as $action) {
                        $detail = $action['content'] ?? $action['title'] ?? $action['message'] ?? '';
                        $this->line("    [{$action['type']}] {$action['user']}: " . substr($detail, 0, 40));
                    }
                }

                // Reset error count on success
                $errorCount = 0;

            } catch (\Exception $e) {
                $errorCount++;
                $this->error('[' . date('H:i:s') . '] Error (' . $errorCount . '/' . $maxErrors . '): ' . $e->getMessage());
                
                if ($errorCount >= $maxErrors) {
                    $this->error('Too many consecutive errors. Stopping daemon.');
                    return 1;
                }
                
                // Short sleep after error
                sleep(60);
                continue;
            }

            // Random sleep 6-10 minutes
            $sleepMinutes = rand(6, 10);
            sleep($sleepMinutes * 60);
        }

        return 0;
    }
}
