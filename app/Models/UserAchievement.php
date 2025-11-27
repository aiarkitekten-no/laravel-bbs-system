<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAchievement extends Model
{
    protected $fillable = [
        'user_id',
        'achievement_id',
        'earned_at',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderByDesc('earned_at')->limit($limit);
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function getUserAchievements(User $user)
    {
        return self::with('achievement')
            ->byUser($user->id)
            ->orderByDesc('earned_at')
            ->get();
    }

    public static function getRecentlyEarned(int $limit = 20)
    {
        return self::with(['user:id,handle', 'achievement'])
            ->orderByDesc('earned_at')
            ->limit($limit)
            ->get();
    }

    public static function getTotalPoints(User $user): int
    {
        return self::byUser($user->id)
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->sum('achievements.points');
    }
}
