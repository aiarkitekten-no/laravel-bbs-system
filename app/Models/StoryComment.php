<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoryComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'story_id',
        'user_id',
        'parent_id',
        'body',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StoryComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(StoryComment::class, 'parent_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForStory($query, int $storyId)
    {
        return $query->where('story_id', $storyId);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    public function hasReplies(): bool
    {
        return $this->replies()->count() > 0;
    }

    // ==========================================
    // BOOT
    // ==========================================

    protected static function booted(): void
    {
        static::created(function (StoryComment $comment) {
            $comment->story->incrementCommentCount();
        });

        static::deleted(function (StoryComment $comment) {
            $comment->story->decrementCommentCount();
        });
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function getCommentsForStory(int $storyId, int $perPage = 50)
    {
        return self::with(['user', 'replies.user'])
            ->forStory($storyId)
            ->topLevel()
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    public static function addComment(Story $story, User $user, string $body, ?StoryComment $parent = null): self
    {
        return self::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'body' => $body,
        ]);
    }
}
