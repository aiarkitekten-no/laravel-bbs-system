<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PrivateMessageController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\OnelinerController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\AnsiArtController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\BulletinController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\HealthController;

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

// Health check routes - basic ping is public, detailed requires auth
Route::get('/health/ping', [HealthController::class, 'ping']);
Route::get('/health/status', [HealthController::class, 'status']);

// Detailed health info requires authentication (exposes system info)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/health/detailed', [HealthController::class, 'detailed'])->middleware('level:COSYSOP');
});

// Apply locale middleware to all API routes
Route::middleware(['locale'])->group(function () {

    // ==========================================
    // PUBLIC ROUTES (No authentication required)
    // ==========================================
    
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login'])->middleware('login.throttle');
        Route::post('/guest', [AuthController::class, 'guestLogin'])->middleware('login.throttle');
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

    // Conferences (public list)
    Route::get('/conferences', [App\Http\Controllers\Api\ConferenceController::class, 'index']);

    // ==========================================
    // PUBLIC FILE AREA (read-only)
    // ==========================================
    Route::prefix('files')->group(function () {
        Route::get('/categories', [FileController::class, 'categories']);
        Route::get('/categories/{categoryId}', [FileController::class, 'list']);
        Route::get('/search', [FileController::class, 'search']);
        Route::get('/new', [FileController::class, 'newSince']);
        Route::get('/top-uploaders', [FileController::class, 'topUploaders']);
        Route::get('/{fileId}', [FileController::class, 'show'])->where('fileId', '[0-9]+');
    });

    // ==========================================
    // PUBLIC GAMES (read-only)
    // ==========================================
    Route::prefix('games')->group(function () {
        Route::get('/', [GameController::class, 'index']);
        Route::get('/highscores', [GameController::class, 'globalHighscores']);
        Route::get('/achievements', [GameController::class, 'achievements']);
        Route::get('/{slug}', [GameController::class, 'show'])->where('slug', '[a-z0-9-]+');
        Route::get('/{slug}/highscores', [GameController::class, 'highscores'])->where('slug', '[a-z0-9-]+');
    });

    // ==========================================
    // PUBLIC ANSI ART GALLERY (read-only)
    // ==========================================
    Route::prefix('ansi')->group(function () {
        Route::get('/', [AnsiArtController::class, 'index']);
        Route::get('/categories', [AnsiArtController::class, 'categories']);
        Route::get('/random', [AnsiArtController::class, 'random']);
        Route::get('/{id}', [AnsiArtController::class, 'show'])->where('id', '[0-9]+');
    });

    // ==========================================
    // PUBLIC POLLS (read-only)
    // ==========================================
    Route::prefix('polls')->group(function () {
        Route::get('/', [PollController::class, 'index']);  // Use ?active=true for active polls
        Route::get('/{id}', [PollController::class, 'show'])->where('id', '[0-9]+');
        Route::get('/{id}/results', [PollController::class, 'results'])->where('id', '[0-9]+');
    });

    // ==========================================
    // PUBLIC BULLETINS (read-only)
    // ==========================================
    Route::prefix('bulletin')->group(function () {
        Route::get('/', [BulletinController::class, 'index']);
        Route::get('/bbs-links', [BulletinController::class, 'bbsList']);
        Route::get('/logoff-quote', [BulletinController::class, 'randomQuote']);
        Route::get('/{id}', [BulletinController::class, 'show'])->where('id', '[0-9]+');
    });

    // ==========================================
    // PUBLIC SOCIAL (read-only)
    // ==========================================
    Route::prefix('social')->group(function () {
        Route::get('/clubs', [SocialController::class, 'clubs']);
        Route::get('/clubs/{id}', [SocialController::class, 'showClub'])->where('id', '[0-9]+');
        Route::get('/awards', [SocialController::class, 'awards']);
        Route::get('/awards/month/{year}/{month}', [SocialController::class, 'awardsByMonth']);
        Route::get('/graffiti', [SocialController::class, 'graffitiWall']);
        Route::get('/birthdays', [SocialController::class, 'todaysBirthdays']);
        Route::get('/birthdays/upcoming', [SocialController::class, 'upcomingBirthdays']);
    });

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

        // Conferences (authenticated)
        Route::prefix('conferences')->group(function () {
            Route::post('/{conferenceId}/join', [App\Http\Controllers\Api\ConferenceController::class, 'join']);
            Route::get('/current', [App\Http\Controllers\Api\ConferenceController::class, 'current']);
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

            // File approval
            Route::prefix('files')->group(function () {
                Route::get('/pending', [FileController::class, 'pending']);
                Route::post('/{fileId}/approve', [FileController::class, 'approve']);
                Route::post('/{fileId}/reject', [FileController::class, 'reject']);
                Route::get('/requests', [FileController::class, 'requests']);
                Route::post('/requests/{requestId}/fulfill', [FileController::class, 'fulfillRequest']);
            });
        });

        // SysOp only routes
        Route::middleware(['level:SYSOP'])->group(function () {
            // ==========================================
            // ADMIN & SYSOP DASHBOARD (Phase 11)
            // ==========================================
            
            Route::prefix('admin')->group(function () {
                // Dashboard & Stats
                Route::get('/dashboard', [AdminController::class, 'dashboard']);
                Route::get('/caller-log', [AdminController::class, 'callerLog']);
                Route::get('/top-users', [AdminController::class, 'topUsers']);
                Route::get('/system-stats', [AdminController::class, 'systemStats']);
                Route::get('/report', [AdminController::class, 'report']);
                Route::get('/peak-hours', [AdminController::class, 'peakHours']);
                Route::get('/message-volume', [AdminController::class, 'messageVolume']);
                Route::get('/user-rankings', [AdminController::class, 'userRankings']);
                Route::get('/yearly-stats', [AdminController::class, 'yearlyStats']);
                
                // User Management
                Route::get('/users', [AdminController::class, 'users']);
                Route::get('/users/{userId}', [AdminController::class, 'userShow']);
                Route::put('/users/{userId}', [AdminController::class, 'userUpdate']);
                Route::delete('/users/{userId}', [AdminController::class, 'userDelete']);
                Route::get('/users/{userId}/ips', [AdminController::class, 'userIps']);
                
                // System Configuration
                Route::get('/config', [AdminController::class, 'config']);
                Route::put('/config', [AdminController::class, 'configUpdate']);
                Route::post('/maintenance', [AdminController::class, 'maintenance']);
                Route::post('/clear-cache', [AdminController::class, 'clearCache']);
                
                // Activity & Logs
                Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
                
                // System Health & Diagnostics
                Route::get('/diagnostics', [HealthController::class, 'diagnostics']);
                
                // RSS Feed
                Route::get('/rss', [AdminController::class, 'rssFeed']);
            });
        });

        // ==========================================
        // FILE AREA - Authenticated actions only
        // ==========================================

        Route::prefix('files')->group(function () {
            Route::post('/duplicate-check', [FileController::class, 'duplicateCheck']);
            Route::post('/upload', [FileController::class, 'upload']);
            Route::get('/{fileId}/download', [FileController::class, 'download']);
            Route::post('/requests', [FileController::class, 'createRequest']);
            Route::get('/requests/open', [FileController::class, 'openRequests']);
        });

        // ==========================================
        // DOOR GAMES - Authenticated actions only
        // ==========================================

        Route::prefix('games')->group(function () {
            Route::get('/my-achievements', [GameController::class, 'myAchievements']);
            Route::post('/{slug}/start', [GameController::class, 'start']);
            Route::post('/{slug}/action', [GameController::class, 'action']);
            Route::get('/{slug}/state', [GameController::class, 'getState']);
        });

        // ==========================================
        // ANSI ART GALLERY - Authenticated actions only
        // ==========================================

        Route::prefix('ansi')->group(function () {
            Route::get('/favorites', [AnsiArtController::class, 'myFavorites']);
            Route::post('/', [AnsiArtController::class, 'store']);
            Route::post('/{id}/view', [AnsiArtController::class, 'view']);
            Route::post('/{id}/favorite', [AnsiArtController::class, 'toggleFavorite']);
        });

        // ==========================================
        // POLLS - Authenticated actions only
        // ==========================================

        Route::prefix('polls')->group(function () {
            Route::get('/my-votes', [PollController::class, 'myVotes']);
            Route::post('/', [PollController::class, 'create']);
            Route::post('/{id}/vote', [PollController::class, 'vote']);
        });

        // ==========================================
        // BULLETINS - (all public, nothing here)
        // ==========================================

        // ==========================================
        // SOCIAL FEATURES - Authenticated actions only
        // ==========================================

        Route::prefix('social')->group(function () {
            // Time Bank (all require auth)
            Route::get('/time-bank', [SocialController::class, 'timeBank']);
            Route::post('/time-bank/deposit', [SocialController::class, 'timeBankDeposit']);
            Route::post('/time-bank/withdraw', [SocialController::class, 'timeBankWithdraw']);
            Route::get('/time-bank/history', [SocialController::class, 'timeBankHistory']);
            
            // User Clubs - write operations
            Route::post('/clubs', [SocialController::class, 'createClub']);
            Route::post('/clubs/{id}/join', [SocialController::class, 'joinClub']);
            Route::delete('/clubs/{id}/leave', [SocialController::class, 'leaveClub']);
            
            // Graffiti Wall - write operations
            Route::post('/graffiti', [SocialController::class, 'createGraffiti']);
            Route::delete('/graffiti/{id}', [SocialController::class, 'deleteGraffiti']);
        });
    });
});
