<?php

namespace App\Services\Games;

use App\Models\User;

/**
 * Legend of the Red Dragon - Simplified RPG door game
 */
class LordGameService extends BaseGameService
{
    private array $monsters = [
        ['name' => 'Rat', 'hp' => 5, 'attack' => 1, 'exp' => 5, 'gold' => 2],
        ['name' => 'Slime', 'hp' => 8, 'attack' => 2, 'exp' => 8, 'gold' => 5],
        ['name' => 'Goblin', 'hp' => 15, 'attack' => 4, 'exp' => 15, 'gold' => 10],
        ['name' => 'Orc', 'hp' => 25, 'attack' => 6, 'exp' => 25, 'gold' => 20],
        ['name' => 'Troll', 'hp' => 40, 'attack' => 10, 'exp' => 50, 'gold' => 40],
        ['name' => 'Dark Knight', 'hp' => 60, 'attack' => 15, 'exp' => 100, 'gold' => 80],
        ['name' => 'Dragon', 'hp' => 100, 'attack' => 25, 'exp' => 250, 'gold' => 200],
        ['name' => 'Red Dragon', 'hp' => 500, 'attack' => 50, 'exp' => 1000, 'gold' => 1000],
    ];

    private array $weapons = [
        ['name' => 'Stick', 'attack' => 1, 'cost' => 0],
        ['name' => 'Dagger', 'attack' => 3, 'cost' => 50],
        ['name' => 'Short Sword', 'attack' => 6, 'cost' => 150],
        ['name' => 'Long Sword', 'attack' => 10, 'cost' => 400],
        ['name' => 'Battle Axe', 'attack' => 15, 'cost' => 1000],
        ['name' => 'Magic Sword', 'attack' => 25, 'cost' => 3000],
    ];

    public function start(User $user): array
    {
        $initialState = [
            'name' => $user->handle,
            'level' => 1,
            'hp' => 20,
            'max_hp' => 20,
            'attack' => 1,
            'defense' => 0,
            'exp' => 0,
            'exp_next' => 100,
            'gold' => 50,
            'weapon' => 'Stick',
            'weapon_attack' => 1,
            'forest_fights' => 10,
            'alive' => true,
            'red_dragon_killed' => false,
        ];

        $this->updateState($user, $initialState);

        return [
            'message' => "Welcome to the realm, {$user->handle}! The Red Dragon terrorizes the land. Will you become strong enough to defeat it?",
            'state' => $initialState,
            'locations' => ['forest', 'town', 'inn', 'weapon_shop'],
            'commands' => ['fight', 'heal', 'buy_weapon', 'status'],
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $state = $this->getPlayerState($user)->state;

        if (!$state['alive']) {
            return [
                'game_over' => true,
                'message' => 'You are dead! Game over.',
                'score' => $state['exp'],
                'level' => $state['level'],
            ];
        }

        if ($state['red_dragon_killed']) {
            return [
                'game_over' => true,
                'won' => true,
                'message' => 'You have slain the Red Dragon! You are a legend!',
                'score' => $state['exp'] + 5000,
                'level' => $state['level'],
            ];
        }

        switch ($action) {
            case 'fight':
                return $this->fight($user, $state);
            case 'heal':
                return $this->heal($user, $state);
            case 'buy_weapon':
                return $this->buyWeapon($user, $state, $data);
            case 'status':
                return $this->status($state);
            case 'challenge_dragon':
                return $this->challengeDragon($user, $state);
            default:
                return ['error' => 'Unknown action', 'game_over' => false];
        }
    }

    private function fight(User $user, array $state): array
    {
        if ($state['forest_fights'] <= 0) {
            return ['error' => 'No forest fights remaining today. Rest at the inn.', 'game_over' => false];
        }

        // Choose monster based on level
        $maxMonsterIndex = min($state['level'], count($this->monsters) - 2); // -2 to exclude Red Dragon
        $monsterIndex = random_int(0, $maxMonsterIndex);
        $monster = $this->monsters[$monsterIndex];

        $monsterHp = $monster['hp'];
        $log = [];
        $log[] = "You encounter a {$monster['name']}!";

        // Combat loop
        while ($monsterHp > 0 && $state['hp'] > 0) {
            // Player attacks
            $playerDamage = $state['attack'] + $state['weapon_attack'] + random_int(1, 5);
            $monsterHp -= $playerDamage;
            $log[] = "You deal {$playerDamage} damage to the {$monster['name']}.";

            if ($monsterHp <= 0) {
                break;
            }

            // Monster attacks
            $monsterDamage = max(1, $monster['attack'] - $state['defense'] + random_int(-2, 2));
            $state['hp'] -= $monsterDamage;
            $log[] = "The {$monster['name']} deals {$monsterDamage} damage to you.";
        }

        $state['forest_fights']--;

        if ($state['hp'] <= 0) {
            $state['hp'] = 0;
            $state['alive'] = false;
            $this->updateState($user, $state);

            return [
                'message' => "The {$monster['name']} has killed you!",
                'combat_log' => $log,
                'game_over' => true,
                'score' => $state['exp'],
                'level' => $state['level'],
            ];
        }

        // Victory!
        $state['exp'] += $monster['exp'];
        $state['gold'] += $monster['gold'];
        $log[] = "Victory! Gained {$monster['exp']} exp and {$monster['gold']} gold.";

        // Level up check
        $leveledUp = false;
        if ($state['exp'] >= $state['exp_next']) {
            $state['level']++;
            $state['max_hp'] += 10;
            $state['hp'] = $state['max_hp'];
            $state['attack'] += 2;
            $state['defense'] += 1;
            $state['exp_next'] = $state['level'] * 100;
            $leveledUp = true;
            $log[] = "LEVEL UP! You are now level {$state['level']}!";
        }

        $this->updateState($user, $state);

        return [
            'message' => "Defeated the {$monster['name']}!",
            'combat_log' => $log,
            'leveled_up' => $leveledUp,
            'hp' => $state['hp'],
            'max_hp' => $state['max_hp'],
            'exp' => $state['exp'],
            'gold' => $state['gold'],
            'level' => $state['level'],
            'forest_fights' => $state['forest_fights'],
            'can_challenge_dragon' => $state['level'] >= 10,
            'game_over' => false,
        ];
    }

    private function heal(User $user, array $state): array
    {
        $cost = 10 * $state['level'];
        $healAmount = $state['max_hp'] - $state['hp'];

        if ($healAmount <= 0) {
            return ['message' => 'You are already at full health!', 'game_over' => false];
        }

        if ($state['gold'] < $cost) {
            return ['error' => "Healing costs {$cost} gold.", 'game_over' => false];
        }

        $state['gold'] -= $cost;
        $state['hp'] = $state['max_hp'];

        $this->updateState($user, $state);

        return [
            'message' => "Healed to full health for {$cost} gold.",
            'hp' => $state['hp'],
            'gold' => $state['gold'],
            'game_over' => false,
        ];
    }

    private function buyWeapon(User $user, array $state, array $data): array
    {
        $weaponName = $data['weapon'] ?? null;
        $weapon = collect($this->weapons)->firstWhere('name', $weaponName);

        if (!$weapon) {
            return [
                'error' => 'Invalid weapon',
                'available_weapons' => $this->weapons,
                'game_over' => false,
            ];
        }

        if ($state['gold'] < $weapon['cost']) {
            return ['error' => "Not enough gold. Need {$weapon['cost']}.", 'game_over' => false];
        }

        $state['gold'] -= $weapon['cost'];
        $state['weapon'] = $weapon['name'];
        $state['weapon_attack'] = $weapon['attack'];

        $this->updateState($user, $state);

        return [
            'message' => "Purchased {$weapon['name']}!",
            'weapon' => $weapon['name'],
            'weapon_attack' => $weapon['attack'],
            'gold' => $state['gold'],
            'game_over' => false,
        ];
    }

    private function challengeDragon(User $user, array $state): array
    {
        if ($state['level'] < 10) {
            return ['error' => 'You must be level 10 to challenge the Red Dragon!', 'game_over' => false];
        }

        $dragon = $this->monsters[count($this->monsters) - 1]; // Red Dragon
        $dragonHp = $dragon['hp'];
        $log = [];
        $log[] = "You challenge the mighty RED DRAGON!";

        while ($dragonHp > 0 && $state['hp'] > 0) {
            $playerDamage = $state['attack'] + $state['weapon_attack'] + random_int(5, 20);
            $dragonHp -= $playerDamage;
            $log[] = "You deal {$playerDamage} damage!";

            if ($dragonHp <= 0) break;

            $dragonDamage = max(10, $dragon['attack'] - $state['defense'] + random_int(-10, 10));
            $state['hp'] -= $dragonDamage;
            $log[] = "The Dragon breathes fire for {$dragonDamage} damage!";
        }

        if ($state['hp'] <= 0) {
            $state['hp'] = 0;
            $state['alive'] = false;
            $this->updateState($user, $state);

            return [
                'message' => 'The Red Dragon has destroyed you!',
                'combat_log' => $log,
                'game_over' => true,
                'score' => $state['exp'],
            ];
        }

        $state['red_dragon_killed'] = true;
        $state['exp'] += $dragon['exp'];
        $state['gold'] += $dragon['gold'];

        $this->updateState($user, $state);

        return [
            'message' => 'YOU HAVE SLAIN THE RED DRAGON! YOU ARE A LEGEND!',
            'combat_log' => $log,
            'game_over' => true,
            'won' => true,
            'score' => $state['exp'] + 5000,
            'level' => $state['level'],
        ];
    }

    private function status(array $state): array
    {
        return [
            'name' => $state['name'],
            'level' => $state['level'],
            'hp' => $state['hp'],
            'max_hp' => $state['max_hp'],
            'attack' => $state['attack'],
            'defense' => $state['defense'],
            'exp' => $state['exp'],
            'exp_next' => $state['exp_next'],
            'gold' => $state['gold'],
            'weapon' => $state['weapon'],
            'forest_fights' => $state['forest_fights'],
            'can_challenge_dragon' => $state['level'] >= 10,
            'game_over' => false,
        ];
    }
}
