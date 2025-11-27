<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryVote extends Model
{
    protected $fillable = [
        'story_id',
        'user_id',
        'vote',
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

    public function scopeUpvotes($query)
    {
        return $query->where('vote', 1);
    }

    public function scopeDownvotes($query)
    {
        return $query->where('vote', -1);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function isUpvote(): bool
    {
        return $this->vote === 1;
    }

    public function isDownvote(): bool
    {
        return $this->vote === -1;
    }
}
