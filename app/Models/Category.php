<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'slug',
        'name_en',
        'name_no',
        'description_en',
        'description_no',
        'sort_order',
        'message_count',
        'story_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function threads(): HasMany
    {
        return $this->hasMany(MessageThread::class);
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
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

    public function incrementMessageCount(): void
    {
        $this->increment('message_count');
    }

    public function decrementMessageCount(): void
    {
        $this->decrement('message_count');
    }

    public function incrementStoryCount(): void
    {
        $this->increment('story_count');
    }

    public function recalculateCounts(): void
    {
        $this->update([
            'message_count' => $this->threads()->withCount('messages')->get()->sum('messages_count'),
            'story_count' => $this->stories()->count(),
        ]);
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function getActiveList(): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()->ordered()->get();
    }

    public static function findBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->first();
    }
}
