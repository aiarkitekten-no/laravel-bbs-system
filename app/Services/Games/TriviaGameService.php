<?php

namespace App\Services\Games;

use App\Models\User;

class TriviaGameService extends BaseGameService
{
    private array $questions = [
        ['q' => 'What year was the BBS era at its peak?', 'a' => ['1994', '1985', '2000', '1970'], 'correct' => 0],
        ['q' => 'What does BBS stand for?', 'a' => ['Bulletin Board System', 'Binary Buffer Service', 'Basic Broadcast System', 'Byte Based Storage'], 'correct' => 0],
        ['q' => 'Which protocol was commonly used for file transfers on BBSes?', 'a' => ['ZMODEM', 'HTTP', 'FTP', 'SMTP'], 'correct' => 0],
        ['q' => 'What was the maximum speed of a 56K modem?', 'a' => ['56 Kbps', '56 MBps', '5.6 Kbps', '560 Kbps'], 'correct' => 0],
        ['q' => 'Which game was a popular door game on BBSes?', 'a' => ['Legend of the Red Dragon', 'World of Warcraft', 'Minecraft', 'Fortnite'], 'correct' => 0],
        ['q' => 'What sound did a modem make when connecting?', 'a' => ['Handshake tones', 'Music', 'Silence', 'Beeping'], 'correct' => 0],
        ['q' => 'What is ANSI art?', 'a' => ['Text-based graphics', 'Photo editing', 'Video format', 'Audio codec'], 'correct' => 0],
        ['q' => 'What was FidoNet?', 'a' => ['A BBS network', 'A dog website', 'An email service', 'A game'], 'correct' => 0],
        ['q' => 'What is a SysOp?', 'a' => ['System Operator', 'System Option', 'Systematic Operation', 'Sync Operator'], 'correct' => 0],
        ['q' => 'Which year did the World Wide Web become public?', 'a' => ['1991', '1985', '1999', '1980'], 'correct' => 0],
    ];

    public function start(User $user): array
    {
        // Shuffle and pick 5 questions
        $shuffled = collect($this->questions)->shuffle()->take(5)->values()->all();

        $this->updateState($user, [
            'questions' => $shuffled,
            'current' => 0,
            'score' => 0,
            'answers' => [],
            'start_time' => time(),
        ]);

        return [
            'total_questions' => count($shuffled),
            'current_question' => 1,
            'question' => $shuffled[0]['q'],
            'answers' => $shuffled[0]['a'],
        ];
    }

    public function play(User $user, string $action, array $data): array
    {
        $state = $this->getPlayerState($user)->state;

        if ($action !== 'answer' || !isset($data['answer'])) {
            return ['error' => 'Invalid action'];
        }

        $currentIndex = $state['current'];
        $questions = $state['questions'];
        $answerIndex = (int) $data['answer'];

        $isCorrect = $answerIndex === $questions[$currentIndex]['correct'];
        $newScore = $state['score'] + ($isCorrect ? 100 : 0);
        $answers = $state['answers'];
        $answers[] = [
            'question' => $currentIndex,
            'answer' => $answerIndex,
            'correct' => $isCorrect,
        ];

        $nextIndex = $currentIndex + 1;
        $gameOver = $nextIndex >= count($questions);

        $this->updateState($user, [
            'current' => $nextIndex,
            'score' => $newScore,
            'answers' => $answers,
        ]);

        $result = [
            'correct' => $isCorrect,
            'correct_answer' => $questions[$currentIndex]['correct'],
            'score' => $newScore,
            'game_over' => $gameOver,
        ];

        if ($gameOver) {
            $timePlayed = time() - $state['start_time'];
            $result['final_score'] = $newScore;
            $result['time_played'] = $timePlayed;
            $result['score'] = $newScore;
            $result['correct_count'] = count(array_filter($answers, fn($a) => $a['correct']));
            $result['total_questions'] = count($questions);
        } else {
            $result['current_question'] = $nextIndex + 1;
            $result['question'] = $questions[$nextIndex]['q'];
            $result['answers'] = $questions[$nextIndex]['a'];
        }

        return $result;
    }
}
