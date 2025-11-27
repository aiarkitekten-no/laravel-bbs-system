<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnsiArt extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ansi_art';

    protected $fillable = [
        'user_id',
        'title',
        'artist',
        'group_name',
        'content',
        'width',
        'height',
        'category',
        'is_featured',
        'view_count',
        'download_count',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'download_count' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    // Categories
    const CATEGORY_LOGO = 'logo';
    const CATEGORY_WELCOME = 'welcome';
    const CATEGORY_GOODBYE = 'goodbye';
    const CATEGORY_MENU = 'menu';
    const CATEGORY_ARTWORK = 'artwork';
    const CATEGORY_HEADER = 'header';

    const CATEGORIES = [
        self::CATEGORY_LOGO,
        self::CATEGORY_WELCOME,
        self::CATEGORY_GOODBYE,
        self::CATEGORY_MENU,
        self::CATEGORY_ARTWORK,
        self::CATEGORY_HEADER,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    public function incrementDownloads(): void
    {
        $this->increment('download_count');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopePopular($query)
    {
        return $query->orderByDesc('view_count');
    }
}
