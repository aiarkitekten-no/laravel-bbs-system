<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeBank;
use App\Models\UserClub;
use App\Models\UserAward;
use App\Models\GraffitiWall;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SocialController extends Controller
{
    // ==========================================
    // TIME BANK
    // ==========================================

    public function timeBankStatus(): JsonResponse
    {
        $user = Auth::user();
        $timeBank = TimeBank::getOrCreate($user);

        return response()->json([
            'saved_minutes' => $timeBank->saved_minutes,
            'max_minutes' => $timeBank->max_save_minutes,
            'available_today' => $user->time_limit_minutes ?? 60,
        ]);
    }

    public function timeBankDeposit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'minutes' => 'required|integer|min:1|max:60',
        ]);

        $user = Auth::user();
        $timeBank = TimeBank::getOrCreate($user);

        if (!$timeBank->canDeposit($validated['minutes'])) {
            return response()->json([
                'error' => __('timebank.max_reached'),
            ], 400);
        }

        $timeBank->deposit($validated['minutes']);

        return response()->json([
            'message' => __('timebank.deposited', ['minutes' => $validated['minutes']]),
            'saved_minutes' => $timeBank->saved_minutes,
        ]);
    }

    public function timeBankWithdraw(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'minutes' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $timeBank = TimeBank::getOrCreate($user);

        $withdrawn = $timeBank->withdraw($validated['minutes']);

        if ($withdrawn === 0) {
            return response()->json([
                'error' => __('timebank.empty'),
            ], 400);
        }

        return response()->json([
            'message' => __('timebank.withdrawn', ['minutes' => $withdrawn]),
            'withdrawn' => $withdrawn,
            'saved_minutes' => $timeBank->saved_minutes,
        ]);
    }

    // ==========================================
    // USER CLUBS
    // ==========================================

    public function clubs(): JsonResponse
    {
        $clubs = UserClub::with('founder:id,handle')
            ->active()
            ->public()
            ->orderByDesc('member_count')
            ->paginate(20);

        return response()->json($clubs);
    }

    public function showClub(int $id): JsonResponse
    {
        $club = UserClub::with(['founder:id,handle', 'members:id,handle'])
            ->findOrFail($id);

        $isMember = Auth::check() ? $club->isMember(Auth::user()) : false;

        return response()->json([
            'data' => $club,
            'is_member' => $isMember,
        ]);
    }

    public function createClub(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:user_clubs,name',
            'description' => 'nullable|string|max:1000',
            'logo_ansi' => 'nullable|string',
            'is_public' => 'boolean',
            'max_members' => 'nullable|integer|min:2|max:500',
        ]);

        $club = UserClub::create([
            'founder_id' => Auth::id(),
            'member_count' => 1,
            ...$validated,
        ]);

        // Add founder as first member
        $club->members()->attach(Auth::id(), [
            'role' => 'founder',
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => __('clubs.created'),
            'data' => $club,
        ], 201);
    }

    public function joinClub(int $id): JsonResponse
    {
        $club = UserClub::findOrFail($id);
        $user = Auth::user();

        if (!$club->is_public) {
            return response()->json([
                'error' => __('clubs.private'),
            ], 403);
        }

        if (!$club->addMember($user)) {
            return response()->json([
                'error' => __('clubs.already_member_or_full'),
            ], 400);
        }

        return response()->json([
            'message' => __('clubs.joined'),
        ]);
    }

    public function leaveClub(int $id): JsonResponse
    {
        $club = UserClub::findOrFail($id);
        $user = Auth::user();

        if ($club->isFounder($user)) {
            return response()->json([
                'error' => __('clubs.founder_cannot_leave'),
            ], 400);
        }

        if (!$club->removeMember($user)) {
            return response()->json([
                'error' => __('clubs.not_member'),
            ], 400);
        }

        return response()->json([
            'message' => __('clubs.left'),
        ]);
    }

    public function myClubs(): JsonResponse
    {
        $user = Auth::user();

        $clubs = $user->clubs()
            ->with('founder:id,handle')
            ->get();

        return response()->json(['data' => $clubs]);
    }

    // ==========================================
    // USER AWARDS
    // ==========================================

    public function awards(Request $request): JsonResponse
    {
        $query = UserAward::with('user:id,handle');

        if ($request->has('month')) {
            $query->forMonth($request->month);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        $awards = $query->latest('award_month')->paginate(20);

        return response()->json($awards);
    }

    public function myAwards(): JsonResponse
    {
        $awards = UserAward::where('user_id', Auth::id())
            ->latest('award_month')
            ->get();

        return response()->json(['data' => $awards]);
    }

    public function awardTypes(): JsonResponse
    {
        return response()->json([
            'data' => UserAward::AWARD_TYPES,
        ]);
    }

    public function grantAward(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'award_type' => 'required|string|in:' . implode(',', array_keys(UserAward::AWARD_TYPES)),
            'month' => 'required|date_format:Y-m',
        ]);

        $user = \App\Models\User::findOrFail($validated['user_id']);
        $award = UserAward::grantAward($user, $validated['award_type'], $validated['month'] . '-01');

        // Award credits to user
        $user->increment('credits', $award->points);

        return response()->json([
            'message' => __('awards.granted'),
            'data' => $award,
        ], 201);
    }

    // ==========================================
    // GRAFFITI WALL
    // ==========================================

    public function graffitiWall(): JsonResponse
    {
        $graffiti = GraffitiWall::with('user:id,handle')
            ->approved()
            ->recent(100)
            ->get();

        return response()->json(['data' => $graffiti]);
    }

    public function addGraffiti(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:80',
            'color' => 'nullable|string|in:' . implode(',', array_keys(GraffitiWall::COLORS)),
        ]);

        $graffiti = GraffitiWall::create([
            'user_id' => Auth::id(),
            'is_approved' => Auth::user()->isStaff(),
            ...$validated,
        ]);

        return response()->json([
            'message' => Auth::user()->isStaff() 
                ? __('graffiti.added') 
                : __('graffiti.pending_approval'),
            'data' => $graffiti,
        ], 201);
    }

    public function pendingGraffiti(): JsonResponse
    {
        $graffiti = GraffitiWall::with('user:id,handle')
            ->pending()
            ->latest()
            ->paginate(20);

        return response()->json($graffiti);
    }

    public function approveGraffiti(int $id): JsonResponse
    {
        $graffiti = GraffitiWall::findOrFail($id);
        $graffiti->is_approved = true;
        $graffiti->save();

        return response()->json([
            'message' => __('graffiti.approved'),
        ]);
    }

    public function rejectGraffiti(int $id): JsonResponse
    {
        GraffitiWall::findOrFail($id)->delete();

        return response()->json([
            'message' => __('graffiti.rejected'),
        ]);
    }

    // ==========================================
    // BIRTHDAYS
    // ==========================================

    public function todaysBirthdays(): JsonResponse
    {
        $today = now();

        $users = \App\Models\User::whereMonth('birthdate', $today->month)
            ->whereDay('birthdate', $today->day)
            ->select(['id', 'handle', 'birthdate'])
            ->get();

        return response()->json(['data' => $users]);
    }

    public function upcomingBirthdays(): JsonResponse
    {
        $today = now();
        $nextWeek = now()->addWeek();

        $users = \App\Models\User::whereNotNull('birthdate')
            ->whereRaw('DATE_FORMAT(birthdate, "%m-%d") BETWEEN ? AND ?', [
                $today->format('m-d'),
                $nextWeek->format('m-d'),
            ])
            ->select(['id', 'handle', 'birthdate'])
            ->orderByRaw('DATE_FORMAT(birthdate, "%m-%d")')
            ->limit(20)
            ->get();

        return response()->json(['data' => $users]);
    }
}
