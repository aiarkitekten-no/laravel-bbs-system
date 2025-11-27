<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poll extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'question',
        'description',
        'options',
        'is_active',
        'is_multiple_choice',
        'show_results_before_vote',
        'expires_at',
        'total_votes',
    ];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
        'is_multiple_choice' => 'boolean',
        'show_results_before_vote' => 'boolean',
        'expires_at' => 'datetime',
        'total_votes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }

    public function hasUserVoted(User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }

    public function getUserVotes(User $user): array
    {
        return $this->votes()
            ->where('user_id', $user->id)
            ->pluck('option_index')
            ->toArray();
    }

    public function vote(User $user, array $optionIndices): bool
    {
        if ($this->hasUserVoted($user)) {
            return false;
        }

        if (!$this->is_multiple_choice && count($optionIndices) > 1) {
            return false;
        }

        foreach ($optionIndices as $index) {
            if ($index < 0 || $index >= count($this->options)) {
                continue;
            }

            $this->votes()->create([
                'user_id' => $user->id,
                'option_index' => $index,
            ]);
        }

        $this->increment('total_votes', count($optionIndices));
        return true;
    }

    public function getResults(): array
    {
        $results = [];
        $options = $this->options ?? [];

        foreach ($options as $index => $option) {
            $voteCount = $this->votes()->where('option_index', $index)->count();
            $percentage = $this->total_votes > 0 
                ? round(($voteCount / $this->total_votes) * 100, 1) 
                : 0;

            $results[] = [
                'index' => $index,
                'text' => $option,
                'votes' => $voteCount,
                'percentage' => $percentage,
            ];
        }

        return $results;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
