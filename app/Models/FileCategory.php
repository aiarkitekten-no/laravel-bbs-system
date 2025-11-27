<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FileCategory extends Model
{
    protected $fillable = [
        'name_en',
        'name_no',
        'description_en',
        'description_no',
        'sort_order',
        'file_count',
        'total_size',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_size' => 'integer',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'category_id');
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'no' ? $this->name_no : $this->name_en;
    }

    public function getDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $locale === 'no' ? $this->description_no : $this->description_en;
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->total_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name_en');
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function recalculateStats(): void
    {
        $stats = $this->files()
            ->where('status', 'APPROVED')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(file_size), 0) as size')
            ->first();

        $this->update([
            'file_count' => $stats->count ?? 0,
            'total_size' => $stats->size ?? 0,
        ]);
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function getActiveList(): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()->ordered()->get();
    }
}
