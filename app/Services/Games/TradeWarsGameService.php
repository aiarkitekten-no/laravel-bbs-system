<?php

namespace App\Services\Games;

use App\Models\User;

/**
 * Trade Wars - Simplified space trading game
 */
class TradeWarsGameService extends BaseGameService
{
    private array $ports = [
        'Terra' => ['fuel' => 100, 'ore' => 50, 'goods' => 200],
        'Alpha Centauri' => ['fuel' => 150, 'ore' => 80, 'goods' => 120],
        'Betelgeuse' => ['fuel' => 80, 'ore' => 200, 'goods' => 90],
        'Rigel' => ['fuel' => 200, 'ore' => 60, 'goods' => 150],
        'Vega' => ['fuel' => 90, 'ore' => 150, 'goods' => 180],
    ];

    public function start(User $user): array
    {
        $initialState = [
            'credits' => 1000,
            'fuel' => 100,
            'cargo' => ['ore' => 0, 'goods' => 0],
            'cargo_max' => 50,
            'location' => 'Terra',
            'turns' => 0,
            'max_turns' => 50,
            'ship_level' => 1,
        ];

        $this->updateState($user, $initialState);

        return [
            'message' => 'Welcome to Trade Wars! You start at Terra with 1000 credits.',
            'state' => $initialState,
            'ports' => array_keys($this->ports),
            'commands' => ['buy', 'sell', 'travel', 'upgrade', 'status'],
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $state = $this->getPlayerState($user)->state;

        if ($state['turns'] >= $state['max_turns']) {
            return [
                'game_over' => true,
                'message' => 'Game over! You ran out of turns.',
                'score' => $state['credits'],
                'final_credits' => $state['credits'],
            ];
        }

        switch ($action) {
            case 'buy':
                return $this->buy($user, $state, $data);
            case 'sell':
                return $this->sell($user, $state, $data);
            case 'travel':
                return $this->travel($user, $state, $data);
            case 'upgrade':
                return $this->upgrade($user, $state);
            case 'status':
                return $this->status($state);
            default:
                return ['error' => 'Unknown action', 'game_over' => false];
        }
    }

    private function buy(User $user, array $state, array $data): array
    {
        $item = $data['item'] ?? null;
        $quantity = (int)($data['quantity'] ?? 1);

        if (!in_array($item, ['ore', 'goods'])) {
            return ['error' => 'Invalid item', 'game_over' => false];
        }

        $port = $this->ports[$state['location']];
        $price = $port[$item];
        $cost = $price * $quantity;

        $totalCargo = $state['cargo']['ore'] + $state['cargo']['goods'] + $quantity;
        if ($totalCargo > $state['cargo_max']) {
            return ['error' => 'Not enough cargo space', 'game_over' => false];
        }

        if ($cost > $state['credits']) {
            return ['error' => 'Not enough credits', 'game_over' => false];
        }

        $state['credits'] -= $cost;
        $state['cargo'][$item] += $quantity;
        $state['turns']++;

        $this->updateState($user, $state);

        return [
            'message' => "Bought {$quantity} {$item} for {$cost} credits",
            'credits' => $state['credits'],
            'cargo' => $state['cargo'],
            'turns_remaining' => $state['max_turns'] - $state['turns'],
            'game_over' => false,
        ];
    }

    private function sell(User $user, array $state, array $data): array
    {
        $item = $data['item'] ?? null;
        $quantity = (int)($data['quantity'] ?? 1);

        if (!in_array($item, ['ore', 'goods'])) {
            return ['error' => 'Invalid item', 'game_over' => false];
        }

        if ($state['cargo'][$item] < $quantity) {
            return ['error' => 'Not enough cargo', 'game_over' => false];
        }

        $port = $this->ports[$state['location']];
        $price = (int)($port[$item] * 1.2); // 20% markup for selling
        $revenue = $price * $quantity;

        $state['credits'] += $revenue;
        $state['cargo'][$item] -= $quantity;
        $state['turns']++;

        $this->updateState($user, $state);

        return [
            'message' => "Sold {$quantity} {$item} for {$revenue} credits",
            'credits' => $state['credits'],
            'cargo' => $state['cargo'],
            'turns_remaining' => $state['max_turns'] - $state['turns'],
            'game_over' => false,
        ];
    }

    private function travel(User $user, array $state, array $data): array
    {
        $destination = $data['destination'] ?? null;

        if (!isset($this->ports[$destination])) {
            return ['error' => 'Invalid destination', 'game_over' => false];
        }

        if ($destination === $state['location']) {
            return ['error' => 'Already at this port', 'game_over' => false];
        }

        $fuelCost = 10;
        if ($state['fuel'] < $fuelCost) {
            return ['error' => 'Not enough fuel', 'game_over' => false];
        }

        $state['fuel'] -= $fuelCost;
        $state['location'] = $destination;
        $state['turns']++;

        // Random event (10% chance of pirate attack)
        $event = null;
        if (random_int(1, 10) === 1) {
            $stolen = min($state['credits'], random_int(50, 200));
            $state['credits'] -= $stolen;
            $event = "Pirates attacked! Lost {$stolen} credits.";
        }

        $this->updateState($user, $state);

        $port = $this->ports[$destination];

        return [
            'message' => "Traveled to {$destination}",
            'event' => $event,
            'location' => $destination,
            'port_prices' => $port,
            'fuel' => $state['fuel'],
            'credits' => $state['credits'],
            'turns_remaining' => $state['max_turns'] - $state['turns'],
            'game_over' => false,
        ];
    }

    private function upgrade(User $user, array $state): array
    {
        $upgradeCost = $state['ship_level'] * 500;

        if ($state['credits'] < $upgradeCost) {
            return ['error' => "Upgrade costs {$upgradeCost} credits", 'game_over' => false];
        }

        $state['credits'] -= $upgradeCost;
        $state['ship_level']++;
        $state['cargo_max'] += 25;
        $state['turns']++;

        $this->updateState($user, $state);

        return [
            'message' => "Ship upgraded to level {$state['ship_level']}!",
            'ship_level' => $state['ship_level'],
            'cargo_max' => $state['cargo_max'],
            'credits' => $state['credits'],
            'game_over' => false,
        ];
    }

    private function status(array $state): array
    {
        return [
            'location' => $state['location'],
            'credits' => $state['credits'],
            'fuel' => $state['fuel'],
            'cargo' => $state['cargo'],
            'cargo_max' => $state['cargo_max'],
            'ship_level' => $state['ship_level'],
            'turns_remaining' => $state['max_turns'] - $state['turns'],
            'port_prices' => $this->ports[$state['location']],
            'game_over' => false,
        ];
    }
}
