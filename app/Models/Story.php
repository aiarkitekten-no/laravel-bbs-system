<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Story extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'title_en',
        'title_no',
        'content_en',
        'content_no',
        'ai_model',
        'ai_prompt',
        'upvotes',
        'downvotes',
        'view_count',
        'comment_count',
        'story_date',
    ];

    protected $casts = [
        'story_date' => 'date',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(StoryVote::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(StoryComment::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(StoryFavorite::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'story_favorites')
            ->withTimestamps();
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getTitleAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'no' ? ($this->title_no ?: $this->title_en) : $this->title_en;
    }

    public function getContentAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'no' ? ($this->content_no ?: $this->content_en) : $this->content_en;
    }

    public function getScoreAttribute(): int
    {
        return $this->upvotes - $this->downvotes;
    }

    public function getVotePercentageAttribute(): int
    {
        $total = $this->upvotes + $this->downvotes;
        if ($total === 0) return 0;
        return (int) round(($this->upvotes / $total) * 100);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('story_date', today());
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('story_date', '>=', now()->subDays($days));
    }

    public function scopeOrderedByScore($query)
    {
        return $query->orderByRaw('(upvotes - downvotes) DESC');
    }

    public function scopeOrderedByDate($query)
    {
        return $query->orderByDesc('story_date')->orderByDesc('created_at');
    }

    public function scopeMostViewed($query)
    {
        return $query->orderByDesc('view_count');
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function vote(User $user, int $vote): StoryVote
    {
        $existingVote = $this->votes()->where('user_id', $user->id)->first();

        if ($existingVote) {
            // If same vote, remove it
            if ($existingVote->vote === $vote) {
                $existingVote->delete();
                $this->recalculateVotes();
                return $existingVote;
            }

            // Change vote
            $existingVote->update(['vote' => $vote]);
            $this->recalculateVotes();
            return $existingVote;
        }

        // New vote
        $storyVote = $this->votes()->create([
            'user_id' => $user->id,
            'vote' => $vote,
        ]);

        $this->recalculateVotes();
        return $storyVote;
    }

    public function upvote(User $user): StoryVote
    {
        return $this->vote($user, 1);
    }

    public function downvote(User $user): StoryVote
    {
        return $this->vote($user, -1);
    }

    public function getUserVote(User $user): ?int
    {
        $vote = $this->votes()->where('user_id', $user->id)->first();
        return $vote?->vote;
    }

    public function recalculateVotes(): void
    {
        $this->update([
            'upvotes' => $this->votes()->where('vote', 1)->count(),
            'downvotes' => $this->votes()->where('vote', -1)->count(),
        ]);
    }

    public function toggleFavorite(User $user): bool
    {
        $existing = $this->favorites()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        $this->favorites()->create(['user_id' => $user->id]);
        return true;
    }

    public function isFavoritedBy(User $user): bool
    {
        return $this->favorites()->where('user_id', $user->id)->exists();
    }

    public function incrementCommentCount(): void
    {
        $this->increment('comment_count');
    }

    public function decrementCommentCount(): void
    {
        $this->decrement('comment_count');
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function getTodaysStory(): ?self
    {
        return self::today()->first();
    }

    public static function getStoriesForCategory(int $categoryId, int $perPage = 20)
    {
        return self::with('category')
            ->inCategory($categoryId)
            ->orderedByDate()
            ->paginate($perPage);
    }

    public static function getTopStories(int $limit = 10)
    {
        return self::with('category')
            ->orderedByScore()
            ->limit($limit)
            ->get();
    }

    public static function getRecentStories(int $days = 7, int $limit = 20)
    {
        return self::with('category')
            ->recent($days)
            ->orderedByDate()
            ->limit($limit)
            ->get();
    }
}
