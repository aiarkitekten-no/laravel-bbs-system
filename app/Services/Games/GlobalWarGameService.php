<?php

namespace App\Services\Games;

use App\Models\User;

/**
 * Global War - Risk-style territory conquest game
 */
class GlobalWarGameService extends BaseGameService
{
    private array $territories = [
        'North America' => ['armies' => 10, 'bonus' => 5],
        'South America' => ['armies' => 8, 'bonus' => 3],
        'Europe' => ['armies' => 12, 'bonus' => 5],
        'Africa' => ['armies' => 10, 'bonus' => 3],
        'Asia' => ['armies' => 15, 'bonus' => 7],
        'Australia' => ['armies' => 6, 'bonus' => 2],
    ];

    public function start(User $user): array
    {
        // Player starts with 3 random territories
        $allTerritories = array_keys($this->territories);
        shuffle($allTerritories);

        $playerTerritories = array_slice($allTerritories, 0, 2);
        $enemyTerritories = array_slice($allTerritories, 2);

        $initialState = [
            'turn' => 1,
            'max_turns' => 50,
            'player_territories' => [],
            'enemy_territories' => [],
            'reserves' => 10,
        ];

        foreach ($playerTerritories as $t) {
            $initialState['player_territories'][$t] = 5;
        }
        foreach ($enemyTerritories as $t) {
            $initialState['enemy_territories'][$t] = random_int(3, 8);
        }

        $this->updateState($user, $initialState);

        return [
            'message' => 'Welcome to Global War! Conquer all territories to win.',
            'your_territories' => $initialState['player_territories'],
            'enemy_territories' => array_keys($initialState['enemy_territories']),
            'reserves' => $initialState['reserves'],
            'commands' => ['attack', 'fortify', 'end_turn', 'status'],
            'game_over' => false,
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $state = $this->getPlayerState($user)->state;

        // Check win/lose conditions
        if (empty($state['player_territories'])) {
            return [
                'game_over' => true,
                'won' => false,
                'message' => 'All your territories have been conquered. Defeat!',
                'score' => $state['turn'] * 10,
            ];
        }

        if (empty($state['enemy_territories'])) {
            return [
                'game_over' => true,
                'won' => true,
                'message' => 'You have conquered the world! Victory!',
                'score' => (50 - $state['turn']) * 100 + 1000,
            ];
        }

        if ($state['turn'] > $state['max_turns']) {
            $playerCount = count($state['player_territories']);
            $enemyCount = count($state['enemy_territories']);
            $won = $playerCount > $enemyCount;

            return [
                'game_over' => true,
                'won' => $won,
                'message' => $won ? 'You control more territories! Victory!' : 'Enemy controls more territories. Defeat!',
                'score' => $playerCount * 100,
            ];
        }

        switch ($action) {
            case 'deploy':
                return $this->deploy($user, $state, $data);
            case 'attack':
                return $this->attack($user, $state, $data);
            case 'fortify':
                return $this->fortify($user, $state, $data);
            case 'end_turn':
                return $this->endTurn($user, $state);
            case 'status':
                return $this->status($state);
            default:
                return ['error' => 'Unknown action', 'game_over' => false];
        }
    }

    private function deploy(User $user, array $state, array $data): array
    {
        $territory = $data['territory'] ?? null;
        $armies = (int)($data['armies'] ?? 1);

        if (!isset($state['player_territories'][$territory])) {
            return ['error' => 'You do not control this territory', 'game_over' => false];
        }

        if ($armies > $state['reserves']) {
            return ['error' => 'Not enough reserves', 'game_over' => false];
        }

        $state['player_territories'][$territory] += $armies;
        $state['reserves'] -= $armies;

        $this->updateState($user, $state);

        return [
            'message' => "Deployed {$armies} armies to {$territory}",
            'territory' => $territory,
            'armies' => $state['player_territories'][$territory],
            'reserves' => $state['reserves'],
            'game_over' => false,
        ];
    }

    private function attack(User $user, array $state, array $data): array
    {
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        $armies = (int)($data['armies'] ?? 1);

        if (!isset($state['player_territories'][$from])) {
            return ['error' => 'You do not control the attacking territory', 'game_over' => false];
        }

        if (!isset($state['enemy_territories'][$to])) {
            return ['error' => 'Invalid target territory', 'game_over' => false];
        }

        if ($armies >= $state['player_territories'][$from]) {
            return ['error' => 'Must leave at least 1 army behind', 'game_over' => false];
        }

        // Combat resolution (simplified Risk dice)
        $attackerLosses = 0;
        $defenderLosses = 0;
        $defenderArmies = $state['enemy_territories'][$to];

        for ($i = 0; $i < min($armies, 3); $i++) {
            $attackRoll = random_int(1, 6);
            $defendRoll = random_int(1, 6);

            if ($attackRoll > $defendRoll) {
                $defenderLosses++;
            } else {
                $attackerLosses++;
            }
        }

        $state['player_territories'][$from] -= $attackerLosses;
        $state['enemy_territories'][$to] -= $defenderLosses;

        $conquered = false;
        if ($state['enemy_territories'][$to] <= 0) {
            // Territory conquered
            unset($state['enemy_territories'][$to]);
            $movingArmies = min($armies - $attackerLosses, $state['player_territories'][$from] - 1);
            $state['player_territories'][$to] = max(1, $movingArmies);
            $state['player_territories'][$from] -= $movingArmies;
            $conquered = true;
        }

        $this->updateState($user, $state);

        return [
            'message' => $conquered ? "Conquered {$to}!" : "Battle at {$to}",
            'attacker_losses' => $attackerLosses,
            'defender_losses' => $defenderLosses,
            'conquered' => $conquered,
            'your_territories' => $state['player_territories'],
            'enemy_territories' => array_keys($state['enemy_territories']),
            'game_over' => false,
        ];
    }

    private function fortify(User $user, array $state, array $data): array
    {
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        $armies = (int)($data['armies'] ?? 1);

        if (!isset($state['player_territories'][$from]) || !isset($state['player_territories'][$to])) {
            return ['error' => 'You must control both territories', 'game_over' => false];
        }

        if ($armies >= $state['player_territories'][$from]) {
            return ['error' => 'Must leave at least 1 army behind', 'game_over' => false];
        }

        $state['player_territories'][$from] -= $armies;
        $state['player_territories'][$to] += $armies;

        $this->updateState($user, $state);

        return [
            'message' => "Moved {$armies} armies from {$from} to {$to}",
            'your_territories' => $state['player_territories'],
            'game_over' => false,
        ];
    }

    private function endTurn(User $user, array $state): array
    {
        // Enemy turn - random attack
        if (!empty($state['enemy_territories']) && !empty($state['player_territories'])) {
            $enemyTerritory = array_rand($state['enemy_territories']);
            $playerTerritory = array_rand($state['player_territories']);

            // 30% chance enemy attacks
            if (random_int(1, 10) <= 3 && $state['enemy_territories'][$enemyTerritory] > 3) {
                $attackResult = $this->enemyAttack($state, $enemyTerritory, $playerTerritory);
                $state = $attackResult['state'];
            }
        }

        // Calculate reinforcements
        $territoryCount = count($state['player_territories']);
        $reinforcements = max(3, (int)($territoryCount / 2));

        $state['reserves'] += $reinforcements;
        $state['turn']++;

        // Enemy gets reinforcements too
        foreach ($state['enemy_territories'] as $t => $armies) {
            $state['enemy_territories'][$t] += random_int(1, 2);
        }

        $this->updateState($user, $state);

        return [
            'message' => "Turn {$state['turn']}. Received {$reinforcements} reinforcements.",
            'turn' => $state['turn'],
            'reserves' => $state['reserves'],
            'your_territories' => $state['player_territories'],
            'enemy_territories' => $state['enemy_territories'],
            'game_over' => false,
        ];
    }

    private function enemyAttack(array $state, string $from, string $to): array
    {
        $attackingArmies = $state['enemy_territories'][$from] - 1;
        $defendingArmies = $state['player_territories'][$to];

        for ($i = 0; $i < min($attackingArmies, 3); $i++) {
            if (random_int(1, 6) > random_int(1, 6)) {
                $defendingArmies--;
            } else {
                $attackingArmies--;
            }
        }

        $state['enemy_territories'][$from] = $attackingArmies + 1;

        if ($defendingArmies <= 0) {
            unset($state['player_territories'][$to]);
            $state['enemy_territories'][$to] = max(1, $attackingArmies);
        } else {
            $state['player_territories'][$to] = $defendingArmies;
        }

        return ['state' => $state];
    }

    private function status(array $state): array
    {
        return [
            'turn' => $state['turn'],
            'turns_remaining' => $state['max_turns'] - $state['turn'],
            'reserves' => $state['reserves'],
            'your_territories' => $state['player_territories'],
            'your_territory_count' => count($state['player_territories']),
            'enemy_territories' => $state['enemy_territories'],
            'enemy_territory_count' => count($state['enemy_territories']),
            'game_over' => false,
        ];
    }
}
