<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\NodeChatMessage;
use App\Models\User;
use App\Models\UserAutoReply;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NodeController extends Controller
{
    protected AiChatService $aiChatService;

    public function __construct(AiChatService $aiChatService)
    {
        $this->aiChatService = $aiChatService;
    }

    /**
     * Get all nodes status
     */
    public function index(): JsonResponse
    {
        $nodes = Node::with('currentUser')
            ->orderBy('node_number')
            ->get()
            ->map(function ($node) {
                return $this->formatNodeResponse($node);
            });

        return response()->json([
            'success' => true,
            'data' => [
                'nodes' => $nodes,
                'total' => $nodes->count(),
                'available' => $nodes->where('status', 'ONLINE')->whereNull('user')->count(),
                'occupied' => $nodes->whereNotNull('user')->count(),
            ],
        ]);
    }

    /**
     * Get specific node
     */
    public function show(int $nodeNumber): JsonResponse
    {
        $node = Node::where('node_number', $nodeNumber)->first();

        if (!$node) {
            return response()->json([
                'success' => false,
                'message' => __('node.not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatNodeResponse($node),
        ]);
    }

    /**
     * Who's Online - Shows ALL nodes (active and inactive)
     */
    public function whosOnline(): JsonResponse
    {
        $allNodes = Node::with('currentUser')
            ->orderBy('node_number')
            ->get();

        $nodes = $allNodes->map(function ($node) {
            if ($node->current_user_id && $node->currentUser) {
                return [
                    'node_number' => $node->node_number,
                    'status' => 'active',
                    'handle' => $node->currentUser->handle,
                    'level' => $node->currentUser->level,
                    'location' => $node->currentUser->location ?? 'Unknown',
                    'activity' => $node->current_activity ?? 'Browsing',
                    'connected_at' => $node->user_connected_at?->toIso8601String(),
                    'time_online' => $node->user_connected_at 
                        ? $node->user_connected_at->diffForHumans(null, true) 
                        : null,
                    'is_bot' => $node->currentUser->is_bot ?? false,
                ];
            } else {
                return [
                    'node_number' => $node->node_number,
                    'status' => 'inactive',
                    'handle' => null,
                    'level' => null,
                    'location' => null,
                    'activity' => 'Waiting for caller...',
                    'connected_at' => null,
                    'time_online' => null,
                    'is_bot' => false,
                ];
            }
        });

        $onlineCount = $nodes->where('status', 'active')->count();

        return response()->json([
            'success' => true,
            'data' => $nodes,
            'online_count' => $onlineCount,
            'total_nodes' => $nodes->count(),
        ]);
    }

    /**
     * Last 10 Callers
     */
    public function lastCallers(int $count = 10): JsonResponse
    {
        $count = min($count, 50); // Max 50

        $users = User::human()
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->limit($count)
            ->get()
            ->map(function ($user) {
                return [
                    'handle' => $user->handle,
                    'level' => $user->level,
                    'location' => $user->location,
                    'last_login' => $user->last_login_at->toIso8601String(),
                    'last_login_relative' => $user->last_login_at->diffForHumans(),
                    'total_logins' => $user->total_logins,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Update current activity
     */
    public function updateActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $node = $user->currentNode;

        if (!$node) {
            return response()->json([
                'success' => false,
                'message' => __('node.not_on_node'),
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'activity' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $node->updateActivity($request->activity);

        return response()->json([
            'success' => true,
            'message' => __('node.activity_updated'),
        ]);
    }

    /**
     * Send chat message
     */
    public function sendChat(Request $request): JsonResponse
    {
        $user = $request->user();
        $fromNode = $user->currentNode;

        if (!$fromNode) {
            return response()->json([
                'success' => false,
                'message' => __('node.not_on_node'),
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:500',
            'to_node' => 'nullable|integer|exists:nodes,node_number',
            'to_user' => 'nullable|string|exists:users,handle',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $toNode = null;
        $toUser = null;

        if ($request->to_node) {
            $toNode = Node::where('node_number', $request->to_node)->first();
            $toUser = $toNode->currentUser;
        } elseif ($request->to_user) {
            $toUser = User::where('handle', $request->to_user)->first();
            $toNode = $toUser?->currentNode;
        }

        // Check for auto-reply
        $autoReplyMessage = null;
        if ($toUser) {
            $autoReply = UserAutoReply::getReplyForUser($toUser);
            if ($autoReply) {
                $autoReplyMessage = $autoReply;
            }
        }

        $message = NodeChatMessage::sendMessage(
            $fromNode,
            $toNode,
            $user,
            $toUser,
            $request->message
        );

        $response = [
            'success' => true,
            'message' => $toNode ? __('node.message_sent') : __('node.message_broadcast'),
            'data' => [
                'id' => $message->id,
                'is_broadcast' => $message->isBroadcast(),
            ],
        ];

        if ($autoReplyMessage) {
            $response['auto_reply'] = [
                'from' => $toUser->handle,
                'message' => $autoReplyMessage,
            ];
        }

        // Queue AI response if messaging an AI user
        if ($toUser && $toUser->is_bot) {
            $this->aiChatService->queueResponse($toUser, $user, $request->message, $fromNode);
            $response['ai_will_respond'] = true;
        }

        return response()->json($response);
    }

    /**
     * Get unread chat messages
     */
    public function getChat(Request $request): JsonResponse
    {
        $user = $request->user();
        $node = $user->currentNode;

        if (!$node) {
            return response()->json([
                'success' => false,
                'message' => __('node.not_on_node'),
            ], 400);
        }

        $messages = NodeChatMessage::getUnreadForNode($node->id);

        // Mark as read
        NodeChatMessage::whereIn('id', $messages->pluck('id'))->update(['is_read' => true]);

        $formatted = $messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'from_node' => $msg->fromNode->node_number,
                'from_user' => $msg->fromUser->handle,
                'message' => $msg->message,
                'is_page' => $msg->is_page,
                'is_broadcast' => $msg->isBroadcast(),
                'sent_at' => $msg->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $formatted->count(),
                'messages' => $formatted,
            ],
        ]);
    }

    /**
     * Page a user (urgent message)
     */
    public function pageUser(Request $request): JsonResponse
    {
        $user = $request->user();
        $fromNode = $user->currentNode;

        if (!$fromNode) {
            return response()->json([
                'success' => false,
                'message' => __('node.not_on_node'),
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'handle' => 'required|string|exists:users,handle',
            'message' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $toUser = User::where('handle', $request->handle)->first();

        if (!$toUser->is_online) {
            return response()->json([
                'success' => false,
                'message' => __('node.user_not_online'),
            ], 400);
        }

        $message = NodeChatMessage::pageUser($fromNode, $user, $toUser, $request->message);

        return response()->json([
            'success' => true,
            'message' => __('node.page_sent', ['handle' => $toUser->handle]),
            'data' => [
                'id' => $message->id,
            ],
        ]);
    }

    /**
     * Set auto-reply
     */
    public function setAutoReply(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'message' => 'required_if:enabled,true|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $autoReply = $user->autoReply()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'is_enabled' => $request->enabled,
                'message' => $request->message,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $request->enabled 
                ? __('node.auto_reply_enabled') 
                : __('node.auto_reply_disabled'),
            'data' => [
                'enabled' => $autoReply->is_enabled,
                'message' => $autoReply->message,
            ],
        ]);
    }

    /**
     * Format node response
     */
    private function formatNodeResponse(Node $node): array
    {
        return [
            'node_number' => $node->node_number,
            'status' => $node->status,
            'user' => $node->currentUser ? [
                'handle' => $node->currentUser->handle,
                'level' => $node->currentUser->level,
                'is_bot' => $node->currentUser->is_bot,
            ] : null,
            'activity' => $node->current_activity,
            'connected_at' => $node->user_connected_at?->toIso8601String(),
            'last_activity' => $node->last_activity_at?->toIso8601String(),
        ];
    }
}
