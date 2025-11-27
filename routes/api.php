<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PrivateMessageController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\OnelinerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Apply locale middleware to all API routes
Route::middleware(['locale'])->group(function () {

    // ==========================================
    // PUBLIC ROUTES (No authentication required)
    // ==========================================
    
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/guest', [AuthController::class, 'guestLogin']);
    });

    // Public node status
    Route::get('/nodes', [NodeController::class, 'index']);
    Route::get('/nodes/{nodeNumber}', [NodeController::class, 'show']);
    Route::get('/whos-online', [NodeController::class, 'whosOnline']);
    Route::get('/last-callers/{count?}', [NodeController::class, 'lastCallers']);

    // Public stories (read-only)
    Route::get('/stories/today', [StoryController::class, 'today']);
    Route::get('/stories', [StoryController::class, 'index']);
    Route::get('/stories/top', [StoryController::class, 'topRated']);
    Route::get('/stories/archive', [StoryController::class, 'archive']);
    Route::get('/stories/{storyId}', [StoryController::class, 'show']);
    Route::get('/stories/{storyId}/comments', [StoryController::class, 'comments']);

    // Public oneliners (read-only)
    Route::get('/oneliners', [OnelinerController::class, 'index']);

    // Public categories
    Route::get('/categories', [MessageController::class, 'categories']);
    Route::get('/categories/{categoryId}/threads', [MessageController::class, 'threads']);
    Route::get('/threads/{threadId}/messages', [MessageController::class, 'messages']);

    // ==========================================
    // AUTHENTICATED ROUTES
    // ==========================================
    
    Route::middleware(['auth:sanctum', 'activity', 'time.check'])->group(function () {
        
        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'changePassword']);
        });

        // Node operations (requires being on a node)
        Route::prefix('node')->group(function () {
            Route::put('/activity', [NodeController::class, 'updateActivity']);
            Route::post('/chat', [NodeController::class, 'sendChat']);
            Route::get('/chat', [NodeController::class, 'getChat']);
            Route::post('/page', [NodeController::class, 'pageUser']);
            Route::put('/auto-reply', [NodeController::class, 'setAutoReply']);
        });

        // ==========================================
        // FORUM / MESSAGE BASES
        // ==========================================

        Route::prefix('forum')->group(function () {
            Route::post('/threads', [MessageController::class, 'createThread']);
            Route::post('/threads/{threadId}/reply', [MessageController::class, 'reply']);
            Route::get('/messages/{messageId}/quote', [MessageController::class, 'quote']);
            Route::get('/search', [MessageController::class, 'search']);
            Route::get('/new', [MessageController::class, 'newSince']);
            Route::delete('/messages/{messageId}', [MessageController::class, 'deleteMessage']);
        });

        // ==========================================
        // PRIVATE MESSAGES
        // ==========================================

        Route::prefix('pm')->group(function () {
            Route::get('/inbox', [PrivateMessageController::class, 'inbox']);
            Route::get('/sent', [PrivateMessageController::class, 'sent']);
            Route::get('/unread-count', [PrivateMessageController::class, 'unreadCount']);
            Route::post('/send', [PrivateMessageController::class, 'send']);
            Route::get('/{messageId}', [PrivateMessageController::class, 'show']);
            Route::post('/{messageId}/reply', [PrivateMessageController::class, 'reply']);
            Route::delete('/{messageId}', [PrivateMessageController::class, 'delete']);
            Route::post('/mark-all-read', [PrivateMessageController::class, 'markAllRead']);
        });

        // ==========================================
        // AI STORIES
        // ==========================================

        Route::prefix('stories')->group(function () {
            Route::post('/{storyId}/upvote', [StoryController::class, 'upvote']);
            Route::post('/{storyId}/downvote', [StoryController::class, 'downvote']);
            Route::post('/{storyId}/favorite', [StoryController::class, 'toggleFavorite']);
            Route::get('/favorites', [StoryController::class, 'favorites']);
            Route::post('/{storyId}/comments', [StoryController::class, 'addComment']);
            Route::delete('/comments/{commentId}', [StoryController::class, 'deleteComment']);
        });

        // ==========================================
        // ONELINERS
        // ==========================================

        Route::prefix('oneliners')->group(function () {
            Route::post('/', [OnelinerController::class, 'store']);
            Route::get('/mine', [OnelinerController::class, 'myOneliners']);
            Route::delete('/{onelinerId}', [OnelinerController::class, 'destroy']);
        });

        // ==========================================
        // USER LEVEL RESTRICTED ROUTES
        // ==========================================

        // Elite+ routes
        Route::middleware(['level:ELITE'])->group(function () {
            // Elite features will be added in later phases
        });

        // CoSysOp+ routes
        Route::middleware(['level:COSYSOP'])->group(function () {
            // Forum moderation
            Route::put('/forum/threads/{threadId}/lock', [MessageController::class, 'toggleLock']);
            Route::put('/forum/threads/{threadId}/sticky', [MessageController::class, 'toggleSticky']);
            
            // Oneliner moderation
            Route::get('/oneliners/pending', [OnelinerController::class, 'pending']);
            Route::post('/oneliners/{onelinerId}/approve', [OnelinerController::class, 'approve']);
            Route::post('/oneliners/{onelinerId}/reject', [OnelinerController::class, 'reject']);
        });

        // SysOp only routes
        Route::middleware(['level:SYSOP'])->group(function () {
            // Admin features will be added in later phases
        });
    });
});
