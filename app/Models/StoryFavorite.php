<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryFavorite extends Model
{
    protected $fillable = [
        'story_id',
        'user_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function getUserFavorites(User $user, int $perPage = 20)
    {
        return self::with('story.category')
            ->byUser($user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
