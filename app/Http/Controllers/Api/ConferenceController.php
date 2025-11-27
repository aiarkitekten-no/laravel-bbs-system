<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConferenceController extends Controller
{
    /**
     * List all conferences
     */
    public function index(): JsonResponse
    {
        $conferences = [
            ['id' => 1, 'name' => 'Main', 'description' => 'General discussion', 'members' => rand(10, 50)],
            ['id' => 2, 'name' => 'Programming', 'description' => 'Code talk and help', 'members' => rand(5, 30)],
            ['id' => 3, 'name' => 'Retro Computing', 'description' => 'Classic hardware and software', 'members' => rand(8, 25)],
            ['id' => 4, 'name' => 'Off Topic', 'description' => 'Random chatter', 'members' => rand(15, 40)],
            ['id' => 5, 'name' => 'Gaming', 'description' => 'Video games old and new', 'members' => rand(12, 35)],
            ['id' => 6, 'name' => 'Music & Audio', 'description' => 'MODs, SIDs, and more', 'members' => rand(6, 20)],
            ['id' => 7, 'name' => 'ANSI Art', 'description' => 'ASCII and ANSI artwork', 'members' => rand(4, 15)],
            ['id' => 8, 'name' => 'Sysop Lounge', 'description' => 'For BBS operators', 'members' => rand(2, 10)],
        ];

        return response()->json([
            'success' => true,
            'data' => $conferences
        ]);
    }

    /**
     * Join a conference
     */
    public function join(Request $request, int $conferenceId): JsonResponse
    {
        $conferences = [
            1 => 'Main',
            2 => 'Programming', 
            3 => 'Retro Computing',
            4 => 'Off Topic',
            5 => 'Gaming',
            6 => 'Music & Audio',
            7 => 'ANSI Art',
            8 => 'Sysop Lounge',
        ];

        $name = $conferences[$conferenceId] ?? "Conference {$conferenceId}";

        // Update user's current conference (optional - could store in DB)
        $user = $request->user();
        if ($user) {
            // Could update user's current_conference field here
        }

        return response()->json([
            'success' => true,
            'message' => "Joined {$name} conference",
            'data' => [
                'conference_id' => $conferenceId,
                'conference_name' => $name
            ]
        ]);
    }

    /**
     * Get current conference
     */
    public function current(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'conference_id' => 1,
                'conference_name' => 'Main'
            ]
        ]);
    }
}
