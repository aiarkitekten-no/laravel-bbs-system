<?php

namespace App\Services\Games;

use App\Models\User;

/**
 * Barren Realms Elite - Simplified kingdom management game
 */
class BreGameService extends BaseGameService
{
    public function start(User $user): array
    {
        $initialState = [
            'kingdom_name' => $user->handle . "'s Realm",
            'turn' => 1,
            'max_turns' => 100,
            'land' => 100,
            'population' => 500,
            'food' => 2000,
            'gold' => 1000,
            'soldiers' => 50,
            'tax_rate' => 10,
            'happiness' => 70,
        ];

        $this->updateState($user, $initialState);

        return [
            'message' => "Welcome, ruler of {$initialState['kingdom_name']}! Manage your kingdom wisely.",
            'state' => $initialState,
            'commands' => ['build', 'recruit', 'set_tax', 'attack', 'explore', 'status', 'end_turn'],
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $state = $this->getPlayerState($user)->state;

        if ($state['turn'] > $state['max_turns']) {
            return $this->endGame($state);
        }

        if ($state['population'] <= 0) {
            return [
                'game_over' => true,
                'message' => 'Your kingdom has fallen! All population is gone.',
                'score' => $this->calculateScore($state),
            ];
        }

        switch ($action) {
            case 'build':
                return $this->build($user, $state, $data);
            case 'recruit':
                return $this->recruit($user, $state, $data);
            case 'set_tax':
                return $this->setTax($user, $state, $data);
            case 'explore':
                return $this->explore($user, $state);
            case 'attack':
                return $this->attack($user, $state);
            case 'end_turn':
                return $this->endTurn($user, $state);
            case 'status':
                return $this->status($state);
            default:
                return ['error' => 'Unknown action', 'game_over' => false];
        }
    }

    private function build(User $user, array $state, array $data): array
    {
        $farms = (int)($data['farms'] ?? 0);
        $cost = $farms * 100;

        if ($cost > $state['gold']) {
            return ['error' => 'Not enough gold', 'game_over' => false];
        }

        $state['gold'] -= $cost;
        $state['land'] += $farms * 10;

        $this->updateState($user, $state);

        return [
            'message' => "Built {$farms} farms, gained " . ($farms * 10) . " land.",
            'land' => $state['land'],
            'gold' => $state['gold'],
            'game_over' => false,
        ];
    }

    private function recruit(User $user, array $state, array $data): array
    {
        $soldiers = (int)($data['soldiers'] ?? 10);
        $cost = $soldiers * 20;

        if ($cost > $state['gold']) {
            return ['error' => 'Not enough gold', 'game_over' => false];
        }

        if ($soldiers > $state['population'] / 10) {
            return ['error' => 'Not enough population to recruit', 'game_over' => false];
        }

        $state['gold'] -= $cost;
        $state['soldiers'] += $soldiers;
        $state['population'] -= $soldiers;

        $this->updateState($user, $state);

        return [
            'message' => "Recruited {$soldiers} soldiers.",
            'soldiers' => $state['soldiers'],
            'population' => $state['population'],
            'gold' => $state['gold'],
            'game_over' => false,
        ];
    }

    private function setTax(User $user, array $state, array $data): array
    {
        $rate = max(0, min(50, (int)($data['rate'] ?? 10)));

        $state['tax_rate'] = $rate;
        $state['happiness'] = max(10, 100 - $rate * 1.5);

        $this->updateState($user, $state);

        return [
            'message' => "Tax rate set to {$rate}%.",
            'tax_rate' => $rate,
            'happiness' => $state['happiness'],
            'game_over' => false,
        ];
    }

    private function explore(User $user, array $state): array
    {
        $cost = 200;
        if ($state['gold'] < $cost) {
            return ['error' => "Exploration costs {$cost} gold", 'game_over' => false];
        }

        $state['gold'] -= $cost;
        $landFound = random_int(10, 50);
        $state['land'] += $landFound;

        // Random event
        $event = null;
        $roll = random_int(1, 10);
        if ($roll <= 2) {
            $goldFound = random_int(100, 500);
            $state['gold'] += $goldFound;
            $event = "Found a treasure chest with {$goldFound} gold!";
        } elseif ($roll == 10) {
            $loss = random_int(10, 30);
            $state['soldiers'] = max(0, $state['soldiers'] - $loss);
            $event = "Ambushed! Lost {$loss} soldiers.";
        }

        $this->updateState($user, $state);

        return [
            'message' => "Explored and found {$landFound} new land.",
            'event' => $event,
            'land' => $state['land'],
            'gold' => $state['gold'],
            'soldiers' => $state['soldiers'],
            'game_over' => false,
        ];
    }

    private function attack(User $user, array $state): array
    {
        if ($state['soldiers'] < 20) {
            return ['error' => 'Need at least 20 soldiers to attack', 'game_over' => false];
        }

        $enemyStrength = random_int(30, 100);
        $ourStrength = $state['soldiers'] + random_int(-10, 10);

        if ($ourStrength > $enemyStrength) {
            $landWon = random_int(20, 50);
            $goldWon = random_int(100, 500);
            $soldiersLost = random_int(5, 15);

            $state['land'] += $landWon;
            $state['gold'] += $goldWon;
            $state['soldiers'] -= $soldiersLost;

            $this->updateState($user, $state);

            return [
                'message' => "Victory! Won {$landWon} land and {$goldWon} gold. Lost {$soldiersLost} soldiers.",
                'land' => $state['land'],
                'gold' => $state['gold'],
                'soldiers' => $state['soldiers'],
                'game_over' => false,
            ];
        } else {
            $soldiersLost = random_int(15, 30);
            $state['soldiers'] = max(0, $state['soldiers'] - $soldiersLost);

            $this->updateState($user, $state);

            return [
                'message' => "Defeat! Lost {$soldiersLost} soldiers.",
                'soldiers' => $state['soldiers'],
                'game_over' => false,
            ];
        }
    }

    private function endTurn(User $user, array $state): array
    {
        // Collect taxes
        $taxIncome = (int)($state['population'] * $state['tax_rate'] / 100 * 2);
        $state['gold'] += $taxIncome;

        // Food consumption
        $foodConsumed = (int)($state['population'] / 10 + $state['soldiers'] / 5);
        $state['food'] -= $foodConsumed;

        // Food production from land
        $foodProduced = (int)($state['land'] / 5);
        $state['food'] += $foodProduced;

        // Population growth based on happiness and food
        if ($state['food'] > 0 && $state['happiness'] > 30) {
            $growth = (int)($state['population'] * ($state['happiness'] / 1000));
            $state['population'] += $growth;
        } elseif ($state['food'] <= 0) {
            $deaths = (int)($state['population'] * 0.1);
            $state['population'] -= $deaths;
            $state['happiness'] = max(10, $state['happiness'] - 10);
        }

        $state['turn']++;

        $this->updateState($user, $state);

        if ($state['turn'] > $state['max_turns']) {
            return $this->endGame($state);
        }

        return [
            'message' => "Turn {$state['turn']} begins.",
            'tax_income' => $taxIncome,
            'food_produced' => $foodProduced,
            'food_consumed' => $foodConsumed,
            'state' => $state,
            'game_over' => false,
        ];
    }

    private function status(array $state): array
    {
        return [
            'kingdom' => $state['kingdom_name'],
            'turn' => $state['turn'],
            'turns_remaining' => $state['max_turns'] - $state['turn'],
            'land' => $state['land'],
            'population' => $state['population'],
            'food' => $state['food'],
            'gold' => $state['gold'],
            'soldiers' => $state['soldiers'],
            'tax_rate' => $state['tax_rate'],
            'happiness' => $state['happiness'],
            'game_over' => false,
        ];
    }

    private function endGame(array $state): array
    {
        $score = $this->calculateScore($state);

        return [
            'game_over' => true,
            'message' => "Your reign has ended after {$state['max_turns']} turns!",
            'final_state' => $state,
            'score' => $score,
        ];
    }

    private function calculateScore(array $state): int
    {
        return $state['land'] + 
               ($state['population'] / 10) + 
               ($state['gold'] / 100) + 
               ($state['soldiers'] * 5);
    }
}
