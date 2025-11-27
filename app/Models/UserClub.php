<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserClub extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'founder_id',
        'logo_ansi',
        'is_public',
        'is_active',
        'member_count',
        'max_members',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'member_count' => 'integer',
        'max_members' => 'integer',
    ];

    public function founder()
    {
        return $this->belongsTo(User::class, 'founder_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'user_club_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function isFounder(User $user): bool
    {
        return $this->founder_id === $user->id;
    }

    public function addMember(User $user, string $role = 'member'): bool
    {
        if ($this->isMember($user)) {
            return false;
        }

        if ($this->member_count >= $this->max_members) {
            return false;
        }

        $this->members()->attach($user->id, [
            'role' => $role,
            'joined_at' => now(),
        ]);

        $this->increment('member_count');
        return true;
    }

    public function removeMember(User $user): bool
    {
        if (!$this->isMember($user)) {
            return false;
        }

        $this->members()->detach($user->id);
        $this->decrement('member_count');
        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
