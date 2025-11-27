<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    const CATEGORY_MESSAGES = 'MESSAGES';
    const CATEGORY_FILES = 'FILES';
    const CATEGORY_GAMES = 'GAMES';
    const CATEGORY_SOCIAL = 'SOCIAL';
    const CATEGORY_TIME = 'TIME';
    const CATEGORY_SPECIAL = 'SPECIAL';

    protected $fillable = [
        'slug',
        'name_en',
        'name_no',
        'description_en',
        'description_no',
        'icon',
        'points',
        'category',
        'requirements',
        'is_secret',
    ];

    protected $casts = [
        'requirements' => 'array',
        'is_secret' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'no' ? $this->name_no : $this->name_en;
    }

    public function getDescriptionAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'no' ? $this->description_no : $this->description_en;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeVisible($query)
    {
        return $query->where('is_secret', false);
    }

    public function scopeSecret($query)
    {
        return $query->where('is_secret', true);
    }

    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // ==========================================
    // METHODS
    // ==========================================

    public function isEarnedBy(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    public function awardTo(User $user): bool
    {
        if ($this->isEarnedBy($user)) {
            return false;
        }

        $this->users()->attach($user->id, ['earned_at' => now()]);

        return true;
    }

    public function checkRequirements(User $user): bool
    {
        if (empty($this->requirements)) {
            return false;
        }

        foreach ($this->requirements as $req => $value) {
            switch ($req) {
                case 'total_messages':
                    if ($user->total_messages < $value) return false;
                    break;
                case 'total_uploads':
                    if ($user->total_uploads < $value) return false;
                    break;
                case 'total_downloads':
                    if ($user->total_downloads < $value) return false;
                    break;
                case 'total_logins':
                    if ($user->total_logins < $value) return false;
                    break;
                case 'days_member':
                    if ($user->created_at->diffInDays(now()) < $value) return false;
                    break;
                case 'game_score':
                    // Check if user has achieved a certain score in any game
                    $hasScore = GameScore::where('user_id', $user->id)
                        ->where('score', '>=', $value)
                        ->exists();
                    if (!$hasScore) return false;
                    break;
                case 'level':
                    if (!$user->hasMinimumLevel($value)) return false;
                    break;
            }
        }

        return true;
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function findBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->first();
    }

    public static function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return self::orderBy('category')->orderBy('points')->get();
    }

    public static function getByCategory(string $category): \Illuminate\Database\Eloquent\Collection
    {
        return self::inCategory($category)->orderBy('points')->get();
    }

    public static function checkAndAwardAll(User $user): array
    {
        $awarded = [];

        $achievements = self::all();
        foreach ($achievements as $achievement) {
            if (!$achievement->isEarnedBy($user) && $achievement->checkRequirements($user)) {
                $achievement->awardTo($user);
                $awarded[] = $achievement;
            }
        }

        return $awarded;
    }
}
