<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PrivateMessageController extends Controller
{
    /**
     * Get inbox messages
     */
    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();

        $messages = PrivateMessage::with('sender:id,handle')
            ->where('recipient_id', $user->id)
            ->where('recipient_deleted', false)
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'messages' => $messages->items(),
            'unread_count' => PrivateMessage::where('recipient_id', $user->id)
                ->where('is_read', false)
                ->where('recipient_deleted', false)
                ->count(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Get sent messages
     */
    public function sent(Request $request): JsonResponse
    {
        $user = $request->user();

        $messages = PrivateMessage::with('recipient:id,handle')
            ->where('sender_id', $user->id)
            ->where('sender_deleted', false)
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'messages' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Read a single message
     */
    public function show(Request $request, int $messageId): JsonResponse
    {
        $user = $request->user();

        $message = PrivateMessage::with(['sender:id,handle', 'recipient:id,handle'])
            ->where('id', $messageId)
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('recipient_id', $user->id);
            })
            ->firstOrFail();

        // Mark as read if recipient
        if ($message->recipient_id === $user->id && !$message->is_read) {
            $message->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Send a new private message
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipient' => 'required|string|exists:users,handle',
            'subject' => 'required|string|min:1|max:255',
            'body' => 'required|string|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('pm.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $sender = $request->user();
        $recipient = User::where('handle', $request->recipient)->first();

        if (!$recipient) {
            return response()->json([
                'success' => false,
                'message' => __('pm.recipient_not_found'),
            ], 404);
        }

        // Can't send to yourself
        if ($recipient->id === $sender->id) {
            return response()->json([
                'success' => false,
                'message' => __('pm.cannot_send_to_self'),
            ], 400);
        }

        $message = PrivateMessage::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('pm.message_sent'),
            'pm' => $message->load('recipient:id,handle'),
        ], 201);
    }

    /**
     * Reply to a private message
     */
    public function reply(Request $request, int $messageId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('pm.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $original = PrivateMessage::findOrFail($messageId);

        // Must be recipient of original
        if ($original->recipient_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => __('pm.unauthorized'),
            ], 403);
        }

        // Create reply
        $subject = str_starts_with($original->subject, 'Re: ')
            ? $original->subject
            : 'Re: ' . $original->subject;

        $reply = PrivateMessage::create([
            'sender_id' => $user->id,
            'recipient_id' => $original->sender_id,
            'subject' => $subject,
            'body' => $request->body,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('pm.reply_sent'),
            'pm' => $reply->load('recipient:id,handle'),
        ], 201);
    }

    /**
     * Delete a message (soft delete for user)
     */
    public function delete(Request $request, int $messageId): JsonResponse
    {
        $user = $request->user();
        $message = PrivateMessage::findOrFail($messageId);

        if ($message->sender_id === $user->id) {
            $message->update(['sender_deleted' => true]);
        } elseif ($message->recipient_id === $user->id) {
            $message->update(['recipient_deleted' => true]);
        } else {
            return response()->json([
                'success' => false,
                'message' => __('pm.unauthorized'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => __('pm.message_deleted'),
        ]);
    }

    /**
     * Get unread count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = PrivateMessage::where('recipient_id', $user->id)
            ->where('is_read', false)
            ->where('recipient_deleted', false)
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark all as read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        PrivateMessage::where('recipient_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => __('pm.all_marked_read'),
        ]);
    }
}
