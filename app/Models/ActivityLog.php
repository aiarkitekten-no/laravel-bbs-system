<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeRecent($query, int $limit = 100)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function log(User $user, string $action, string $description = null, Request $request = null, array $metadata = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    public static function getRecentActivity(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return self::with('user')
            ->recent($limit)
            ->get();
    }

    public static function getUserActivity(int $userId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return self::with('user')
            ->byUser($userId)
            ->recent($limit)
            ->get();
    }
}
