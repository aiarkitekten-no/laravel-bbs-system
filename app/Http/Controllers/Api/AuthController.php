<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Node;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'handle' => 'required|string|min:3|max:50|unique:users,handle|alpha_dash',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'locale' => 'nullable|string|in:en,no',
        ], [
            'handle.unique' => __('auth.handle_taken'),
            'email.unique' => __('auth.email_taken'),
            'password.confirmed' => __('auth.password_mismatch'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Use secure factory method to set sensitive attributes
        $user = User::createWithDefaults([
            'handle' => $request->handle,
            'email' => $request->email,
            'password' => $request->password,
            'name' => $request->name,
            'location' => $request->location,
            'locale' => $request->locale ?? 'en',
        ], User::LEVEL_USER, 100);

        // Log the registration
        ActivityLog::log($user, 'register', __('activity.user_registered'));

        // Get a node
        $node = Node::getFirstAvailable();
        $token = $user->createToken('bbs-terminal')->plainTextToken;

        if ($node) {
            $node->assignUser($user);
            $user->updateLastLogin($request->ip());
        }

        return response()->json([
            'success' => true,
            'message' => __('auth.registered'),
            'data' => [
                'user' => $this->formatUserResponse($user),
                'token' => $token,
                'node' => $node ? $node->node_number : null,
            ],
        ], 201);
    }

    /**
     * Login existing user
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // Can be email or handle
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Try to find user by email or handle
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'handle';
        $user = User::where($loginField, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.failed'),
            ], 401);
        }

        // Check if user is already online
        if ($user->is_online) {
            // Force disconnect from previous session
            if ($user->currentNode) {
                $user->currentNode->releaseUser();
            }
        }

        // Get a node
        $node = Node::getFirstAvailable();
        
        if (!$node) {
            return response()->json([
                'success' => false,
                'message' => __('auth.no_nodes_available'),
            ], 503);
        }

        // Assign node and create token
        $node->assignUser($user);
        $user->updateLastLogin($request->ip());

        // Revoke old tokens
        $user->tokens()->delete();
        $token = $user->createToken('bbs-terminal')->plainTextToken;

        // Log the login
        ActivityLog::log($user, 'login', __('activity.user_logged_in'));

        return response()->json([
            'success' => true,
            'message' => __('auth.logged_in'),
            'data' => [
                'user' => $this->formatUserResponse($user->fresh()),
                'token' => $token,
                'node' => $node->node_number,
            ],
        ]);
    }

    /**
     * Guest login
     */
    public function guestLogin(Request $request): JsonResponse
    {
        // Generate unique guest handle
        $guestNumber = User::where('level', User::LEVEL_GUEST)->count() + 1;
        $handle = 'Guest_' . $guestNumber . '_' . Str::random(4);

        $user = User::create([
            'handle' => $handle,
            'email' => 'guest_' . Str::uuid() . '@punktet.no',
            'password' => Hash::make(Str::random(32)),
            'level' => User::LEVEL_GUEST,
            'locale' => $request->locale ?? 'en',
            'credits' => 10, // Limited credits for guests
            'daily_time_limit' => 1800, // 30 minutes for guests
        ]);

        $node = Node::getFirstAvailable();
        
        if (!$node) {
            $user->delete(); // Clean up
            return response()->json([
                'success' => false,
                'message' => __('auth.no_nodes_available'),
            ], 503);
        }

        $node->assignUser($user);
        $user->updateLastLogin($request->ip());
        $token = $user->createToken('bbs-terminal-guest')->plainTextToken;

        // Log the login
        ActivityLog::log($user, 'guest_login', __('activity.guest_logged_in'));

        return response()->json([
            'success' => true,
            'message' => __('auth.guest_welcome'),
            'data' => [
                'user' => $this->formatUserResponse($user),
                'token' => $token,
                'node' => $node->node_number,
                'is_guest' => true,
                'time_limit' => 1800,
            ],
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            // Release node
            if ($user->currentNode) {
                $user->currentNode->releaseUser();
            }

            // Log the logout
            ActivityLog::log($user, 'logout', __('activity.user_logged_out'));

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // If guest, delete account
            if ($user->isGuest()) {
                $user->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('auth.logged_out'),
        ]);
    }

    /**
     * Get current user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('currentNode');

        return response()->json([
            'success' => true,
            'data' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'ascii_signature' => 'nullable|string|max:500',
            'birthday' => 'nullable|date|before:today',
            'locale' => 'nullable|string|in:en,no',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update($request->only([
            'name', 'bio', 'location', 'ascii_signature', 'birthday', 'locale'
        ]));

        return response()->json([
            'success' => true,
            'message' => __('profile.updated'),
            'data' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.password_incorrect'),
            ], 401);
        }

        $user->update(['password' => $request->password]);

        return response()->json([
            'success' => true,
            'message' => __('auth.password_changed'),
        ]);
    }

    /**
     * Format user response
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'handle' => $user->handle,
            'name' => $user->name,
            'email' => $user->email,
            'level' => $user->level,
            'locale' => $user->locale,
            'bio' => $user->bio,
            'location' => $user->location,
            'ascii_signature' => $user->ascii_signature,
            'birthday' => $user->birthday?->format('Y-m-d'),
            'stats' => [
                'total_logins' => $user->total_logins,
                'total_messages' => $user->total_messages,
                'total_files_uploaded' => $user->total_files_uploaded,
                'total_files_downloaded' => $user->total_files_downloaded,
                'total_time_online' => $user->total_time_online,
                'time_online_formatted' => $user->getFormattedTimeOnline(),
                'credits' => $user->credits,
            ],
            'time' => [
                'daily_used' => $user->daily_time_used,
                'daily_limit' => $user->daily_time_limit,
                'remaining' => $user->getRemainingDailyTime(),
                'time_bank' => $user->time_bank,
            ],
            'is_online' => $user->is_online,
            'current_node' => $user->currentNode?->node_number,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_activity_at' => $user->last_activity_at?->toIso8601String(),
            'member_since' => $user->created_at->toIso8601String(),
            'is_staff' => $user->isStaff(),
            'is_sysop' => $user->isSysop(),
            'unread_messages' => $user->getUnreadPrivateMessagesCount(),
        ];
    }
}
