<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlayerState extends Model
{
    protected $fillable = [
        'game_id',
        'user_id',
        'state',
        'turns_today',
        'turns_total',
        'last_played_date',
    ];

    protected $casts = [
        'state' => 'array',
        'last_played_date' => 'date',
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
    // ACCESSORS
    // ==========================================

    public function getStateValueAttribute(string $key, $default = null)
    {
        return $this->state[$key] ?? $default;
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

    public function scopePlayedToday($query)
    {
        return $query->whereDate('last_played_date', today());
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function updateState(array $newState): void
    {
        $this->update([
            'state' => array_merge($this->state ?? [], $newState),
        ]);
    }

    public function setState(string $key, $value): void
    {
        $state = $this->state ?? [];
        $state[$key] = $value;
        $this->update(['state' => $state]);
    }

    public function getState(string $key, $default = null)
    {
        return $this->state[$key] ?? $default;
    }

    public function useTurn(): bool
    {
        // Check if it's a new day
        if ($this->last_played_date?->isToday() === false) {
            $this->update([
                'turns_today' => 1,
                'turns_total' => $this->turns_total + 1,
                'last_played_date' => today(),
            ]);
            return true;
        }

        // Check turn limit from game config
        $maxTurns = $this->game->config['max_daily_turns'] ?? PHP_INT_MAX;

        if ($this->turns_today >= $maxTurns) {
            return false;
        }

        $this->increment('turns_today');
        $this->increment('turns_total');
        $this->update(['last_played_date' => today()]);

        return true;
    }

    public function hasPlayedToday(): bool
    {
        return $this->last_played_date?->isToday() ?? false;
    }

    public function getRemainingTurns(): int
    {
        $maxTurns = $this->game->config['max_daily_turns'] ?? PHP_INT_MAX;

        if (!$this->last_played_date?->isToday()) {
            return $maxTurns;
        }

        return max(0, $maxTurns - $this->turns_today);
    }

    public function resetForNewDay(): void
    {
        $this->update([
            'turns_today' => 0,
            'last_played_date' => null,
        ]);
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function resetAllDailyTurns(): void
    {
        self::query()->update(['turns_today' => 0]);
    }

    public static function getLeaderboard(int $gameId, string $stateKey = 'level', int $limit = 20)
    {
        return self::with('user:id,handle')
            ->forGame($gameId)
            ->orderByRaw("JSON_EXTRACT(state, '$.{$stateKey}') DESC")
            ->limit($limit)
            ->get();
    }
}
