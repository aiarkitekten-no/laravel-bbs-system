<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageThread extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'user_id',
        'subject',
        'is_sticky',
        'is_locked',
        'view_count',
        'reply_count',
        'last_message_id',
        'last_message_at',
    ];

    protected $casts = [
        'is_sticky' => 'boolean',
        'is_locked' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeSticky($query)
    {
        return $query->where('is_sticky', true);
    }

    public function scopeNotSticky($query)
    {
        return $query->where('is_sticky', false);
    }

    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeOrderedByActivity($query)
    {
        return $query->orderByDesc('is_sticky')
            ->orderByDesc('last_message_at');
    }

    public function scopeWithNewMessages($query, \DateTime $since)
    {
        return $query->where('last_message_at', '>', $since);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function updateLastMessage(Message $message): void
    {
        $this->update([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
            'reply_count' => $this->messages()->count() - 1, // Exclude first message
        ]);
    }

    public function lock(): void
    {
        $this->update(['is_locked' => true]);
    }

    public function unlock(): void
    {
        $this->update(['is_locked' => false]);
    }

    public function toggleSticky(): void
    {
        $this->update(['is_sticky' => !$this->is_sticky]);
    }

    public function canReply(User $user): bool
    {
        if ($this->is_locked && !$user->isStaff()) {
            return false;
        }
        return true;
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function createThread(Category $category, User $user, string $subject, string $body): self
    {
        $thread = self::create([
            'category_id' => $category->id,
            'user_id' => $user->id,
            'subject' => $subject,
            'last_message_at' => now(),
        ]);

        // Create the first message
        $message = $thread->messages()->create([
            'user_id' => $user->id,
            'body' => $body,
        ]);

        $thread->update(['last_message_id' => $message->id]);
        $category->incrementMessageCount();
        $user->increment('total_messages');

        return $thread;
    }

    public static function getThreadsForCategory(int $categoryId, int $perPage = 20)
    {
        return self::with(['user', 'lastMessage.user'])
            ->inCategory($categoryId)
            ->orderedByActivity()
            ->paginate($perPage);
    }
}
