<?php

namespace App\Services\Games;

use App\Models\User;

/**
 * Daily Lottery - Once per day lottery game
 */
class LotteryGameService extends BaseGameService
{
    public function start(User $user): array
    {
        $playerState = $this->getPlayerState($user);

        if ($playerState->hasPlayedToday()) {
            return [
                'message' => 'You have already played today\'s lottery!',
                'last_numbers' => $playerState->getState('last_numbers', []),
                'last_matched' => $playerState->getState('last_matched', 0),
                'last_prize' => $playerState->getState('last_prize', 0),
                'next_draw' => now()->addDay()->startOfDay()->toIso8601String(),
                'game_over' => false,
            ];
        }

        return [
            'message' => 'Welcome to the Daily Lottery! Pick 6 numbers from 1-49.',
            'instructions' => 'Choose your lucky numbers or let the system pick for you.',
            'range' => ['min' => 1, 'max' => 49],
            'picks_needed' => 6,
            'game_over' => false,
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $playerState = $this->getPlayerState($user);

        if ($playerState->hasPlayedToday()) {
            return [
                'error' => 'Already played today',
                'next_draw' => now()->addDay()->startOfDay()->toIso8601String(),
                'game_over' => false,
            ];
        }

        if ($action === 'quick_pick') {
            $numbers = $this->generateQuickPick();
        } elseif ($action === 'pick') {
            $numbers = $data['numbers'] ?? [];

            // Validate numbers
            if (count($numbers) !== 6) {
                return ['error' => 'Must pick exactly 6 numbers', 'game_over' => false];
            }

            $numbers = array_map('intval', $numbers);
            $numbers = array_unique($numbers);

            if (count($numbers) !== 6) {
                return ['error' => 'Numbers must be unique', 'game_over' => false];
            }

            foreach ($numbers as $num) {
                if ($num < 1 || $num > 49) {
                    return ['error' => 'Numbers must be between 1 and 49', 'game_over' => false];
                }
            }

            sort($numbers);
        } else {
            return ['error' => 'Invalid action. Use "quick_pick" or "pick"', 'game_over' => false];
        }

        // Generate winning numbers
        $winningNumbers = $this->generateQuickPick();

        // Calculate matches
        $matched = count(array_intersect($numbers, $winningNumbers));

        // Prize table
        $prizes = [
            0 => 0,
            1 => 0,
            2 => 10,
            3 => 100,
            4 => 1000,
            5 => 10000,
            6 => 1000000,
        ];

        $prize = $prizes[$matched];

        // Award prize
        if ($prize > 0) {
            $user->increment('credits', $prize);
        }

        // Save state
        $playerState->update([
            'last_played_date' => today(),
            'turns_today' => 1,
            'state' => [
                'last_numbers' => $numbers,
                'winning_numbers' => $winningNumbers,
                'last_matched' => $matched,
                'last_prize' => $prize,
            ],
        ]);

        return [
            'your_numbers' => $numbers,
            'winning_numbers' => $winningNumbers,
            'matched' => $matched,
            'prize' => $prize,
            'message' => $this->getPrizeMessage($matched, $prize),
            'next_draw' => now()->addDay()->startOfDay()->toIso8601String(),
            'game_over' => true,
            'score' => $matched,
        ];
    }

    private function generateQuickPick(): array
    {
        $numbers = [];
        while (count($numbers) < 6) {
            $num = random_int(1, 49);
            if (!in_array($num, $numbers)) {
                $numbers[] = $num;
            }
        }
        sort($numbers);
        return $numbers;
    }

    private function getPrizeMessage(int $matched, int $prize): string
    {
        $messages = [
            0 => 'No matches. Better luck tomorrow!',
            1 => 'One number matched. So close!',
            2 => "Two numbers! Won {$prize} credits!",
            3 => "Three numbers! Won {$prize} credits!",
            4 => "FOUR numbers! Won {$prize} credits!",
            5 => "FIVE NUMBERS! Won {$prize} credits! AMAZING!",
            6 => "JACKPOT!!! ALL SIX NUMBERS! Won {$prize} credits!!!",
        ];

        return $messages[$matched] ?? 'Unknown result';
    }
}
