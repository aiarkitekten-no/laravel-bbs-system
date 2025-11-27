<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Story;
use App\Models\StoryComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoryController extends Controller
{
    /**
     * Get today's story
     */
    public function today(): JsonResponse
    {
        $story = Story::with('category')
            ->today()
            ->first();

        if (!$story) {
            return response()->json([
                'success' => false,
                'message' => __('stories.no_story_today'),
            ], 404);
        }

        $story->incrementViewCount();

        return response()->json([
            'success' => true,
            'story' => $this->formatStory($story),
        ]);
    }

    /**
     * Get story list (recent or by category)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Story::with('category');

        // Filter by category
        if ($request->has('category_id')) {
            $query->inCategory($request->category_id);
        }

        // Sort options
        $sort = $request->get('sort', 'date');
        switch ($sort) {
            case 'score':
                $query->orderedByScore();
                break;
            case 'views':
                $query->mostViewed();
                break;
            default:
                $query->orderedByDate();
        }

        $stories = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'stories' => collect($stories->items())->map(fn($s) => $this->formatStory($s, false)),
            'pagination' => [
                'current_page' => $stories->currentPage(),
                'last_page' => $stories->lastPage(),
                'per_page' => $stories->perPage(),
                'total' => $stories->total(),
            ],
        ]);
    }

    /**
     * Get a single story
     */
    public function show(Request $request, int $storyId): JsonResponse
    {
        $story = Story::with('category')->findOrFail($storyId);
        $story->incrementViewCount();

        $user = $request->user();
        $userVote = $user ? $story->getUserVote($user) : null;
        $isFavorited = $user ? $story->isFavoritedBy($user) : false;

        return response()->json([
            'success' => true,
            'story' => $this->formatStory($story),
            'user_vote' => $userVote,
            'is_favorited' => $isFavorited,
        ]);
    }

    /**
     * Upvote a story
     */
    public function upvote(Request $request, int $storyId): JsonResponse
    {
        $story = Story::findOrFail($storyId);
        $user = $request->user();

        $story->upvote($user);

        return response()->json([
            'success' => true,
            'message' => __('stories.upvoted'),
            'upvotes' => $story->fresh()->upvotes,
            'downvotes' => $story->fresh()->downvotes,
            'score' => $story->fresh()->score,
        ]);
    }

    /**
     * Downvote a story
     */
    public function downvote(Request $request, int $storyId): JsonResponse
    {
        $story = Story::findOrFail($storyId);
        $user = $request->user();

        $story->downvote($user);

        return response()->json([
            'success' => true,
            'message' => __('stories.downvoted'),
            'upvotes' => $story->fresh()->upvotes,
            'downvotes' => $story->fresh()->downvotes,
            'score' => $story->fresh()->score,
        ]);
    }

    /**
     * Toggle favorite
     */
    public function toggleFavorite(Request $request, int $storyId): JsonResponse
    {
        $story = Story::findOrFail($storyId);
        $user = $request->user();

        $isFavorited = $story->toggleFavorite($user);

        return response()->json([
            'success' => true,
            'message' => $isFavorited ? __('stories.added_to_favorites') : __('stories.removed_from_favorites'),
            'is_favorited' => $isFavorited,
        ]);
    }

    /**
     * Get user's favorite stories
     */
    public function favorites(Request $request): JsonResponse
    {
        $user = $request->user();

        $favorites = $user->storyFavorites()
            ->with('story.category')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'stories' => collect($favorites->items())->map(fn($f) => $this->formatStory($f->story, false)),
            'pagination' => [
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'per_page' => $favorites->perPage(),
                'total' => $favorites->total(),
            ],
        ]);
    }

    /**
     * Get comments for a story
     */
    public function comments(Request $request, int $storyId): JsonResponse
    {
        $story = Story::findOrFail($storyId);

        $comments = StoryComment::with(['user:id,handle', 'replies.user:id,handle'])
            ->forStory($storyId)
            ->topLevel()
            ->orderBy('created_at')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'story_id' => $storyId,
            'comments' => $comments->items(),
            'comment_count' => $story->comment_count,
            'pagination' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    /**
     * Add a comment to a story
     */
    public function addComment(Request $request, int $storyId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|min:1|max:2000',
            'parent_id' => 'nullable|exists:story_comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('stories.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $story = Story::findOrFail($storyId);
        $user = $request->user();
        $parent = $request->parent_id ? StoryComment::find($request->parent_id) : null;

        $comment = StoryComment::addComment($story, $user, $request->body, $parent);

        return response()->json([
            'success' => true,
            'message' => __('stories.comment_added'),
            'comment' => $comment->load('user:id,handle'),
        ], 201);
    }

    /**
     * Delete a comment
     */
    public function deleteComment(Request $request, int $commentId): JsonResponse
    {
        $user = $request->user();
        $comment = StoryComment::findOrFail($commentId);

        // Check permissions
        if ($comment->user_id !== $user->id && !$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('stories.unauthorized'),
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => __('stories.comment_deleted'),
        ]);
    }

    /**
     * Get top rated stories
     */
    public function topRated(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        $days = $request->get('days', 30);

        $stories = Story::with('category')
            ->where('story_date', '>=', now()->subDays($days))
            ->orderedByScore()
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'stories' => $stories->map(fn($s) => $this->formatStory($s, false)),
            'period_days' => $days,
        ]);
    }

    /**
     * Get story archive (by date)
     */
    public function archive(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month');

        $query = Story::with('category')
            ->whereYear('story_date', $year);

        if ($month) {
            $query->whereMonth('story_date', $month);
        }

        $stories = $query->orderedByDate()
            ->paginate($request->get('per_page', 30));

        return response()->json([
            'success' => true,
            'year' => $year,
            'month' => $month,
            'stories' => collect($stories->items())->map(fn($s) => $this->formatStory($s, false)),
            'pagination' => [
                'current_page' => $stories->currentPage(),
                'last_page' => $stories->lastPage(),
                'per_page' => $stories->perPage(),
                'total' => $stories->total(),
            ],
        ]);
    }

    /**
     * Helper: Format story for response
     */
    private function formatStory(Story $story, bool $fullContent = true): array
    {
        $data = [
            'id' => $story->id,
            'title' => $story->title,
            'category' => $story->category ? [
                'id' => $story->category->id,
                'name' => $story->category->name,
            ] : null,
            'story_date' => $story->story_date?->format('Y-m-d'),
            'upvotes' => $story->upvotes,
            'downvotes' => $story->downvotes,
            'score' => $story->score,
            'vote_percentage' => $story->vote_percentage,
            'view_count' => $story->view_count,
            'comment_count' => $story->comment_count,
            'created_at' => $story->created_at,
        ];

        if ($fullContent) {
            $data['content'] = $story->content;
            $data['ai_model'] = $story->ai_model;
        } else {
            // Preview only (first 200 chars)
            $data['preview'] = substr($story->content, 0, 200) . (strlen($story->content) > 200 ? '...' : '');
        }

        return $data;
    }
}
