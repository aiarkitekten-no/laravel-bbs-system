<?php

namespace App\Services\Games;

use App\Models\User;

class NumberGuessGameService extends BaseGameService
{
    public function start(User $user): array
    {
        $min = $this->config('min', 1);
        $max = $this->config('max', 100);
        $maxGuesses = $this->config('max_guesses', 7);
        $target = random_int($min, $max);

        $this->updateState($user, [
            'target' => $target,
            'min' => $min,
            'max' => $max,
            'guesses' => [],
            'max_guesses' => $maxGuesses,
            'start_time' => time(),
        ]);

        return [
            'min' => $min,
            'max' => $max,
            'max_guesses' => $maxGuesses,
            'message' => "I'm thinking of a number between {$min} and {$max}. You have {$maxGuesses} guesses.",
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $state = $this->getPlayerState($user)->state;

        if ($action !== 'guess' || !isset($data['number'])) {
            return ['error' => 'Invalid action'];
        }

        $guess = (int) $data['number'];
        $target = $state['target'];
        $guesses = $state['guesses'];
        $guesses[] = $guess;

        $this->updateState($user, ['guesses' => $guesses]);

        $won = $guess === $target;
        $guessCount = count($guesses);
        $lost = $guessCount >= $state['max_guesses'] && !$won;
        $gameOver = $won || $lost;

        $hint = '';
        if (!$won) {
            $hint = $guess < $target ? 'higher' : 'lower';
        }

        $result = [
            'guess' => $guess,
            'hint' => $hint,
            'guesses_made' => $guessCount,
            'guesses_remaining' => $state['max_guesses'] - $guessCount,
            'game_over' => $gameOver,
        ];

        if ($gameOver) {
            $timePlayed = time() - $state['start_time'];
            $result['won'] = $won;
            $result['target'] = $target;
            $result['score'] = $won ? ($state['max_guesses'] - $guessCount + 1) * 100 : 0;
            $result['time_played'] = $timePlayed;
            $result['all_guesses'] = $guesses;
        }

        return $result;
    }
}
