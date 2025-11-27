<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    public const STATUS_ONLINE = 'ONLINE';
    public const STATUS_OFFLINE = 'OFFLINE';
    public const STATUS_BUSY = 'BUSY';
    public const STATUS_MAINTENANCE = 'MAINTENANCE';

    protected $fillable = [
        'node_number',
        'status',
        'current_user_id',
        'current_activity',
        'user_connected_at',
        'last_activity_at',
    ];

    protected $casts = [
        'user_connected_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(NodeActivityLog::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(NodeChatMessage::class, 'from_node_id');
    }

    public function receivedChatMessages(): HasMany
    {
        return $this->hasMany(NodeChatMessage::class, 'to_node_id');
    }

    // ==========================================
    // STATUS METHODS
    // ==========================================

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_ONLINE && $this->current_user_id === null;
    }

    public function isBusy(): bool
    {
        return $this->current_user_id !== null;
    }

    public function isOnline(): bool
    {
        return $this->status === self::STATUS_ONLINE;
    }

    public function setOffline(): void
    {
        $this->update([
            'status' => self::STATUS_OFFLINE,
            'current_user_id' => null,
            'current_activity' => null,
            'user_connected_at' => null,
        ]);
    }

    public function setOnline(): void
    {
        $this->update(['status' => self::STATUS_ONLINE]);
    }

    public function setMaintenance(): void
    {
        $this->update([
            'status' => self::STATUS_MAINTENANCE,
            'current_user_id' => null,
            'current_activity' => 'Under maintenance',
        ]);
    }

    // ==========================================
    // USER CONNECTION METHODS
    // ==========================================

    public function assignUser(User $user): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $this->update([
            'current_user_id' => $user->id,
            'current_activity' => __('bbs.logging_in'),
            'user_connected_at' => now(),
            'last_activity_at' => now(),
        ]);

        $user->update([
            'current_node_id' => $this->id,
            'is_online' => true,
        ]);

        // Log the activity
        NodeActivityLog::logActivity(
            $this,
            $user,
            NodeActivityLog::ACTION_LOGIN,
            'User connected to node ' . $this->node_number
        );

        return true;
    }

    public function releaseUser(): void
    {
        if ($this->currentUser) {
            // Calculate time online
            if ($this->user_connected_at) {
                $timeOnline = now()->diffInSeconds($this->user_connected_at);
                $this->currentUser->addTimeOnline($timeOnline);
            }

            // Log the activity
            NodeActivityLog::logActivity(
                $this,
                $this->currentUser,
                NodeActivityLog::ACTION_LOGOUT,
                'User disconnected from node ' . $this->node_number
            );

            $this->currentUser->setOffline();
        }

        $this->update([
            'current_user_id' => null,
            'current_activity' => null,
            'user_connected_at' => null,
        ]);
    }

    public function updateActivity(string $activity): void
    {
        $this->update([
            'current_activity' => $activity,
            'last_activity_at' => now(),
        ]);

        if ($this->currentUser) {
            $this->currentUser->updateLastActivity();
        }
    }

    // ==========================================
    // TIMEOUT CHECK
    // ==========================================

    public function isTimedOut(int $timeoutMinutes = 15): bool
    {
        if (!$this->last_activity_at || !$this->current_user_id) {
            return false;
        }

        return $this->last_activity_at->diffInMinutes(now()) >= $timeoutMinutes;
    }

    public static function releaseTimedOutNodes(int $timeoutMinutes = 15): int
    {
        $count = 0;
        $nodes = self::whereNotNull('current_user_id')->get();

        foreach ($nodes as $node) {
            if ($node->isTimedOut($timeoutMinutes)) {
                $node->releaseUser();
                $count++;
            }
        }

        return $count;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_ONLINE)
            ->whereNull('current_user_id');
    }

    public function scopeOccupied($query)
    {
        return $query->whereNotNull('current_user_id');
    }

    public function scopeOnline($query)
    {
        return $query->where('status', self::STATUS_ONLINE);
    }

    // ==========================================
    // STATIC HELPERS
    // ==========================================

    public static function getFirstAvailable(): ?self
    {
        // Try to get an existing available node first
        $node = self::available()->orderBy('node_number')->first();
        
        if ($node) {
            return $node;
        }
        
        // All nodes are occupied - create a VIP node
        return self::createVipNode();
    }

    /**
     * Create a VIP node when all regular nodes are occupied
     */
    public static function createVipNode(): self
    {
        // Find the highest node number
        $maxNode = self::max('node_number') ?? 6;
        $vipNodeNumber = $maxNode + 1;
        
        // Check if this VIP node already exists
        $existingVip = self::where('node_number', $vipNodeNumber)
            ->whereNull('current_user_id')
            ->first();
            
        if ($existingVip) {
            return $existingVip;
        }
        
        // Create new VIP node
        return self::create([
            'node_number' => $vipNodeNumber,
            'status' => self::STATUS_ONLINE,
            'is_active' => true,
            'description' => 'VIP Node #' . $vipNodeNumber,
        ]);
    }

    public static function getOnlineUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return self::with('currentUser')
            ->occupied()
            ->orderBy('node_number')
            ->get()
            ->pluck('currentUser');
    }

    public static function getNodeStatus(): array
    {
        return self::with('currentUser')
            ->orderBy('node_number')
            ->get()
            ->map(function ($node) {
                return [
                    'node' => $node->node_number,
                    'status' => $node->status,
                    'user' => $node->currentUser?->handle,
                    'activity' => $node->current_activity,
                    'connected_at' => $node->user_connected_at?->toIso8601String(),
                ];
            })
            ->toArray();
    }
}
