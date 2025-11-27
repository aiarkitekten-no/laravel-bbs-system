<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Game;
use App\Models\GamePlayerState;
use App\Models\GameScore;
use App\Models\UserAchievement;
use App\Services\Games\GameServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GameController extends Controller
{
    /**
     * List all available games
     */
    public function index(): JsonResponse
    {
        $games = Game::active()
            ->orderBy('type')
            ->orderBy('name_en')
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'slug' => $g->slug,
                'name' => $g->name,
                'description' => $g->description,
                'type' => $g->type,
                'plays_today' => $g->plays_today,
                'plays_total' => $g->plays_total,
            ]);

        return response()->json([
            'success' => true,
            'games' => $games,
        ]);
    }

    /**
     * Get game details and user's state
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $game = Game::findBySlug($slug);

        if (!$game || !$game->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('games.not_found'),
            ], 404);
        }

        $user = $request->user();
        $playerState = $game->getPlayerState($user);
        $userHighScore = $game->getUserHighScore($user);

        return response()->json([
            'success' => true,
            'game' => [
                'id' => $game->id,
                'slug' => $game->slug,
                'name' => $game->name,
                'description' => $game->description,
                'type' => $game->type,
                'config' => $game->config,
            ],
            'player_state' => $playerState ? [
                'turns_today' => $playerState->turns_today,
                'turns_remaining' => $playerState->getRemainingTurns(),
                'has_played_today' => $playerState->hasPlayedToday(),
                'state' => $playerState->state,
            ] : null,
            'user_high_score' => $userHighScore?->score,
            'high_scores' => $game->getHighScores(10)->map(fn($s) => [
                'user' => $s->user->handle,
                'score' => $s->score,
                'created_at' => $s->created_at,
            ]),
        ]);
    }

    /**
     * Start a new game session
     */
    public function start(Request $request, string $slug): JsonResponse
    {
        $game = Game::findBySlug($slug);

        if (!$game || !$game->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('games.not_found'),
            ], 404);
        }

        $user = $request->user();

        // For door games, check turns
        if ($game->isDoor()) {
            $playerState = $game->getOrCreatePlayerState($user);

            if (!$playerState->useTurn()) {
                return response()->json([
                    'success' => false,
                    'message' => __('games.no_turns_remaining'),
                    'turns_remaining' => 0,
                ], 403);
            }
        }

        // Get game service and initialize
        $gameService = GameServiceFactory::create($game);
        $initialState = $gameService->start($user);

        return response()->json([
            'success' => true,
            'message' => __('games.started'),
            'game_state' => $initialState,
        ]);
    }

    /**
     * Make a move/action in a game
     */
    public function play(Request $request, string $slug): JsonResponse
    {
        $game = Game::findBySlug($slug);

        if (!$game || !$game->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('games.not_found'),
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('games.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $gameService = GameServiceFactory::create($game);

        $result = $gameService->play($user, $request->action, $request->data ?? []);

        // Check if game ended
        if ($result['game_over'] ?? false) {
            // Record score if applicable
            if (isset($result['score'])) {
                GameScore::recordScore(
                    $game,
                    $user,
                    $result['score'],
                    $result['level'] ?? null,
                    $result['time_played'] ?? null,
                    $result['game_data'] ?? null
                );
            }

            // Check for achievements
            $achievements = Achievement::checkAndAwardAll($user);
            if (!empty($achievements)) {
                $result['achievements_earned'] = collect($achievements)->map(fn($a) => [
                    'name' => $a->name,
                    'description' => $a->description,
                    'icon' => $a->icon,
                    'points' => $a->points,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    /**
     * Get high scores for a game
     */
    public function highScores(string $slug): JsonResponse
    {
        $game = Game::findBySlug($slug);

        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => __('games.not_found'),
            ], 404);
        }

        $scores = $game->getHighScores(50);

        return response()->json([
            'success' => true,
            'game' => $game->name,
            'scores' => $scores->map(fn($s, $i) => [
                'rank' => $i + 1,
                'user' => $s->user->handle,
                'score' => $s->score,
                'level' => $s->level_reached,
                'created_at' => $s->created_at,
            ]),
        ]);
    }

    /**
     * Get global high scores across all games
     */
    public function globalHighScores(): JsonResponse
    {
        $scores = GameScore::getGlobalHighScores(50);

        return response()->json([
            'success' => true,
            'scores' => $scores->map(fn($s, $i) => [
                'rank' => $i + 1,
                'user' => $s->user->handle,
                'game' => $s->game->name,
                'score' => $s->score,
                'created_at' => $s->created_at,
            ]),
        ]);
    }

    /**
     * Get today's top scores
     */
    public function todaysScores(): JsonResponse
    {
        $scores = GameScore::getTodaysTopScores(20);

        return response()->json([
            'success' => true,
            'date' => today()->toDateString(),
            'scores' => $scores->map(fn($s, $i) => [
                'rank' => $i + 1,
                'user' => $s->user->handle,
                'game' => $s->game->name,
                'score' => $s->score,
            ]),
        ]);
    }

    /**
     * Get user's game history
     */
    public function myScores(Request $request): JsonResponse
    {
        $user = $request->user();
        $scores = GameScore::getUserScores($user, 100);

        return response()->json([
            'success' => true,
            'scores' => $scores->map(fn($s) => [
                'game' => $s->game->name,
                'score' => $s->score,
                'level' => $s->level_reached,
                'time_played' => $s->time_played,
                'created_at' => $s->created_at,
            ]),
        ]);
    }

    /**
     * List all achievements
     */
    public function achievements(): JsonResponse
    {
        $achievements = Achievement::visible()
            ->orderBy('category')
            ->orderBy('points')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'slug' => $a->slug,
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon,
                'points' => $a->points,
                'category' => $a->category,
            ]);

        return response()->json([
            'success' => true,
            'achievements' => $achievements,
        ]);
    }

    /**
     * Get user's achievements
     */
    public function myAchievements(Request $request): JsonResponse
    {
        $user = $request->user();
        $earned = UserAchievement::getUserAchievements($user);
        $totalPoints = UserAchievement::getTotalPoints($user);

        return response()->json([
            'success' => true,
            'total_points' => $totalPoints,
            'achievements' => $earned->map(fn($ua) => [
                'name' => $ua->achievement->name,
                'description' => $ua->achievement->description,
                'icon' => $ua->achievement->icon,
                'points' => $ua->achievement->points,
                'category' => $ua->achievement->category,
                'earned_at' => $ua->earned_at,
            ]),
        ]);
    }

    /**
     * Play daily lottery
     */
    public function lottery(Request $request): JsonResponse
    {
        $game = Game::findBySlug('lottery');

        if (!$game || !$game->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('games.lottery_unavailable'),
            ], 404);
        }

        $user = $request->user();
        $playerState = $game->getOrCreatePlayerState($user);

        // Check if already played today
        if ($playerState->hasPlayedToday()) {
            return response()->json([
                'success' => false,
                'message' => __('games.lottery_already_played'),
                'next_draw' => now()->addDay()->startOfDay()->toIso8601String(),
            ], 403);
        }

        // Generate lottery result
        $numbers = [];
        for ($i = 0; $i < 6; $i++) {
            do {
                $num = random_int(1, 49);
            } while (in_array($num, $numbers));
            $numbers[] = $num;
        }
        sort($numbers);

        // Calculate winnings based on matched numbers (simple version)
        $winningNumbers = $game->config['winning_numbers'] ?? [7, 14, 21, 28, 35, 42];
        $matched = count(array_intersect($numbers, $winningNumbers));

        $prizes = [
            0 => 0,
            1 => 0,
            2 => 10,
            3 => 100,
            4 => 1000,
            5 => 10000,
            6 => 100000,
        ];

        $prize = $prizes[$matched] ?? 0;

        if ($prize > 0) {
            $user->increment('credits', $prize);
        }

        // Mark as played
        $playerState->update([
            'last_played_date' => today(),
            'turns_today' => 1,
            'state' => ['last_numbers' => $numbers, 'last_matched' => $matched, 'last_prize' => $prize],
        ]);

        // Record score
        GameScore::recordScore($game, $user, $matched, null, null, [
            'numbers' => $numbers,
            'matched' => $matched,
            'prize' => $prize,
        ]);

        return response()->json([
            'success' => true,
            'numbers' => $numbers,
            'winning_numbers' => $winningNumbers,
            'matched' => $matched,
            'prize' => $prize,
            'message' => $matched >= 3 ? __('games.lottery_winner', ['amount' => $prize]) : __('games.lottery_no_win'),
        ]);
    }
}
