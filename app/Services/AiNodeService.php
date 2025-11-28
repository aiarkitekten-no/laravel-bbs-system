<?php

namespace App\Services;

use App\Models\User;
use App\Models\Node;
use App\Models\Oneliner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiNodeService
{
    protected array $config;
    protected string $logChannel = 'ai';

    public function __construct()
    {
        $this->config = config('ai');
    }

    /**
     * Check if AI nodes are enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['nodes']['enabled'] ?? false;
    }

    /**
     * Get or create AI users
     */
    public function getAiUsers(): array
    {
        $usernames = $this->config['personalities']['usernames'] ?? ['AIBot'];
        $users = [];

        foreach ($usernames as $username) {
            $username = trim($username);
            $user = User::where('handle', $username)->first();

            if (!$user) {
                // Use createWithDefaults for proper secure attribute setting
                $user = User::createWithDefaults([
                    'handle' => $username,
                    'email' => Str::slug($username) . '@ai.punktet.no',
                    'password' => bcrypt(Str::random(32)),
                    'name' => $username,
                    'location' => $this->getRandomLocation(),
                ], User::LEVEL_USER, 1000);
                
                // Set bot status using secure setter
                $user->setIsBot(true, $username);
                $user->save();
                
                $this->log("Created AI user: {$username}");
            }

            $users[] = $user;
        }

        return $users;
    }

    /**
     * Simulate AI node activity
     */
    public function simulateActivity(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $aiUsers = $this->getAiUsers();
        $maxNodes = min($this->config['nodes']['count'] ?? 2, count($aiUsers));

        // Get available nodes (not used by real users)
        $availableNodes = Node::whereNull('current_user_id')
            ->orWhereHas('currentUser', fn($q) => $q->where('is_bot', true))
            ->orderBy('node_number')
            ->limit($maxNodes)
            ->get();

        if ($availableNodes->isEmpty()) {
            $this->log('No available nodes for AI simulation');
            return;
        }

        $usedUsers = [];

        foreach ($availableNodes as $node) {
            // Check if node already has an AI user
            if ($node->current_user_id) {
                $currentUser = $node->currentUser;
                if ($currentUser && $currentUser->is_bot) {
                    // Update activity for existing AI user
                    $this->updateAiActivity($node, $currentUser);
                    $usedUsers[] = $currentUser->id;
                    continue;
                }
            }

            // Find an unused AI user
            $availableAiUsers = array_filter($aiUsers, fn($u) => !in_array($u->id, $usedUsers));
            if (empty($availableAiUsers)) {
                continue;
            }

            $aiUser = $availableAiUsers[array_rand($availableAiUsers)];
            $usedUsers[] = $aiUser->id;

            // Connect AI user to node
            $this->connectAiToNode($node, $aiUser);
        }

        // Occasionally post an oneliner (15% chance)
        if ($this->shouldPostOneliner()) {
            try {
                $this->postAiOneliner($aiUsers);
            } catch (\Exception $e) {
                $this->log("Oneliner error: " . $e->getMessage(), 'error');
            }
        }

        // Occasionally vote on a story (10% chance)
        if (rand(1, 100) <= 10) {
            try {
                $this->aiVoteOnStory($aiUsers);
            } catch (\Exception $e) {
                $this->log("Story vote error: " . $e->getMessage(), 'error');
            }
        }

        // Occasionally vote on a poll (5% chance)
        if (rand(1, 100) <= 5) {
            try {
                $this->aiVoteOnPoll($aiUsers);
            } catch (\Exception $e) {
                $this->log("Poll vote error: " . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * Connect AI user to a node
     */
    protected function connectAiToNode(Node $node, User $user): void
    {
        $node->update([
            'current_user_id' => $user->id,
            'current_activity' => $this->getRandomActivity(),
            'user_connected_at' => now(),
            'status' => 'ONLINE',
        ]);

        $user->update([
            'is_online' => true,
            'last_activity_at' => now(),
        ]);

        $this->log("AI user {$user->handle} connected to node {$node->node_number}");
    }

    /**
     * Update AI user activity
     */
    protected function updateAiActivity(Node $node, User $user): void
    {
        // Check if AI should disconnect
        $connectedMinutes = $node->user_connected_at 
            ? now()->diffInMinutes($node->user_connected_at) 
            : 0;

        $maxMinutes = ($this->config['nodes']['max_session_time'] ?? 1800) / 60;
        $minMinutes = ($this->config['nodes']['min_session_time'] ?? 300) / 60;

        // Random chance to disconnect after minimum time
        if ($connectedMinutes >= $minMinutes && rand(1, 100) <= 10) {
            $this->disconnectAiFromNode($node, $user);
            return;
        }

        // Force disconnect after max time
        if ($connectedMinutes >= $maxMinutes) {
            $this->disconnectAiFromNode($node, $user);
            return;
        }

        // Update activity
        $node->update([
            'current_activity' => $this->getRandomActivity(),
        ]);

        $user->update([
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Disconnect AI from node
     */
    protected function disconnectAiFromNode(Node $node, User $user): void
    {
        $node->update([
            'current_user_id' => null,
            'current_activity' => null,
            'user_connected_at' => null,
        ]);

        $user->update([
            'is_online' => false,
            'last_login_at' => now(),
            'total_logins' => $user->total_logins + 1,
        ]);

        $this->log("AI user {$user->handle} disconnected from node {$node->node_number}");
    }

    /**
     * Get random activity
     */
    protected function getRandomActivity(): string
    {
        $activities = $this->config['builtin_activities'] 
            ?? $this->config['personalities']['activities'] 
            ?? ['Browsing'];

        return $activities[array_rand($activities)];
    }

    /**
     * Get random location
     */
    protected function getRandomLocation(): string
    {
        $locations = $this->config['personalities']['locations'] ?? ['Cyberspace'];
        return trim($locations[array_rand($locations)]);
    }

    /**
     * Check if AI should post oneliner
     */
    protected function shouldPostOneliner(): bool
    {
        if (!($this->config['oneliners']['enabled'] ?? false)) {
            return false;
        }

        // 15% chance per cycle (every 30 seconds = roughly every 3-4 minutes)
        return rand(1, 100) <= 15;
    }

    /**
     * Post AI oneliner
     */
    protected function postAiOneliner(array $aiUsers): void
    {
        if (empty($aiUsers)) {
            $this->log("No AI users available for oneliner");
            return;
        }

        // Only use online AI users
        $onlineAiUsers = array_filter($aiUsers, fn($u) => $u->is_online);
        if (empty($onlineAiUsers)) {
            $onlineAiUsers = $aiUsers; // Fallback to any AI user
        }

        $user = $onlineAiUsers[array_rand($onlineAiUsers)];
        $oneliners = $this->config['builtin_oneliners'] ?? ['Hello from the AI!'];
        $content = $oneliners[array_rand($oneliners)];

        // Check if same content was posted recently
        $recentExists = Oneliner::where('content', $content)
            ->where('created_at', '>', now()->subHours(12))
            ->exists();

        if ($recentExists) {
            $this->log("Skipping duplicate oneliner: {$content}");
            return;
        }

        try {
            Oneliner::create([
                'user_id' => $user->id,
                'content' => $content,
            ]);

            $this->log("AI user {$user->handle} posted oneliner: {$content}");
        } catch (\Exception $e) {
            $this->log("Failed to post oneliner: " . $e->getMessage(), 'error');
        }
    }

    /**
     * AI votes on a random story
     */
    protected function aiVoteOnStory(array $aiUsers): void
    {
        if (empty($aiUsers)) {
            return;
        }

        $user = $aiUsers[array_rand($aiUsers)];

        // Get a random story
        $story = \App\Models\Story::inRandomOrder()->first();
        if (!$story) {
            return;
        }

        // Check if already voted
        $existingVote = \App\Models\StoryVote::where('user_id', $user->id)
            ->where('story_id', $story->id)
            ->first();

        if ($existingVote) {
            return;
        }

        // Vote (80% upvote, 20% downvote)
        $voteValue = rand(1, 100) <= 80 ? 1 : -1;

        try {
            \App\Models\StoryVote::create([
                'story_id' => $story->id,
                'user_id' => $user->id,
                'vote' => $voteValue,
            ]);

            $this->log("AI user {$user->handle} voted {$voteValue} on story '{$story->title}'");
        } catch (\Exception $e) {
            $this->log("Failed to vote on story: " . $e->getMessage(), 'error');
        }
    }

    /**
     * AI votes on a random poll
     */
    protected function aiVoteOnPoll(array $aiUsers): void
    {
        if (empty($aiUsers)) {
            return;
        }

        $user = $aiUsers[array_rand($aiUsers)];

        // Get an active poll
        $poll = \App\Models\Poll::where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->inRandomOrder()
            ->first();

        if (!$poll) {
            return;
        }

        // Check if already voted
        $existingVote = \App\Models\PollVote::where('user_id', $user->id)
            ->where('poll_id', $poll->id)
            ->first();

        if ($existingVote) {
            return;
        }

        // Get a random option
        $option = $poll->options()->inRandomOrder()->first();
        if (!$option) {
            return;
        }

        try {
            \App\Models\PollVote::create([
                'poll_id' => $poll->id,
                'poll_option_id' => $option->id,
                'user_id' => $user->id,
            ]);

            $this->log("AI user {$user->handle} voted on poll '{$poll->question}' option '{$option->option_text}'");
        } catch (\Exception $e) {
            $this->log("Failed to vote on poll: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Disconnect all AI users
     */
    public function disconnectAll(): int
    {
        $count = 0;
        
        $aiNodes = Node::whereHas('currentUser', fn($q) => $q->where('is_bot', true))->get();
        
        foreach ($aiNodes as $node) {
            $user = $node->currentUser;
            if ($user) {
                $this->disconnectAiFromNode($node, $user);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get AI status
     */
    public function getStatus(): array
    {
        $aiUsers = User::where('is_bot', true)->get();
        $onlineAi = $aiUsers->filter(fn($u) => $u->is_online);

        return [
            'enabled' => $this->isEnabled(),
            'total_ai_users' => $aiUsers->count(),
            'online_ai_users' => $onlineAi->count(),
            'config' => [
                'max_nodes' => $this->config['nodes']['count'] ?? 2,
                'activity_interval' => $this->config['nodes']['activity_interval'] ?? 30,
            ],
        ];
    }

    /**
     * Log AI activity
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if (!($this->config['logging']['enabled'] ?? false)) {
            return;
        }

        Log::channel('single')->{$level}("[AI] {$message}");
    }
}
