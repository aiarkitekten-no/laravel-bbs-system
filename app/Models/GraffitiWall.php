<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GraffitiWall extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'graffiti_wall';

    protected $fillable = [
        'user_id',
        'content',
        'color',
        'position_x',
        'position_y',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'position_x' => 'integer',
        'position_y' => 'integer',
    ];

    // ANSI Colors
    const COLORS = [
        'black' => '30',
        'red' => '31',
        'green' => '32',
        'yellow' => '33',
        'blue' => '34',
        'magenta' => '35',
        'cyan' => '36',
        'white' => '37',
        'bright_red' => '91',
        'bright_green' => '92',
        'bright_yellow' => '93',
        'bright_blue' => '94',
        'bright_magenta' => '95',
        'bright_cyan' => '96',
        'bright_white' => '97',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAnsiContent(): string
    {
        $colorCode = self::COLORS[$this->color] ?? '37';
        return "\e[{$colorCode}m{$this->content}\e[0m";
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }
}
