<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 'PENDING';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_REJECTED = 'REJECTED';
    const STATUS_QUARANTINED = 'QUARANTINED';

    protected $fillable = [
        'category_id',
        'uploader_id',
        'filename',
        'storage_path',
        'file_id_diz',
        'description',
        'file_size',
        'mime_type',
        'md5_hash',
        'download_count',
        'credits_cost',
        'status',
        'virus_scanned',
        'virus_scanned_at',
        'virus_scan_result',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'virus_scanned' => 'boolean',
        'virus_scanned_at' => 'datetime',
        'approved_at' => 'datetime',
        'file_size' => 'integer',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function category(): BelongsTo
    {
        return $this->belongsTo(FileCategory::class, 'category_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeQuarantined($query)
    {
        return $query->where('status', self::STATUS_QUARANTINED);
    }

    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeNewerThan($query, \DateTime $date)
    {
        return $query->where('created_at', '>', $date);
    }

    public function scopeByUploader($query, int $userId)
    {
        return $query->where('uploader_id', $userId);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function approve(User $approver): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->category->recalculateStats();
        $this->uploader->increment('total_uploads');
    }

    public function reject(User $rejector): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $rejector->id,
            'approved_at' => now(),
        ]);

        // Optionally delete the physical file
        if (Storage::exists($this->storage_path)) {
            Storage::delete($this->storage_path);
        }
    }

    public function quarantine(): void
    {
        $this->update(['status' => self::STATUS_QUARANTINED]);
    }

    public function incrementDownloads(): void
    {
        $this->increment('download_count');
    }

    public function canDownload(User $user): bool
    {
        if (!$this->is_approved) {
            // Only uploader and staff can download unapproved files
            return $user->id === $this->uploader_id || $user->isStaff();
        }

        // Check credits if required
        if ($this->credits_cost > 0 && $user->credits < $this->credits_cost) {
            return false;
        }

        return true;
    }

    public function chargeDownload(User $user): bool
    {
        if ($this->credits_cost > 0) {
            if ($user->credits < $this->credits_cost) {
                return false;
            }
            $user->decrement('credits', $this->credits_cost);
        }

        $this->incrementDownloads();
        $user->increment('total_downloads');

        return true;
    }

    public function markVirusScanned(bool $clean, ?string $result = null): void
    {
        $this->update([
            'virus_scanned' => true,
            'virus_scanned_at' => now(),
            'virus_scan_result' => $result,
        ]);

        if (!$clean) {
            $this->quarantine();
        }
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function findDuplicate(string $md5Hash): ?self
    {
        return self::where('md5_hash', $md5Hash)
            ->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PENDING])
            ->first();
    }

    public static function getNewFiles(\DateTime $since, int $limit = 50)
    {
        return self::with(['category', 'uploader:id,handle'])
            ->approved()
            ->newerThan($since)
            ->orderByDesc('approved_at')
            ->limit($limit)
            ->get();
    }

    public static function getTopUploaders(int $limit = 20)
    {
        return User::select('id', 'handle', 'total_uploads')
            ->where('total_uploads', '>', 0)
            ->orderByDesc('total_uploads')
            ->limit($limit)
            ->get();
    }

    public static function search(string $query, ?int $categoryId = null, int $limit = 50)
    {
        $search = self::with(['category', 'uploader:id,handle'])
            ->approved()
            ->where(function ($q) use ($query) {
                $q->where('filename', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%")
                    ->orWhere('file_id_diz', 'LIKE', "%{$query}%");
            });

        if ($categoryId) {
            $search->inCategory($categoryId);
        }

        return $search->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
