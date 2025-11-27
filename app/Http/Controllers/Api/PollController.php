<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PollController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Poll::with('user:id,handle');

        if ($request->boolean('active')) {
            $query->active();
        }

        $polls = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($polls);
    }

    public function show(int $id): JsonResponse
    {
        $poll = Poll::with('user:id,handle')->findOrFail($id);
        $user = Auth::user();

        $data = [
            'poll' => $poll,
            'has_voted' => $user ? $poll->hasUserVoted($user) : false,
            'user_votes' => $user ? $poll->getUserVotes($user) : [],
        ];

        // Include results if allowed
        if ($poll->show_results_before_vote || $data['has_voted'] || $poll->isExpired()) {
            $data['results'] = $poll->getResults();
        }

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'description' => 'nullable|string|max:1000',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:255',
            'is_multiple_choice' => 'boolean',
            'show_results_before_vote' => 'boolean',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $poll = Poll::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return response()->json([
            'message' => __('polls.created'),
            'data' => $poll,
        ], 201);
    }

    public function vote(Request $request, int $id): JsonResponse
    {
        $poll = Poll::findOrFail($id);
        $user = Auth::user();

        if (!$poll->is_active || $poll->isExpired()) {
            return response()->json([
                'error' => __('polls.closed'),
            ], 400);
        }

        if ($poll->hasUserVoted($user)) {
            return response()->json([
                'error' => __('polls.already_voted'),
            ], 400);
        }

        $validated = $request->validate([
            'options' => 'required|array|min:1',
            'options.*' => 'required|integer|min:0',
        ]);

        $success = $poll->vote($user, $validated['options']);

        if (!$success) {
            return response()->json([
                'error' => __('polls.vote_failed'),
            ], 400);
        }

        return response()->json([
            'message' => __('polls.vote_success'),
            'results' => $poll->getResults(),
        ]);
    }

    public function results(int $id): JsonResponse
    {
        $poll = Poll::findOrFail($id);

        return response()->json([
            'poll' => $poll,
            'results' => $poll->getResults(),
            'total_votes' => $poll->total_votes,
        ]);
    }

    public function close(int $id): JsonResponse
    {
        $poll = Poll::findOrFail($id);
        
        if ($poll->user_id !== Auth::id() && !Auth::user()->isStaff()) {
            return response()->json(['error' => __('polls.not_authorized')], 403);
        }

        $poll->is_active = false;
        $poll->save();

        return response()->json([
            'message' => __('polls.closed_success'),
        ]);
    }
}
