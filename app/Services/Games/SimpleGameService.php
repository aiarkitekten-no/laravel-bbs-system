<?php

namespace App\Services\Games;

use App\Models\User;

class SimpleGameService extends BaseGameService
{
    public function start(User $user): array
    {
        return [
            'message' => 'Game started',
            'game' => $this->game->slug,
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        return [
            'message' => 'Action processed',
            'action' => $action,
            'game_over' => false,
        ];
    }
}
