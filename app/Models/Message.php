<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'thread_id',
        'user_id',
        'reply_to_id',
        'body',
        'body_html',
        'is_bot_generated',
        'bot_personality',
    ];

    protected $casts = [
        'is_bot_generated' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeByBot($query)
    {
        return $query->where('is_bot_generated', true);
    }

    public function scopeByHuman($query)
    {
        return $query->where('is_bot_generated', false);
    }

    public function scopeInThread($query, int $threadId)
    {
        return $query->where('thread_id', $threadId);
    }

    public function scopeNewerThan($query, \DateTime $date)
    {
        return $query->where('created_at', '>', $date);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function getQuoteText(): string
    {
        $handle = $this->user->handle;
        $date = $this->created_at->format('Y-m-d H:i');
        $body = $this->body;

        // Truncate if too long
        if (strlen($body) > 200) {
            $body = substr($body, 0, 200) . '...';
        }

        return ">>> {$handle} wrote on {$date}:\n>>> {$body}\n\n";
    }

    public function isFirstInThread(): bool
    {
        return $this->id === $this->thread->messages()->orderBy('id')->first()->id;
    }

    // ==========================================
    // BOOT
    // ==========================================

    protected static function booted(): void
    {
        static::created(function (Message $message) {
            // Update thread's last message
            $message->thread->updateLastMessage($message);
            
            // Update user's message count (if not bot)
            if (!$message->is_bot_generated) {
                $message->user->increment('total_messages');
            }
        });

        static::deleted(function (Message $message) {
            // Recalculate thread stats
            $thread = $message->thread;
            $lastMessage = $thread->messages()->latest()->first();
            
            if ($lastMessage) {
                $thread->update([
                    'last_message_id' => $lastMessage->id,
                    'last_message_at' => $lastMessage->created_at,
                    'reply_count' => $thread->messages()->count() - 1,
                ]);
            }
        });
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function reply(MessageThread $thread, User $user, string $body, ?Message $replyTo = null, bool $isBot = false, ?string $botPersonality = null): self
    {
        return self::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'reply_to_id' => $replyTo?->id,
            'body' => $body,
            'is_bot_generated' => $isBot,
            'bot_personality' => $botPersonality,
        ]);
    }

    public static function getMessagesForThread(int $threadId, int $perPage = 25)
    {
        return self::with(['user', 'replyTo.user'])
            ->inThread($threadId)
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    public static function searchMessages(string $query, ?int $categoryId = null, int $limit = 50)
    {
        $search = self::with(['thread.category', 'user'])
            ->where('body', 'LIKE', "%{$query}%");

        if ($categoryId) {
            $search->whereHas('thread', fn($q) => $q->where('category_id', $categoryId));
        }

        return $search->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
