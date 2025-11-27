<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeActivityLog extends Model
{
    public const ACTION_LOGIN = 'LOGIN';
    public const ACTION_LOGOUT = 'LOGOUT';
    public const ACTION_ACTIVITY = 'ACTIVITY';
    public const ACTION_TIMEOUT = 'TIMEOUT';
    public const ACTION_DISCONNECT = 'DISCONNECT';

    protected $fillable = [
        'node_id',
        'user_id',
        'action',
        'activity_description',
        'ip_address',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeRecent($query, int $limit = 100)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public static function logActivity(Node $node, User $user, string $action, string $description = null, string $ip = null): self
    {
        return self::create([
            'node_id' => $node->id,
            'user_id' => $user->id,
            'action' => $action,
            'activity_description' => $description,
            'ip_address' => $ip,
        ]);
    }
}
