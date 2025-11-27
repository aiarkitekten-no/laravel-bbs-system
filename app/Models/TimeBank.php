<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeBank extends Model
{
    use HasFactory;

    protected $table = 'time_bank';

    protected $fillable = [
        'user_id',
        'saved_minutes',
        'max_save_minutes',
    ];

    protected $casts = [
        'saved_minutes' => 'integer',
        'max_save_minutes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deposit(int $minutes): bool
    {
        $newTotal = $this->saved_minutes + $minutes;

        if ($newTotal > $this->max_save_minutes) {
            return false;
        }

        $this->saved_minutes = $newTotal;
        $this->save();
        return true;
    }

    public function withdraw(int $minutes): int
    {
        $available = min($minutes, $this->saved_minutes);
        $this->saved_minutes -= $available;
        $this->save();
        return $available;
    }

    public function getAvailableMinutes(): int
    {
        return $this->saved_minutes;
    }

    public function canDeposit(int $minutes): bool
    {
        return ($this->saved_minutes + $minutes) <= $this->max_save_minutes;
    }

    public static function getOrCreate(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            [
                'saved_minutes' => 0,
                'max_save_minutes' => 120, // Default 2 hours max
            ]
        );
    }
}
