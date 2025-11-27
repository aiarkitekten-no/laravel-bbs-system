<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Oneliner extends Model
{
    protected $fillable = [
        'user_id',
        'content',
        'is_ai_generated',
        'is_approved',
    ];

    protected $casts = [
        'is_ai_generated' => 'boolean',
        'is_approved' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    public function scopeByAi($query)
    {
        return $query->where('is_ai_generated', true);
    }

    public function scopeByHuman($query)
    {
        return $query->where('is_ai_generated', false);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $limit = 20)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function approve(): void
    {
        $this->update(['is_approved' => true]);
    }

    public function reject(): void
    {
        $this->delete();
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function getRecentOneliners(int $limit = 20)
    {
        return self::with('user')
            ->approved()
            ->recent($limit)
            ->get();
    }

    public static function getPendingOneliners(int $limit = 50)
    {
        return self::with('user')
            ->pending()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public static function postOneliner(User $user, string $content, bool $isAiGenerated = false, bool $autoApprove = false): self
    {
        // Auto-approve for SYSOP/COSYSOP or if autoApprove is set
        $approved = $autoApprove || $user->isStaff();

        return self::create([
            'user_id' => $user->id,
            'content' => $content,
            'is_ai_generated' => $isAiGenerated,
            'is_approved' => $approved,
        ]);
    }
}
