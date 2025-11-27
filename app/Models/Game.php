<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    const TYPE_SIMPLE = 'SIMPLE';   // Quick games (trivia, hangman)
    const TYPE_DOOR = 'DOOR';       // Complex door games (LORD, Trade Wars)
    const TYPE_DAILY = 'DAILY';     // Once per day (lottery)

    protected $fillable = [
        'slug',
        'name_en',
        'name_no',
        'description_en',
        'description_no',
        'type',
        'is_active',
        'config',
        'plays_today',
        'plays_total',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function scores(): HasMany
    {
        return $this->hasMany(GameScore::class);
    }

    public function playerStates(): HasMany
    {
        return $this->hasMany(GamePlayerState::class);
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'no' ? $this->name_no : $this->name_en;
    }

    public function getDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $locale === 'no' ? $this->description_no : $this->description_en;
    }

    public function getConfigValueAttribute(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSimple($query)
    {
        return $query->ofType(self::TYPE_SIMPLE);
    }

    public function scopeDoor($query)
    {
        return $query->ofType(self::TYPE_DOOR);
    }

    public function scopeDaily($query)
    {
        return $query->ofType(self::TYPE_DAILY);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function incrementPlays(): void
    {
        $this->increment('plays_today');
        $this->increment('plays_total');
    }

    public function resetDailyPlays(): void
    {
        $this->update(['plays_today' => 0]);
    }

    public function getHighScores(int $limit = 10)
    {
        return $this->scores()
            ->with('user:id,handle')
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    public function getUserHighScore(User $user): ?GameScore
    {
        return $this->scores()
            ->where('user_id', $user->id)
            ->orderByDesc('score')
            ->first();
    }

    public function getPlayerState(User $user): ?GamePlayerState
    {
        return $this->playerStates()
            ->where('user_id', $user->id)
            ->first();
    }

    public function getOrCreatePlayerState(User $user): GamePlayerState
    {
        return $this->playerStates()->firstOrCreate(
            ['user_id' => $user->id],
            ['state' => $this->getInitialState()]
        );
    }

    public function getInitialState(): array
    {
        // Default initial state - games can override via config
        return $this->config['initial_state'] ?? [];
    }

    public function isSimple(): bool
    {
        return $this->type === self::TYPE_SIMPLE;
    }

    public function isDoor(): bool
    {
        return $this->type === self::TYPE_DOOR;
    }

    public function isDaily(): bool
    {
        return $this->type === self::TYPE_DAILY;
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function findBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->first();
    }

    public static function getActiveGames(): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()->orderBy('name_en')->get();
    }

    public static function getGamesByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()->ofType($type)->orderBy('name_en')->get();
    }

    public static function resetAllDailyPlays(): void
    {
        self::query()->update(['plays_today' => 0]);
    }
}
