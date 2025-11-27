<?php

namespace App\Services\Games;

use App\Models\Game;
use App\Models\User;

abstract class BaseGameService
{
    protected Game $game;

    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    /**
     * Start a new game session
     */
    abstract public function start(User $user): array;

    /**
     * Process a player action
     */
    abstract public function play(User $user, string $action, array $data): array;

    /**
     * Get game configuration value
     */
    protected function config(string $key, $default = null)
    {
        return $this->game->config[$key] ?? $default;
    }

    /**
     * Get or create player state
     */
    protected function getPlayerState(User $user)
    {
        return $this->game->getOrCreatePlayerState($user);
    }

    /**
     * Update player state
     */
    protected function updateState(User $user, array $state): void
    {
        $playerState = $this->getPlayerState($user);
        $playerState->updateState($state);
    }

    /**
     * Get current state value
     */
    protected function getState(User $user, string $key, $default = null)
    {
        $playerState = $this->getPlayerState($user);
        return $playerState->getState($key, $default);
    }
}
