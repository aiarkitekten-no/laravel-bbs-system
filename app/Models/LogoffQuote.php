<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogoffQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quote',
        'author',
        'is_approved',
        'times_shown',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'times_shown' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getRandom(): ?self
    {
        return static::where('is_approved', true)
            ->inRandomOrder()
            ->first();
    }

    public function incrementShown(): void
    {
        $this->increment('times_shown');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }
}
