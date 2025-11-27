<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameScore extends Model
{
    protected $fillable = [
        'game_id',
        'user_id',
        'score',
        'level_reached',
        'time_played',
        'game_data',
    ];

    protected $casts = [
        'score' => 'integer',
        'game_data' => 'array',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForGame($query, int $gameId)
    {
        return $query->where('game_id', $gameId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeTopScores($query, int $limit = 10)
    {
        return $query->orderByDesc('score')->limit($limit);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->where('created_at', '>=', now()->startOfWeek());
    }

    public function scopeThisMonth($query)
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function recordScore(Game $game, User $user, int $score, ?int $level = null, ?int $timePlayed = null, ?array $data = null): self
    {
        $gameScore = self::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'score' => $score,
            'level_reached' => $level,
            'time_played' => $timePlayed,
            'game_data' => $data,
        ]);

        $game->incrementPlays();

        return $gameScore;
    }

    public static function getGlobalHighScores(int $limit = 20)
    {
        return self::with(['game', 'user:id,handle'])
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    public static function getUserScores(User $user, int $limit = 50)
    {
        return self::with('game')
            ->byUser($user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public static function getTodaysTopScores(int $limit = 10)
    {
        return self::with(['game', 'user:id,handle'])
            ->today()
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }
}
