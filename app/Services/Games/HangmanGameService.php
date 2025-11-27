<?php

namespace App\Services\Games;

use App\Models\User;

class HangmanGameService extends BaseGameService
{
    private array $words = [
        'COMPUTER', 'MODEM', 'BULLETIN', 'SYSTEM', 'TERMINAL',
        'DOWNLOAD', 'UPLOAD', 'PROTOCOL', 'NETWORK', 'BINARY',
        'SYSOP', 'ANSI', 'ASCII', 'FIDONET', 'TELNET',
        'ZMODEM', 'XMODEM', 'KERMIT', 'PACKET', 'BAUD',
    ];

    public function start(User $user): array
    {
        $word = $this->words[array_rand($this->words)];

        $this->updateState($user, [
            'word' => $word,
            'guessed' => [],
            'wrong' => 0,
            'max_wrong' => 6,
            'start_time' => time(),
        ]);

        return [
            'word_length' => strlen($word),
            'display' => str_repeat('_ ', strlen($word)),
            'guessed' => [],
            'wrong' => 0,
            'max_wrong' => 6,
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $state = $this->getPlayerState($user)->state;

        if ($action !== 'guess' || !isset($data['letter'])) {
            return ['error' => 'Invalid action'];
        }

        $letter = strtoupper(substr($data['letter'], 0, 1));
        $word = $state['word'];
        $guessed = $state['guessed'];
        $wrong = $state['wrong'];

        // Already guessed this letter
        if (in_array($letter, $guessed)) {
            return [
                'error' => 'Already guessed',
                'display' => $this->getDisplay($word, $guessed),
                'guessed' => $guessed,
                'wrong' => $wrong,
            ];
        }

        $guessed[] = $letter;

        // Check if letter is in word
        if (strpos($word, $letter) === false) {
            $wrong++;
        }

        $display = $this->getDisplay($word, $guessed);
        $won = strpos($display, '_') === false;
        $lost = $wrong >= $state['max_wrong'];
        $gameOver = $won || $lost;

        $this->updateState($user, [
            'guessed' => $guessed,
            'wrong' => $wrong,
        ]);

        $result = [
            'letter' => $letter,
            'in_word' => strpos($word, $letter) !== false,
            'display' => $display,
            'guessed' => $guessed,
            'wrong' => $wrong,
            'max_wrong' => $state['max_wrong'],
            'game_over' => $gameOver,
        ];

        if ($gameOver) {
            $timePlayed = time() - $state['start_time'];
            $result['won'] = $won;
            $result['word'] = $word;
            $result['score'] = $won ? (strlen($word) * 10) + ((6 - $wrong) * 20) : 0;
            $result['time_played'] = $timePlayed;
        }

        return $result;
    }

    private function getDisplay(string $word, array $guessed): string
    {
        $display = '';
        for ($i = 0; $i < strlen($word); $i++) {
            $char = $word[$i];
            $display .= in_array($char, $guessed) ? $char : '_';
            $display .= ' ';
        }
        return trim($display);
    }
}
