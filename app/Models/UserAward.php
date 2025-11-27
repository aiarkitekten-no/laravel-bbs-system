<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'award_type',
        'award_month',
        'title',
        'description',
        'points',
        'badge_icon',
    ];

    protected $casts = [
        'award_month' => 'date',
        'points' => 'integer',
    ];

    // Award types
    const TYPE_TOP_POSTER = 'top_poster';
    const TYPE_TOP_UPLOADER = 'top_uploader';
    const TYPE_TOP_GAMER = 'top_gamer';
    const TYPE_MOST_HELPFUL = 'most_helpful';
    const TYPE_BEST_STORY = 'best_story';
    const TYPE_LONGEST_ONLINE = 'longest_online';
    const TYPE_SYSOP_CHOICE = 'sysop_choice';

    const AWARD_TYPES = [
        self::TYPE_TOP_POSTER => 'Top Poster of the Month',
        self::TYPE_TOP_UPLOADER => 'Top Uploader of the Month',
        self::TYPE_TOP_GAMER => 'Top Gamer of the Month',
        self::TYPE_MOST_HELPFUL => 'Most Helpful User',
        self::TYPE_BEST_STORY => 'Best Story Contributor',
        self::TYPE_LONGEST_ONLINE => 'Longest Online Time',
        self::TYPE_SYSOP_CHOICE => 'SysOp\'s Choice Award',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function grantAward(User $user, string $type, string $month): self
    {
        $title = self::AWARD_TYPES[$type] ?? $type;

        return static::create([
            'user_id' => $user->id,
            'award_type' => $type,
            'award_month' => $month,
            'title' => $title,
            'description' => "{$title} - {$month}",
            'points' => self::getPointsForType($type),
            'badge_icon' => self::getBadgeForType($type),
        ]);
    }

    public static function getPointsForType(string $type): int
    {
        $points = [
            self::TYPE_TOP_POSTER => 100,
            self::TYPE_TOP_UPLOADER => 150,
            self::TYPE_TOP_GAMER => 100,
            self::TYPE_MOST_HELPFUL => 200,
            self::TYPE_BEST_STORY => 150,
            self::TYPE_LONGEST_ONLINE => 75,
            self::TYPE_SYSOP_CHOICE => 250,
        ];

        return $points[$type] ?? 50;
    }

    public static function getBadgeForType(string $type): string
    {
        $badges = [
            self::TYPE_TOP_POSTER => '📝',
            self::TYPE_TOP_UPLOADER => '📤',
            self::TYPE_TOP_GAMER => '🎮',
            self::TYPE_MOST_HELPFUL => '🤝',
            self::TYPE_BEST_STORY => '📚',
            self::TYPE_LONGEST_ONLINE => '⏰',
            self::TYPE_SYSOP_CHOICE => '⭐',
        ];

        return $badges[$type] ?? '🏆';
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->whereMonth('award_month', date('m', strtotime($month)))
            ->whereYear('award_month', date('Y', strtotime($month)));
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('award_type', $type);
    }
}
