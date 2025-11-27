<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileRequest extends Model
{
    const STATUS_OPEN = 'OPEN';
    const STATUS_FULFILLED = 'FULFILLED';
    const STATUS_CLOSED = 'CLOSED';

    protected $fillable = [
        'user_id',
        'filename_requested',
        'description',
        'status',
        'fulfilled_by',
        'fulfilled_file_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fulfilledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function fulfilledFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'fulfilled_file_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeFulfilled($query)
    {
        return $query->where('status', self::STATUS_FULFILLED);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function fulfill(User $fulfiller, File $file): void
    {
        $this->update([
            'status' => self::STATUS_FULFILLED,
            'fulfilled_by' => $fulfiller->id,
            'fulfilled_file_id' => $file->id,
        ]);
    }

    public function close(): void
    {
        $this->update(['status' => self::STATUS_CLOSED]);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function getOpenRequests(int $limit = 50)
    {
        return self::with('user:id,handle')
            ->open()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public static function createRequest(User $user, string $filename, ?string $description = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'filename_requested' => $filename,
            'description' => $description,
        ]);
    }
}
