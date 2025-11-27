<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAutoReply extends Model
{
    protected $fillable = [
        'user_id',
        'is_enabled',
        'message',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enable(): void
    {
        $this->update(['is_enabled' => true]);
    }

    public function disable(): void
    {
        $this->update(['is_enabled' => false]);
    }

    public function toggle(): void
    {
        $this->update(['is_enabled' => !$this->is_enabled]);
    }

    public static function getReplyForUser(User $user): ?string
    {
        $autoReply = $user->autoReply;
        
        if ($autoReply && $autoReply->is_enabled) {
            return $autoReply->message;
        }

        return null;
    }
}
