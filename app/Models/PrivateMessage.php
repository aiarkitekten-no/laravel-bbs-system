<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'recipient_id',
        'subject',
        'body',
        'is_read',
        'read_at',
        'sender_deleted',
        'recipient_deleted',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'sender_deleted' => 'boolean',
        'recipient_deleted' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('recipient_id', $userId)
              ->where('recipient_deleted', false);
        })->orWhere(function ($q) use ($userId) {
            $q->where('sender_id', $userId)
              ->where('sender_deleted', false);
        });
    }

    public function scopeInbox($query, int $userId)
    {
        return $query->where('recipient_id', $userId)
            ->where('recipient_deleted', false);
    }

    public function scopeSent($query, int $userId)
    {
        return $query->where('sender_id', $userId)
            ->where('sender_deleted', false);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    public function deleteForSender(): void
    {
        $this->update(['sender_deleted' => true]);
    }

    public function deleteForRecipient(): void
    {
        $this->update(['recipient_deleted' => true]);
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function send(User $sender, User $recipient, string $subject, string $body): self
    {
        return self::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    public static function getUnreadCount(int $userId): int
    {
        return self::inbox($userId)->unread()->count();
    }
}
