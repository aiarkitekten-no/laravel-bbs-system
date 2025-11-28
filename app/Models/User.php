<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // User levels
    public const LEVEL_GUEST = 'GUEST';
    public const LEVEL_USER = 'USER';
    public const LEVEL_ELITE = 'ELITE';
    public const LEVEL_COSYSOP = 'COSYSOP';
    public const LEVEL_SYSOP = 'SYSOP';

    public const LEVELS = [
        self::LEVEL_GUEST,
        self::LEVEL_USER,
        self::LEVEL_ELITE,
        self::LEVEL_COSYSOP,
        self::LEVEL_SYSOP,
    ];

    /**
     * The attributes that are mass assignable.
     * SECURITY: level, credits, is_bot removed to prevent privilege escalation
     */
    protected $fillable = [
        'handle',
        'name',
        'email',
        'password',
        'locale',
        'bio',
        'location',
        'ascii_signature',
        'birthday',
        'last_login_at',
        'last_activity_at',
        'last_ip',
        'is_online',
        'current_node_id',
    ];

    /**
     * Attributes that require explicit setting (not mass assignable)
     * Use dedicated methods: setLevel(), addCredits(), etc.
     */
    protected $guarded_sensitive = [
        'level',
        'credits',
        'is_bot',
        'bot_personality',
        'total_logins',
        'total_messages',
        'total_files_uploaded',
        'total_files_downloaded',
        'total_time_online',
        'daily_time_used',
        'daily_time_limit',
        'time_bank',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'birthday' => 'date',
        'is_bot' => 'boolean',
        'is_online' => 'boolean',
    ];

    // ==========================================
    // SECURE SETTERS FOR SENSITIVE ATTRIBUTES
    // ==========================================

    /**
     * Safely set user level (not mass assignable)
     */
    public function setLevel(string $level): self
    {
        if (!in_array($level, self::LEVELS)) {
            throw new \InvalidArgumentException("Invalid level: {$level}");
        }
        $this->level = $level;
        return $this;
    }

    /**
     * Safely set credits (not mass assignable)
     */
    public function setCredits(int $credits): self
    {
        $this->credits = max(0, $credits);
        return $this;
    }

    /**
     * Safely set bot status (not mass assignable)
     */
    public function setIsBot(bool $isBot, ?string $personality = null): self
    {
        $this->is_bot = $isBot;
        if ($isBot && $personality) {
            $this->bot_personality = $personality;
        }
        return $this;
    }

    /**
     * Create a new user with initial sensitive values
     */
    public static function createWithDefaults(array $attributes, string $level = self::LEVEL_USER, int $credits = 100): self
    {
        $user = new self($attributes);
        $user->level = $level;
        $user->credits = $credits;
        $user->daily_time_limit = config('punktet.default_time_limit', 60) * 60;
        $user->save();
        return $user;
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function currentNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'current_node_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function messageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class);
    }

    public function privateMessagesSent(): HasMany
    {
        return $this->hasMany(PrivateMessage::class, 'sender_id');
    }

    public function privateMessagesReceived(): HasMany
    {
        return $this->hasMany(PrivateMessage::class, 'recipient_id');
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function storyVotes(): HasMany
    {
        return $this->hasMany(StoryVote::class);
    }

    public function storyComments(): HasMany
    {
        return $this->hasMany(StoryComment::class);
    }

    public function storyFavorites(): HasMany
    {
        return $this->hasMany(StoryFavorite::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'uploaded_by');
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public function oneliners(): HasMany
    {
        return $this->hasMany(Oneliner::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function nodeActivityLogs(): HasMany
    {
        return $this->hasMany(NodeActivityLog::class);
    }

    public function gameScores(): HasMany
    {
        return $this->hasMany(GameScore::class);
    }

    public function autoReply(): HasOne
    {
        return $this->hasOne(UserAutoReply::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(UserAward::class);
    }

    // ==========================================
    // LEVEL CHECKS
    // ==========================================

    public function isGuest(): bool
    {
        return $this->level === self::LEVEL_GUEST;
    }

    public function isUser(): bool
    {
        return $this->level === self::LEVEL_USER;
    }

    public function isElite(): bool
    {
        return $this->level === self::LEVEL_ELITE;
    }

    public function isCosysop(): bool
    {
        return $this->level === self::LEVEL_COSYSOP;
    }

    public function isSysop(): bool
    {
        return $this->level === self::LEVEL_SYSOP;
    }

    public function hasMinimumLevel(string $requiredLevel): bool
    {
        $levelOrder = array_flip(self::LEVELS);
        return $levelOrder[$this->level] >= $levelOrder[$requiredLevel];
    }

    public function isStaff(): bool
    {
        return in_array($this->level, [self::LEVEL_COSYSOP, self::LEVEL_SYSOP]);
    }

    // ==========================================
    // TIME & STATISTICS METHODS
    // ==========================================

    public function incrementLoginCount(): void
    {
        $this->increment('total_logins');
    }

    public function updateLastActivity(): void
    {
        $this->update([
            'last_activity_at' => now(),
        ]);
    }

    public function updateLastLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_ip' => $ip,
            'is_online' => true,
        ]);
        $this->incrementLoginCount();
    }

    public function setOffline(): void
    {
        $this->update([
            'is_online' => false,
            'current_node_id' => null,
        ]);
    }

    public function addTimeOnline(int $seconds): void
    {
        $this->increment('total_time_online', $seconds);
        $this->increment('daily_time_used', $seconds);
    }

    public function getRemainingDailyTime(): int
    {
        return max(0, $this->daily_time_limit - $this->daily_time_used);
    }

    public function hasTimeRemaining(): bool
    {
        return $this->getRemainingDailyTime() > 0;
    }

    public function resetDailyTime(): void
    {
        $this->update(['daily_time_used' => 0]);
    }

    public function depositTimeToBank(int $seconds): bool
    {
        return \DB::transaction(function () use ($seconds) {
            // Lock the row to prevent race conditions
            $user = self::where('id', $this->id)->lockForUpdate()->first();
            
            if ($seconds > $user->getRemainingDailyTime()) {
                return false;
            }
            $user->increment('time_bank', $seconds);
            return true;
        });
    }

    public function withdrawTimeFromBank(int $seconds): bool
    {
        return \DB::transaction(function () use ($seconds) {
            // Lock the row to prevent race conditions
            $user = self::where('id', $this->id)->lockForUpdate()->first();
            
            if ($seconds > $user->time_bank) {
                return false;
            }
            $user->decrement('time_bank', $seconds);
            $user->increment('daily_time_limit', $seconds);
            return true;
        });
    }

    // ==========================================
    // CREDITS METHODS
    // ==========================================

    public function addCredits(int $amount): void
    {
        \DB::transaction(function () use ($amount) {
            self::where('id', $this->id)->lockForUpdate()->first();
            $this->increment('credits', $amount);
        });
    }

    public function deductCredits(int $amount): bool
    {
        return \DB::transaction(function () use ($amount) {
            // Lock the row to prevent race conditions (double-spend)
            $user = self::where('id', $this->id)->lockForUpdate()->first();
            
            if ($amount > $user->credits) {
                return false;
            }
            $user->decrement('credits', $amount);
            // Update local instance
            $this->credits = $user->credits - $amount;
            return true;
        });
    }

    public function hasCredits(int $amount): bool
    {
        return $this->credits >= $amount;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    public function scopeHuman($query)
    {
        return $query->where('is_bot', false);
    }

    public function scopeBots($query)
    {
        return $query->where('is_bot', true);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeMinimumLevel($query, string $level)
    {
        $levels = array_slice(self::LEVELS, array_search($level, self::LEVELS));
        return $query->whereIn('level', $levels);
    }

    public function scopeLastCallers($query, int $count = 10)
    {
        return $query->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->limit($count);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    public function getDisplayName(): string
    {
        return $this->handle;
    }

    public function getFormattedTimeOnline(): string
    {
        $seconds = $this->total_time_online;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%dh %dm', $hours, $minutes);
    }

    public function getUnreadPrivateMessagesCount(): int
    {
        return $this->privateMessagesReceived()->where('is_read', false)->count();
    }
}
