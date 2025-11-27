<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BbsLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'url',
        'telnet_address',
        'sysop_name',
        'location',
        'software',
        'is_active',
        'is_featured',
        'last_checked_at',
        'is_online',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_online' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }
}
