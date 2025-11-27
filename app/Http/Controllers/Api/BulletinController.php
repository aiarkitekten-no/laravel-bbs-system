<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\BbsLink;
use App\Models\LogoffQuote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BulletinController extends Controller
{
    // ==========================================
    // BULLETINS
    // ==========================================

    public function index(): JsonResponse
    {
        $bulletins = Bulletin::with('user:id,handle')
            ->active()
            ->byPriority()
            ->paginate(10);

        return response()->json($bulletins);
    }

    public function show(int $id): JsonResponse
    {
        $bulletin = Bulletin::with('user:id,handle')->findOrFail($id);
        $bulletin->incrementViews();

        return response()->json(['data' => $bulletin]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'priority' => 'nullable|integer|min:1|max:4',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
        ]);

        $bulletin = Bulletin::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return response()->json([
            'message' => __('bulletins.created'),
            'data' => $bulletin,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $bulletin = Bulletin::findOrFail($id);

        $validated = $request->validate([
            'title' => 'string|max:255',
            'content' => 'string',
            'priority' => 'integer|min:1|max:4',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        $bulletin->update($validated);

        return response()->json([
            'message' => __('bulletins.updated'),
            'data' => $bulletin,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $bulletin = Bulletin::findOrFail($id);
        $bulletin->delete();

        return response()->json([
            'message' => __('bulletins.deleted'),
        ]);
    }

    // ==========================================
    // BBS LINKS
    // ==========================================

    public function bbsList(): JsonResponse
    {
        $links = BbsLink::active()
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $links]);
    }

    public function storeBbsLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'url' => 'nullable|url',
            'telnet_address' => 'nullable|string|max:255',
            'sysop_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'software' => 'nullable|string|max:255',
        ]);

        $link = BbsLink::create($validated);

        return response()->json([
            'message' => __('bbs.link_added'),
            'data' => $link,
        ], 201);
    }

    public function updateBbsLink(Request $request, int $id): JsonResponse
    {
        $link = BbsLink::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string|max:1000',
            'url' => 'nullable|url',
            'telnet_address' => 'nullable|string|max:255',
            'sysop_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'software' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        $link->update($validated);

        return response()->json([
            'message' => __('bbs.link_updated'),
            'data' => $link,
        ]);
    }

    public function destroyBbsLink(int $id): JsonResponse
    {
        BbsLink::findOrFail($id)->delete();

        return response()->json([
            'message' => __('bbs.link_deleted'),
        ]);
    }

    // ==========================================
    // LOGOFF QUOTES
    // ==========================================

    public function randomQuote(): JsonResponse
    {
        $quote = LogoffQuote::getRandom();

        if ($quote) {
            $quote->incrementShown();
        }

        return response()->json([
            'data' => $quote,
        ]);
    }

    public function quotes(): JsonResponse
    {
        $quotes = LogoffQuote::with('user:id,handle')
            ->approved()
            ->latest()
            ->paginate(20);

        return response()->json($quotes);
    }

    public function storeQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quote' => 'required|string|max:500',
            'author' => 'nullable|string|max:255',
        ]);

        $quote = LogoffQuote::create([
            'user_id' => Auth::id(),
            'is_approved' => Auth::user()->isStaff(),
            ...$validated,
        ]);

        return response()->json([
            'message' => Auth::user()->isStaff() 
                ? __('quotes.added') 
                : __('quotes.pending_approval'),
            'data' => $quote,
        ], 201);
    }

    public function pendingQuotes(): JsonResponse
    {
        $quotes = LogoffQuote::with('user:id,handle')
            ->pending()
            ->latest()
            ->paginate(20);

        return response()->json($quotes);
    }

    public function approveQuote(int $id): JsonResponse
    {
        $quote = LogoffQuote::findOrFail($id);
        $quote->is_approved = true;
        $quote->save();

        return response()->json([
            'message' => __('quotes.approved'),
        ]);
    }

    public function rejectQuote(int $id): JsonResponse
    {
        LogoffQuote::findOrFail($id)->delete();

        return response()->json([
            'message' => __('quotes.rejected'),
        ]);
    }
}
