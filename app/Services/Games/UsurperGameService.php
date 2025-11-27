<?php

namespace App\Services\Games;

use App\Models\User;

/**
 * Usurper - Medieval arena combat game
 */
class UsurperGameService extends BaseGameService
{
    private array $classes = [
        'warrior' => ['hp' => 100, 'attack' => 15, 'defense' => 10, 'magic' => 0],
        'mage' => ['hp' => 60, 'attack' => 8, 'defense' => 5, 'magic' => 20],
        'rogue' => ['hp' => 75, 'attack' => 12, 'defense' => 8, 'magic' => 5],
    ];

    public function start(User $user): array
    {
        return [
            'message' => 'Welcome to the Arena! Choose your class.',
            'classes' => array_keys($this->classes),
            'class_stats' => $this->classes,
            'action_required' => 'choose_class',
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $state = $this->getPlayerState($user)->state;

        if ($action === 'choose_class' || empty($state)) {
            return $this->chooseClass($user, $data);
        }

        if (!($state['alive'] ?? true)) {
            return [
                'game_over' => true,
                'message' => 'You have fallen in the arena!',
                'score' => $state['wins'] * 100 + $state['gold'],
            ];
        }

        switch ($action) {
            case 'fight':
                return $this->fight($user, $state);
            case 'rest':
                return $this->rest($user, $state);
            case 'train':
                return $this->train($user, $state, $data);
            case 'status':
                return $this->status($state);
            default:
                return ['error' => 'Unknown action', 'game_over' => false];
        }
    }

    private function chooseClass(User $user, array $data): array
    {
        $class = $data['class'] ?? null;

        if (!isset($this->classes[$class])) {
            return ['error' => 'Invalid class', 'game_over' => false];
        }

        $classStats = $this->classes[$class];
        $initialState = [
            'class' => $class,
            'level' => 1,
            'hp' => $classStats['hp'],
            'max_hp' => $classStats['hp'],
            'attack' => $classStats['attack'],
            'defense' => $classStats['defense'],
            'magic' => $classStats['magic'],
            'gold' => 100,
            'wins' => 0,
            'losses' => 0,
            'alive' => true,
            'fights_today' => 0,
            'max_fights' => 10,
        ];

        $this->updateState($user, $initialState);

        return [
            'message' => "You are now a {$class}! Enter the arena!",
            'state' => $initialState,
            'commands' => ['fight', 'rest', 'train', 'status'],
            'game_over' => false,
        ];
    }

    private function fight(User $user, array $state): array
    {
        if ($state['fights_today'] >= $state['max_fights']) {
            return ['error' => 'No more fights today. Rest to continue tomorrow.', 'game_over' => false];
        }

        if ($state['hp'] < $state['max_hp'] * 0.2) {
            return ['error' => 'Too wounded to fight. Rest first.', 'game_over' => false];
        }

        // Generate opponent
        $opponentLevel = max(1, $state['level'] + random_int(-1, 1));
        $opponent = [
            'name' => $this->generateOpponentName(),
            'hp' => 50 + ($opponentLevel * 20),
            'attack' => 5 + ($opponentLevel * 3),
            'defense' => 3 + ($opponentLevel * 2),
        ];

        $opponentHp = $opponent['hp'];
        $log = [];
        $log[] = "You face {$opponent['name']} (Level {$opponentLevel})!";

        // Combat
        while ($opponentHp > 0 && $state['hp'] > 0) {
            // Player turn
            $damage = max(1, $state['attack'] + $state['magic'] - $opponent['defense'] + random_int(-3, 5));
            $opponentHp -= $damage;
            $log[] = "You deal {$damage} damage.";

            if ($opponentHp <= 0) break;

            // Opponent turn
            $oppDamage = max(1, $opponent['attack'] - $state['defense'] + random_int(-3, 3));
            $state['hp'] -= $oppDamage;
            $log[] = "{$opponent['name']} deals {$oppDamage} damage.";
        }

        $state['fights_today']++;

        if ($state['hp'] <= 0) {
            $state['hp'] = 0;
            $state['alive'] = false;
            $state['losses']++;
            $this->updateState($user, $state);

            return [
                'message' => 'You have been defeated!',
                'combat_log' => $log,
                'game_over' => true,
                'score' => $state['wins'] * 100 + $state['gold'],
            ];
        }

        // Victory
        $goldWon = 50 + ($opponentLevel * 25);
        $state['gold'] += $goldWon;
        $state['wins']++;

        // Level up every 3 wins
        if ($state['wins'] % 3 === 0) {
            $state['level']++;
            $state['max_hp'] += 15;
            $state['attack'] += 2;
            $state['defense'] += 1;
            $log[] = "LEVEL UP! Now level {$state['level']}!";
        }

        $this->updateState($user, $state);

        return [
            'message' => "Victory over {$opponent['name']}!",
            'combat_log' => $log,
            'gold_won' => $goldWon,
            'wins' => $state['wins'],
            'hp' => $state['hp'],
            'level' => $state['level'],
            'fights_remaining' => $state['max_fights'] - $state['fights_today'],
            'game_over' => false,
        ];
    }

    private function rest(User $user, array $state): array
    {
        $cost = 50;
        if ($state['gold'] < $cost) {
            return ['error' => "Resting costs {$cost} gold", 'game_over' => false];
        }

        $state['gold'] -= $cost;
        $state['hp'] = $state['max_hp'];
        $state['fights_today'] = 0;

        $this->updateState($user, $state);

        return [
            'message' => 'You rest and recover fully.',
            'hp' => $state['hp'],
            'gold' => $state['gold'],
            'fights_remaining' => $state['max_fights'],
            'game_over' => false,
        ];
    }

    private function train(User $user, array $state, array $data): array
    {
        $stat = $data['stat'] ?? 'attack';
        $cost = $state['level'] * 100;

        if ($state['gold'] < $cost) {
            return ['error' => "Training costs {$cost} gold", 'game_over' => false];
        }

        if (!in_array($stat, ['attack', 'defense', 'magic'])) {
            return ['error' => 'Invalid stat. Choose attack, defense, or magic.', 'game_over' => false];
        }

        $state['gold'] -= $cost;
        $state[$stat] += 2;

        $this->updateState($user, $state);

        return [
            'message' => "Trained {$stat}! +2",
            $stat => $state[$stat],
            'gold' => $state['gold'],
            'game_over' => false,
        ];
    }

    private function status(array $state): array
    {
        return [
            'class' => $state['class'],
            'level' => $state['level'],
            'hp' => $state['hp'],
            'max_hp' => $state['max_hp'],
            'attack' => $state['attack'],
            'defense' => $state['defense'],
            'magic' => $state['magic'],
            'gold' => $state['gold'],
            'wins' => $state['wins'],
            'losses' => $state['losses'],
            'fights_remaining' => $state['max_fights'] - $state['fights_today'],
            'game_over' => false,
        ];
    }

    private function generateOpponentName(): string
    {
        $prefixes = ['Dark', 'Shadow', 'Iron', 'Blood', 'Storm', 'Fire', 'Ice'];
        $names = ['Knight', 'Warrior', 'Assassin', 'Berserker', 'Champion', 'Gladiator'];
        return $prefixes[array_rand($prefixes)] . ' ' . $names[array_rand($names)];
    }
}
