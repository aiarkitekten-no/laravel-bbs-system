<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Message;
use App\Models\MessageThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * Get all active categories
     */
    public function categories(): JsonResponse
    {
        $categories = Category::active()
            ->ordered()
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'slug' => $cat->slug,
                'name' => $cat->name,
                'description' => $cat->description,
                'message_count' => $cat->message_count,
                'story_count' => $cat->story_count,
            ]);

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Get threads in a category
     */
    public function threads(Request $request, int $categoryId): JsonResponse
    {
        $category = Category::findOrFail($categoryId);

        $threads = MessageThread::with(['user:id,handle', 'lastMessage.user:id,handle'])
            ->inCategory($categoryId)
            ->orderedByActivity()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
            ],
            'threads' => $threads->items(),
            'pagination' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'total' => $threads->total(),
            ],
        ]);
    }

    /**
     * Create a new thread
     */
    public function createThread(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'subject' => 'required|string|min:3|max:255',
            'body' => 'required|string|min:10|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $category = Category::findOrFail($request->category_id);

        // Check if category is active
        if (!$category->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('messages.category_inactive'),
            ], 403);
        }

        $thread = MessageThread::createThread(
            $category,
            $user,
            $request->subject,
            $request->body
        );

        return response()->json([
            'success' => true,
            'message' => __('messages.thread_created'),
            'thread' => $thread->load('user:id,handle'),
        ], 201);
    }

    /**
     * Get messages in a thread
     */
    public function messages(Request $request, int $threadId): JsonResponse
    {
        $thread = MessageThread::with('category')->findOrFail($threadId);
        $thread->incrementViewCount();

        $messages = Message::with(['user:id,handle,level,total_messages,created_at', 'replyTo.user:id,handle'])
            ->inThread($threadId)
            ->orderBy('created_at')
            ->paginate($request->get('per_page', 25));

        return response()->json([
            'success' => true,
            'thread' => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'is_sticky' => $thread->is_sticky,
                'is_locked' => $thread->is_locked,
                'view_count' => $thread->view_count,
                'reply_count' => $thread->reply_count,
                'category' => [
                    'id' => $thread->category->id,
                    'name' => $thread->category->name,
                ],
            ],
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
     * Reply to a thread
     */
    public function reply(Request $request, int $threadId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|min:3|max:10000',
            'reply_to_id' => 'nullable|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $thread = MessageThread::findOrFail($threadId);
        $user = $request->user();

        // Check if thread is locked
        if (!$thread->canReply($user)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.thread_locked'),
            ], 403);
        }

        $replyTo = $request->reply_to_id ? Message::find($request->reply_to_id) : null;

        $message = Message::reply(
            $thread,
            $user,
            $request->body,
            $replyTo
        );

        return response()->json([
            'success' => true,
            'message' => __('messages.reply_posted'),
            'reply' => $message->load('user:id,handle'),
        ], 201);
    }

    /**
     * Get quote text for a message
     */
    public function quote(int $messageId): JsonResponse
    {
        $message = Message::with('user:id,handle')->findOrFail($messageId);

        return response()->json([
            'success' => true,
            'quote' => $message->getQuoteText(),
        ]);
    }

    /**
     * Search messages
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3|max:100',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $results = Message::searchMessages(
            $request->query,
            $request->category_id,
            50
        );

        return response()->json([
            'success' => true,
            'query' => $request->query,
            'results' => $results->map(fn($msg) => [
                'id' => $msg->id,
                'body' => substr($msg->body, 0, 200),
                'created_at' => $msg->created_at,
                'user' => [
                    'id' => $msg->user->id,
                    'handle' => $msg->user->handle,
                ],
                'thread' => [
                    'id' => $msg->thread->id,
                    'subject' => $msg->thread->subject,
                    'category' => $msg->thread->category->name,
                ],
            ]),
            'count' => $results->count(),
        ]);
    }

    /**
     * Get new messages since a date
     */
    public function newSince(Request $request): JsonResponse
    {
        $user = $request->user();
        $since = $user->last_login_at ?? now()->subDays(7);

        $threads = MessageThread::with(['category', 'lastMessage.user:id,handle'])
            ->withNewMessages($since)
            ->orderedByActivity()
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'since' => $since->toIso8601String(),
            'threads' => $threads->map(fn($t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'category' => $t->category->name,
                'reply_count' => $t->reply_count,
                'last_message_at' => $t->last_message_at,
                'last_message_by' => $t->lastMessage?->user?->handle,
            ]),
            'count' => $threads->count(),
        ]);
    }

    /**
     * Lock/unlock thread (SYSOP/COSYSOP only)
     */
    public function toggleLock(Request $request, int $threadId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized'),
            ], 403);
        }

        $thread = MessageThread::findOrFail($threadId);

        if ($thread->is_locked) {
            $thread->unlock();
            $message = __('messages.thread_unlocked');
        } else {
            $thread->lock();
            $message = __('messages.thread_locked_by_staff');
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_locked' => $thread->is_locked,
        ]);
    }

    /**
     * Toggle sticky (SYSOP/COSYSOP only)
     */
    public function toggleSticky(Request $request, int $threadId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized'),
            ], 403);
        }

        $thread = MessageThread::findOrFail($threadId);
        $thread->toggleSticky();

        return response()->json([
            'success' => true,
            'message' => $thread->is_sticky ? __('messages.thread_pinned') : __('messages.thread_unpinned'),
            'is_sticky' => $thread->is_sticky,
        ]);
    }

    /**
     * Delete message (own message or SYSOP/COSYSOP)
     */
    public function deleteMessage(Request $request, int $messageId): JsonResponse
    {
        $user = $request->user();
        $message = Message::findOrFail($messageId);

        // Check permissions
        if ($message->user_id !== $user->id && !$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized'),
            ], 403);
        }

        // Don't allow deleting first message (would orphan thread)
        if ($message->isFirstInThread()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.cannot_delete_first'),
            ], 403);
        }

        $message->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.message_deleted'),
        ]);
    }
}
