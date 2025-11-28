<?php

namespace App\Services;

use App\Models\User;
use App\Models\File;
use Illuminate\Support\Facades\DB;

/**
 * Credits & File Ratio Service
 * 
 * Handles BBS-style credit system for file downloads/uploads
 */
class FileCreditsService
{
    /**
     * Credits earned per MB uploaded
     */
    protected int $creditsPerUploadMb;
    
    /**
     * Credits cost per MB downloaded
     */
    protected int $creditsPerDownloadMb;
    
    /**
     * Required upload/download ratio
     */
    protected float $requiredRatio;
    
    /**
     * Whether ratio is enforced
     */
    protected bool $ratioRequired;
    
    /**
     * User levels exempt from ratio
     */
    protected array $exemptLevels;
    
    /**
     * Free leech mode (no credits required)
     */
    protected bool $freeLeech;

    public function __construct()
    {
        $this->creditsPerUploadMb = config('bbs.files.credits_per_upload_mb', 10);
        $this->creditsPerDownloadMb = config('bbs.files.credits_per_download_mb', 5);
        $this->requiredRatio = config('bbs.files.default_ratio', 3);
        $this->ratioRequired = config('bbs.files.ratio_required', true);
        $this->exemptLevels = config('bbs.files.ratio_exempt_levels', ['ELITE', 'COSYSOP', 'SYSOP']);
        $this->freeLeech = config('bbs.files.free_leech_enabled', false);
    }

    /**
     * Calculate credits earned for an upload
     */
    public function calculateUploadCredits(int $fileSizeBytes): int
    {
        $megabytes = $fileSizeBytes / 1048576; // 1 MB = 1048576 bytes
        return (int) ceil($megabytes * $this->creditsPerUploadMb);
    }

    /**
     * Calculate credits cost for a download
     */
    public function calculateDownloadCost(int $fileSizeBytes): int
    {
        if ($this->freeLeech) {
            return 0;
        }
        
        $megabytes = $fileSizeBytes / 1048576;
        return (int) ceil($megabytes * $this->creditsPerDownloadMb);
    }

    /**
     * Check if user can download based on ratio and credits
     */
    public function canDownload(User $user, File $file): array
    {
        // Free leech mode - everyone can download
        if ($this->freeLeech) {
            return ['allowed' => true, 'reason' => 'free_leech'];
        }
        
        // Staff/exempt levels bypass ratio
        if ($this->isExempt($user)) {
            return ['allowed' => true, 'reason' => 'exempt'];
        }
        
        // Check ratio if required
        if ($this->ratioRequired) {
            $ratio = $this->getUserRatio($user);
            if ($ratio < (1 / $this->requiredRatio)) {
                return [
                    'allowed' => false,
                    'reason' => 'ratio_too_low',
                    'current_ratio' => $ratio,
                    'required_ratio' => "1:{$this->requiredRatio}",
                    'message' => __('files.ratio_too_low', [
                        'current' => number_format($ratio, 2),
                        'required' => "1:{$this->requiredRatio}",
                    ]),
                ];
            }
        }
        
        // Check credits
        $cost = $this->calculateDownloadCost($file->file_size);
        if ($user->credits < $cost) {
            return [
                'allowed' => false,
                'reason' => 'insufficient_credits',
                'credits_required' => $cost,
                'credits_available' => $user->credits,
                'message' => __('files.insufficient_credits', [
                    'required' => $cost,
                    'available' => $user->credits,
                ]),
            ];
        }
        
        return ['allowed' => true, 'reason' => 'ok', 'cost' => $cost];
    }

    /**
     * Process an upload - award credits
     */
    public function processUpload(User $user, File $file): int
    {
        $credits = $this->calculateUploadCredits($file->file_size);
        
        DB::transaction(function () use ($user, $file, $credits) {
            // Award credits
            $user->addCredits($credits);
            
            // Update user stats
            $user->increment('total_uploads');
            $user->increment('upload_bytes', $file->file_size);
            
            // Log the transaction
            $this->logTransaction($user, 'upload', $credits, $file->id);
        });
        
        return $credits;
    }

    /**
     * Process a download - charge credits
     */
    public function processDownload(User $user, File $file): int
    {
        $cost = $this->calculateDownloadCost($file->file_size);
        
        DB::transaction(function () use ($user, $file, $cost) {
            // Charge credits (if not free leech)
            if ($cost > 0 && !$this->freeLeech) {
                $user->deductCredits($cost);
            }
            
            // Update stats
            $user->increment('total_downloads');
            $user->increment('download_bytes', $file->file_size);
            $file->increment('download_count');
            
            // Log the transaction
            $this->logTransaction($user, 'download', -$cost, $file->id);
        });
        
        return $cost;
    }

    /**
     * Get user's upload/download ratio
     */
    public function getUserRatio(User $user): float
    {
        $uploads = $user->upload_bytes ?? 0;
        $downloads = $user->download_bytes ?? 0;
        
        if ($downloads === 0) {
            return $uploads > 0 ? 999.99 : 1.0; // Infinite ratio if no downloads
        }
        
        return round($uploads / $downloads, 2);
    }

    /**
     * Get user's file stats
     */
    public function getUserStats(User $user): array
    {
        $ratio = $this->getUserRatio($user);
        $requiredRatio = 1 / $this->requiredRatio;
        
        return [
            'credits' => $user->credits,
            'total_uploads' => $user->total_uploads ?? 0,
            'total_downloads' => $user->total_downloads ?? 0,
            'upload_bytes' => $user->upload_bytes ?? 0,
            'download_bytes' => $user->download_bytes ?? 0,
            'upload_formatted' => $this->formatBytes($user->upload_bytes ?? 0),
            'download_formatted' => $this->formatBytes($user->download_bytes ?? 0),
            'ratio' => $ratio,
            'ratio_formatted' => number_format($ratio, 2) . ':1',
            'ratio_required' => $this->ratioRequired,
            'ratio_minimum' => "1:{$this->requiredRatio}",
            'can_download' => !$this->ratioRequired || $ratio >= $requiredRatio || $this->isExempt($user),
            'is_exempt' => $this->isExempt($user),
            'free_leech' => $this->freeLeech,
        ];
    }
    
    /**
     * Alias for getUserStats - for ratio endpoint
     */
    public function getRatioInfo(User $user): array
    {
        return $this->getUserStats($user);
    }
    
    /**
     * Get just the ratio value
     */
    public function getRatio(User $user): float
    {
        return $this->getUserRatio($user);
    }
    
    /**
     * Record upload stats (wrapper for processUpload)
     */
    public function recordUpload(User $user, File $file): int
    {
        return $this->processUpload($user, $file);
    }
    
    /**
     * Charge for download (wrapper for processDownload)
     */
    public function chargeDownload(User $user, File $file): int
    {
        return $this->processDownload($user, $file);
    }

    /**
     * Check if user is exempt from ratio requirements
     */
    public function isExempt(User $user): bool
    {
        return in_array($user->level, $this->exemptLevels);
    }

    /**
     * Award bonus credits (for special events, achievements, etc.)
     */
    public function awardBonus(User $user, int $amount, string $reason): void
    {
        DB::transaction(function () use ($user, $amount, $reason) {
            $user->addCredits($amount);
            $this->logTransaction($user, 'bonus', $amount, null, $reason);
        });
    }

    /**
     * Log credit transaction
     */
    protected function logTransaction(User $user, string $type, int $amount, ?int $fileId = null, ?string $note = null): void
    {
        // Could log to activity_logs or a dedicated credits_log table
        // For now, we just rely on the user's credit balance
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Get leaderboard of top uploaders
     */
    public function getTopUploaders(int $limit = 20): \Illuminate\Support\Collection
    {
        return User::select('id', 'handle', 'total_uploads', 'upload_bytes')
            ->where('total_uploads', '>', 0)
            ->orderByDesc('upload_bytes')
            ->limit($limit)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'handle' => $u->handle,
                'uploads' => $u->total_uploads,
                'bytes' => $u->upload_bytes,
                'formatted' => $this->formatBytes($u->upload_bytes ?? 0),
            ]);
    }
}
