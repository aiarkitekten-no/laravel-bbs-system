<?php

namespace App\Console\Commands;

use App\Services\AiNodeService;
use Illuminate\Console\Command;

class AiSimulate extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:simulate 
                            {--once : Run once instead of continuously}
                            {--disconnect : Disconnect all AI users}
                            {--status : Show AI status}';

    /**
     * The console command description.
     */
    protected $description = 'Simulate AI node activity on the BBS';

    /**
     * Execute the console command.
     */
    public function handle(AiNodeService $aiService): int
    {
        if ($this->option('status')) {
            return $this->showStatus($aiService);
        }

        if ($this->option('disconnect')) {
            return $this->disconnectAll($aiService);
        }

        if (!$aiService->isEnabled()) {
            $this->error('AI simulation is disabled. Set AI_NODES_ENABLED=true in .env.ai');
            return 1;
        }

        if ($this->option('once')) {
            return $this->runOnce($aiService);
        }

        return $this->runContinuously($aiService);
    }

    /**
     * Show AI status
     */
    protected function showStatus(AiNodeService $aiService): int
    {
        $status = $aiService->getStatus();

        $this->info('=== AI Simulation Status ===');
        $this->line('');
        $this->line('Enabled: ' . ($status['enabled'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line('Total AI Users: ' . $status['total_ai_users']);
        $this->line('Currently Online: ' . $status['online_ai_users']);
        $this->line('Max Nodes: ' . $status['config']['max_nodes']);
        $this->line('Activity Interval: ' . $status['config']['activity_interval'] . 's');
        $this->line('');

        return 0;
    }

    /**
     * Disconnect all AI users
     */
    protected function disconnectAll(AiNodeService $aiService): int
    {
        $count = $aiService->disconnectAll();
        $this->info("Disconnected {$count} AI users.");
        return 0;
    }

    /**
     * Run simulation once
     */
    protected function runOnce(AiNodeService $aiService): int
    {
        $this->info('Running AI simulation once...');
        $aiService->simulateActivity();
        $this->info('Done!');
        return 0;
    }

    /**
     * Run simulation continuously
     */
    protected function runContinuously(AiNodeService $aiService): int
    {
        $interval = config('ai.nodes.activity_interval', 30);

        $this->info("Starting AI simulation (interval: {$interval}s)");
        $this->info('Press Ctrl+C to stop.');
        $this->line('');

        while (true) {
            $aiService->simulateActivity();
            
            $status = $aiService->getStatus();
            $this->line(
                sprintf(
                    '[%s] AI users online: %d/%d',
                    now()->format('H:i:s'),
                    $status['online_ai_users'],
                    $status['config']['max_nodes']
                )
            );

            sleep($interval);
        }

        return 0;
    }
}
