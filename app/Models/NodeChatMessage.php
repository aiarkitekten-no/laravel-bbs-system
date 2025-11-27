<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeChatMessage extends Model
{
    protected $fillable = [
        'from_node_id',
        'to_node_id',
        'from_user_id',
        'to_user_id',
        'message',
        'is_read',
        'is_page',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_page' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'to_node_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForNode($query, int $nodeId)
    {
        return $query->where(function ($q) use ($nodeId) {
            $q->where('to_node_id', $nodeId)
              ->orWhereNull('to_node_id'); // Broadcast messages
        });
    }

    public function scopePages($query)
    {
        return $query->where('is_page', true);
    }

    public function scopeBroadcast($query)
    {
        return $query->whereNull('to_node_id');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    public function isBroadcast(): bool
    {
        return $this->to_node_id === null;
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    public static function sendMessage(
        Node $fromNode,
        ?Node $toNode,
        User $fromUser,
        ?User $toUser,
        string $message,
        bool $isPage = false
    ): self {
        return self::create([
            'from_node_id' => $fromNode->id,
            'to_node_id' => $toNode?->id,
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser?->id,
            'message' => $message,
            'is_page' => $isPage,
        ]);
    }

    public static function broadcast(Node $fromNode, User $fromUser, string $message): self
    {
        return self::sendMessage($fromNode, null, $fromUser, null, $message);
    }

    public static function pageUser(Node $fromNode, User $fromUser, User $toUser, string $message): self
    {
        $toNode = Node::where('current_user_id', $toUser->id)->first();
        return self::sendMessage($fromNode, $toNode, $fromUser, $toUser, $message, true);
    }

    public static function getUnreadForNode(int $nodeId): \Illuminate\Database\Eloquent\Collection
    {
        return self::with(['fromUser', 'fromNode'])
            ->forNode($nodeId)
            ->unread()
            ->orderBy('created_at')
            ->get();
    }
}
