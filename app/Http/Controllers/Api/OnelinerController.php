<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Oneliner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OnelinerController extends Controller
{
    /**
     * Get recent oneliners
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 20), 100);

        $oneliners = Oneliner::with('user:id,handle')
            ->approved()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'oneliners' => $oneliners->map(fn($o) => [
                'id' => $o->id,
                'content' => $o->content,
                'user' => [
                    'id' => $o->user->id,
                    'handle' => $o->user->handle,
                ],
                'is_ai_generated' => $o->is_ai_generated,
                'created_at' => $o->created_at,
            ]),
        ]);
    }

    /**
     * Post a new oneliner
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:1|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('oneliners.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Check for spam (same user, recent post)
        $recentPost = Oneliner::where('user_id', $user->id)
            ->where('created_at', '>', now()->subMinutes(5))
            ->exists();

        if ($recentPost && !$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('oneliners.rate_limited'),
            ], 429);
        }

        $oneliner = Oneliner::postOneliner($user, $request->content);

        $message = $oneliner->is_approved
            ? __('oneliners.posted')
            : __('oneliners.pending_approval');

        return response()->json([
            'success' => true,
            'message' => $message,
            'oneliner' => [
                'id' => $oneliner->id,
                'content' => $oneliner->content,
                'is_approved' => $oneliner->is_approved,
            ],
        ], 201);
    }

    /**
     * Delete own oneliner
     */
    public function destroy(Request $request, int $onelinerId): JsonResponse
    {
        $user = $request->user();
        $oneliner = Oneliner::findOrFail($onelinerId);

        // Check permissions
        if ($oneliner->user_id !== $user->id && !$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('oneliners.unauthorized'),
            ], 403);
        }

        $oneliner->delete();

        return response()->json([
            'success' => true,
            'message' => __('oneliners.deleted'),
        ]);
    }

    /**
     * Get pending oneliners (SYSOP/COSYSOP only)
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('oneliners.unauthorized'),
            ], 403);
        }

        $pending = Oneliner::with('user:id,handle')
            ->pending()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'pending' => $pending->map(fn($o) => [
                'id' => $o->id,
                'content' => $o->content,
                'user' => [
                    'id' => $o->user->id,
                    'handle' => $o->user->handle,
                ],
                'created_at' => $o->created_at,
            ]),
            'count' => $pending->count(),
        ]);
    }

    /**
     * Approve a oneliner (SYSOP/COSYSOP only)
     */
    public function approve(Request $request, int $onelinerId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('oneliners.unauthorized'),
            ], 403);
        }

        $oneliner = Oneliner::findOrFail($onelinerId);
        $oneliner->approve();

        return response()->json([
            'success' => true,
            'message' => __('oneliners.approved'),
        ]);
    }

    /**
     * Reject a oneliner (SYSOP/COSYSOP only)
     */
    public function reject(Request $request, int $onelinerId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('oneliners.unauthorized'),
            ], 403);
        }

        $oneliner = Oneliner::findOrFail($onelinerId);
        $oneliner->reject();

        return response()->json([
            'success' => true,
            'message' => __('oneliners.rejected'),
        ]);
    }

    /**
     * Get user's own oneliners
     */
    public function myOneliners(Request $request): JsonResponse
    {
        $user = $request->user();

        $oneliners = Oneliner::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'oneliners' => $oneliners->map(fn($o) => [
                'id' => $o->id,
                'content' => $o->content,
                'is_approved' => $o->is_approved,
                'created_at' => $o->created_at,
            ]),
        ]);
    }
}
