<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Node;
use App\Models\Message;
use App\Models\Story;
use App\Models\File;
use App\Models\Game;
use App\Models\GameScore;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Get system dashboard statistics
     */
    public function dashboard()
    {
        $stats = Cache::remember('admin_dashboard', 300, function () {
            return [
                'users' => [
                    'total' => User::count(),
                    'active_today' => User::whereDate('last_login_at', today())->count(),
                    'active_week' => User::where('last_login_at', '>=', now()->subWeek())->count(),
                    'new_today' => User::whereDate('created_at', today())->count(),
                    'new_week' => User::where('created_at', '>=', now()->subWeek())->count(),
                    'by_level' => User::select('level', DB::raw('count(*) as count'))
                        ->groupBy('level')
                        ->pluck('count', 'level'),
                ],
                'nodes' => [
                    'total' => Node::count(),
                    'active' => Node::whereNotNull('current_user_id')->count(),
                    'available' => Node::whereNull('current_user_id')->where('is_active', true)->count(),
                ],
                'content' => [
                    'messages' => Message::count(),
                    'messages_today' => Message::whereDate('created_at', today())->count(),
                    'stories' => Story::count(),
                    'files' => File::where('status', 'APPROVED')->count(),
                    'files_pending' => File::where('status', 'PENDING')->count(),
                ],
                'games' => [
                    'total_games' => Game::where('is_active', true)->count(),
                    'games_played_today' => GameScore::whereDate('created_at', today())->count(),
                ],
                'system' => [
                    'uptime' => $this->getSystemUptime(),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'disk_usage' => $this->getDiskUsage(),
                ],
            ];
        });

        return response()->json(['data' => $stats]);
    }

    /**
     * Get caller log
     */
    public function callerLog(Request $request)
    {
        $days = $request->get('days', 7);
        
        $logs = ActivityLog::with('user:id,handle,level')
            ->where('action', 'login')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($logs);
    }

    /**
     * Get top users by various metrics
     */
    public function topUsers(Request $request)
    {
        $metric = $request->get('metric', 'calls');
        $limit = min($request->get('limit', 10), 100);

        $query = User::where('is_bot', false);

        switch ($metric) {
            case 'calls':
                $query->orderBy('total_calls', 'desc');
                break;
            case 'messages':
                $query->orderBy('messages_posted', 'desc');
                break;
            case 'uploads':
                $query->orderBy('files_uploaded', 'desc');
                break;
            case 'downloads':
                $query->orderBy('files_downloaded', 'desc');
                break;
            case 'time':
                $query->orderBy('total_time_online', 'desc');
                break;
            case 'credits':
                $query->orderBy('credits', 'desc');
                break;
            default:
                $query->orderBy('total_calls', 'desc');
        }

        $users = $query->limit($limit)
            ->get(['id', 'handle', 'level', 'total_calls', 'messages_posted', 
                   'files_uploaded', 'files_downloaded', 'total_time_online', 'credits']);

        return response()->json(['data' => $users, 'metric' => $metric]);
    }

    /**
     * Get system statistics
     */
    public function systemStats()
    {
        $stats = [
            'database' => [
                'users' => User::count(),
                'messages' => Message::count(),
                'stories' => Story::count(),
                'files' => File::count(),
                'game_scores' => GameScore::count(),
            ],
            'storage' => [
                'files_size' => File::sum('size'),
                'disk_free' => disk_free_space(storage_path()),
                'disk_total' => disk_total_space(storage_path()),
            ],
            'performance' => [
                'cache_driver' => config('cache.default'),
                'session_driver' => config('session.driver'),
                'queue_driver' => config('queue.default'),
            ],
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Get daily/weekly report
     */
    public function report(Request $request)
    {
        $period = $request->get('period', 'daily');
        $date = $request->get('date', today()->toDateString());
        
        $startDate = Carbon::parse($date);
        $endDate = $period === 'weekly' ? $startDate->copy()->addWeek() : $startDate->copy()->addDay();

        $report = [
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'logins' => User::whereBetween('last_login_at', [$startDate, $endDate])->count(),
            'new_users' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
            'messages_posted' => Message::whereBetween('created_at', [$startDate, $endDate])->count(),
            'files_uploaded' => File::whereBetween('created_at', [$startDate, $endDate])->count(),
            'files_downloaded' => DB::table('files')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->sum('download_count'),
            'games_played' => GameScore::whereBetween('created_at', [$startDate, $endDate])->count(),
            'peak_users' => $this->getPeakUsers($startDate, $endDate),
        ];

        return response()->json(['data' => $report]);
    }

    /**
     * User administration - list users
     */
    public function users(Request $request)
    {
        $query = User::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('handle', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('real_name', 'like', "%{$search}%");
            });
        }

        if ($level = $request->get('level')) {
            $query->where('level', $level);
        }

        if ($request->get('bots') === 'only') {
            $query->where('is_bot', true);
        } elseif ($request->get('bots') !== 'include') {
            $query->where('is_bot', false);
        }

        $users = $query->orderBy('last_login_at', 'desc')
            ->paginate($request->get('per_page', 25));

        return response()->json($users);
    }

    /**
     * Get single user details
     */
    public function userShow($userId)
    {
        $user = User::with(['activityLogs' => function ($q) {
            $q->latest()->limit(20);
        }])->findOrFail($userId);

        return response()->json(['data' => $user]);
    }

    /**
     * Update user
     */
    public function userUpdate(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'handle' => 'sometimes|string|max:30|unique:users,handle,' . $userId,
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'level' => 'sometimes|in:' . implode(',', User::LEVELS),
            'is_validated' => 'sometimes|boolean',
            'credits' => 'sometimes|integer|min:0',
            'time_limit_minutes' => 'sometimes|integer|min:0',
            'download_limit_kb' => 'sometimes|integer|min:0',
        ]);

        $user->update($validated);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'admin_user_update',
            'details' => json_encode(['target_user' => $userId, 'changes' => $validated]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => __('admin.user_updated'),
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Delete/ban user
     */
    public function userDelete(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        if ($user->level === User::LEVEL_SYSOP) {
            return response()->json(['error' => 'Cannot delete SysOp'], 403);
        }

        $action = $request->get('action', 'delete');

        if ($action === 'ban') {
            $user->update([
                'level' => User::LEVEL_TWIT,
                'is_validated' => false,
            ]);
            $message = __('admin.user_banned');
        } else {
            $user->delete();
            $message = __('admin.user_deleted');
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => "admin_user_{$action}",
            'details' => json_encode(['target_user' => $userId, 'handle' => $user->handle]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => $message]);
    }

    /**
     * System configuration
     */
    public function config()
    {
        $config = [
            'bbs_name' => config('app.name'),
            'max_nodes' => Node::count(),
            'default_time_limit' => config('punktet.default_time_limit', 60),
            'default_download_limit' => config('punktet.default_download_limit', 10240),
            'registration_open' => config('punktet.registration_open', true),
            'require_validation' => config('punktet.require_validation', false),
            'maintenance_mode' => app()->isDownForMaintenance(),
        ];

        return response()->json(['data' => $config]);
    }

    /**
     * Update system configuration
     */
    public function configUpdate(Request $request)
    {
        // Note: In production, these would be stored in database
        // For now, return success message
        return response()->json([
            'message' => __('admin.config_updated'),
            'note' => 'Configuration changes require .env update for persistence',
        ]);
    }

    /**
     * Toggle maintenance mode
     */
    public function maintenance(Request $request)
    {
        $enable = $request->get('enable', false);

        if ($enable) {
            Artisan::call('down', [
                '--secret' => $request->get('secret', 'sysop-access'),
            ]);
            $message = __('admin.maintenance_enabled');
        } else {
            Artisan::call('up');
            $message = __('admin.maintenance_disabled');
        }

        return response()->json(['message' => $message, 'maintenance' => $enable]);
    }

    /**
     * Clear caches
     */
    public function clearCache(Request $request)
    {
        $type = $request->get('type', 'all');

        switch ($type) {
            case 'config':
                Artisan::call('config:clear');
                break;
            case 'route':
                Artisan::call('route:clear');
                break;
            case 'view':
                Artisan::call('view:clear');
                break;
            case 'cache':
                Artisan::call('cache:clear');
                break;
            default:
                Artisan::call('optimize:clear');
        }

        return response()->json(['message' => __('admin.cache_cleared', ['type' => $type])]);
    }

    /**
     * Get activity logs
     */
    public function activityLogs(Request $request)
    {
        $query = ActivityLog::with('user:id,handle');

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        if ($ip = $request->get('ip')) {
            $query->where('ip_address', $ip);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json($logs);
    }

    /**
     * Get peak hours statistics
     */
    public function peakHours(Request $request)
    {
        $days = $request->get('days', 30);

        $hourlyStats = ActivityLog::where('action', 'login')
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        // Fill in missing hours with 0
        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $result[$i] = $hourlyStats[$i] ?? 0;
        }

        return response()->json([
            'data' => $result,
            'peak_hour' => array_search(max($result), $result),
            'period_days' => $days,
        ]);
    }

    /**
     * Get message volume statistics
     */
    public function messageVolume(Request $request)
    {
        $days = $request->get('days', 30);

        $dailyStats = Message::where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => $dailyStats,
            'total' => $dailyStats->sum('count'),
            'average' => round($dailyStats->avg('count'), 1),
        ]);
    }

    /**
     * Get user rankings
     */
    public function userRankings()
    {
        $rankings = [
            'most_active' => User::where('is_bot', false)
                ->orderBy('total_calls', 'desc')
                ->limit(10)
                ->get(['id', 'handle', 'total_calls']),
            'top_posters' => User::where('is_bot', false)
                ->orderBy('messages_posted', 'desc')
                ->limit(10)
                ->get(['id', 'handle', 'messages_posted']),
            'top_uploaders' => User::where('is_bot', false)
                ->orderBy('files_uploaded', 'desc')
                ->limit(10)
                ->get(['id', 'handle', 'files_uploaded']),
            'longest_online' => User::where('is_bot', false)
                ->orderBy('total_time_online', 'desc')
                ->limit(10)
                ->get(['id', 'handle', 'total_time_online']),
            'richest' => User::where('is_bot', false)
                ->orderBy('credits', 'desc')
                ->limit(10)
                ->get(['id', 'handle', 'credits']),
        ];

        return response()->json(['data' => $rankings]);
    }

    /**
     * Get yearly statistics
     */
    public function yearlyStats(Request $request)
    {
        $year = $request->get('year', date('Y'));

        $stats = [
            'year' => $year,
            'new_users' => User::whereYear('created_at', $year)->count(),
            'total_logins' => ActivityLog::where('action', 'login')
                ->whereYear('created_at', $year)->count(),
            'messages_posted' => Message::whereYear('created_at', $year)->count(),
            'files_uploaded' => File::whereYear('created_at', $year)->count(),
            'monthly_breakdown' => $this->getMonthlyBreakdown($year),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * IP logging - get IPs for a user
     */
    public function userIps($userId)
    {
        $ips = ActivityLog::where('user_id', $userId)
            ->whereNotNull('ip_address')
            ->select('ip_address', DB::raw('COUNT(*) as count'), DB::raw('MAX(created_at) as last_seen'))
            ->groupBy('ip_address')
            ->orderBy('last_seen', 'desc')
            ->get();

        return response()->json(['data' => $ips]);
    }

    /**
     * Generate RSS feed data
     */
    public function rssFeed(Request $request)
    {
        $type = $request->get('type', 'stories');
        $limit = min($request->get('limit', 20), 50);

        switch ($type) {
            case 'stories':
                $items = Story::where('is_published', true)
                    ->orderBy('published_at', 'desc')
                    ->limit($limit)
                    ->get(['id', 'title_en', 'content_en', 'published_at']);
                break;
            case 'messages':
                $items = Message::with('thread:id,title')
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get(['id', 'thread_id', 'content', 'created_at']);
                break;
            default:
                $items = collect();
        }

        return response()->json([
            'feed_type' => $type,
            'items' => $items,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    // Helper methods

    private function getSystemUptime(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int) explode(' ', $uptime)[0];
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                return "{$days}d {$hours}h {$minutes}m";
            }
        }
        return 'N/A';
    }

    private function getDiskUsage(): array
    {
        $path = storage_path();
        return [
            'free' => disk_free_space($path),
            'total' => disk_total_space($path),
            'used_percent' => round((1 - disk_free_space($path) / disk_total_space($path)) * 100, 1),
        ];
    }

    private function getPeakUsers($startDate, $endDate): int
    {
        // This would need proper tracking in production
        return ActivityLog::where('action', 'login')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    private function getMonthlyBreakdown($year): array
    {
        $breakdown = [];
        for ($month = 1; $month <= 12; $month++) {
            $breakdown[$month] = [
                'users' => User::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)->count(),
                'messages' => Message::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)->count(),
            ];
        }
        return $breakdown;
    }
}
