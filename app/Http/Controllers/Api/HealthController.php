<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * PUNKTET BBS Health Controller
 * 
 * Helsesjekk-endepunkter for monitoring og diagnostikk
 */
class HealthController extends Controller
{
    /**
     * Enkel helsesjekk - returnerer 200 hvis systemet kjører
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'OK',
            'message' => 'PUNKTET BBS er online',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Detaljert helsesjekk
     */
    public function status(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // Database-sjekk
        $checks['database'] = $this->checkDatabase();
        if (!$checks['database']['healthy']) $allHealthy = false;

        // Cache-sjekk
        $checks['cache'] = $this->checkCache();
        if (!$checks['cache']['healthy']) $allHealthy = false;

        // Storage-sjekk
        $checks['storage'] = $this->checkStorage();
        if (!$checks['storage']['healthy']) $allHealthy = false;

        // Session-sjekk
        $checks['session'] = $this->checkSession();
        if (!$checks['session']['healthy']) $allHealthy = false;

        // PHP-sjekk
        $checks['php'] = $this->checkPhp();

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'server_time' => now()->toIso8601String(),
            'uptime' => $this->getUptime(),
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Detaljert systeminfo (kun for COSYSOP+)
     * Alias for diagnostics for route consistency
     */
    public function detailed(): JsonResponse
    {
        return $this->diagnostics();
    }

    /**
     * Detaljert systeminfo (kun for SYSOP)
     */
    public function diagnostics(): JsonResponse
    {
        return response()->json([
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'debug_mode' => config('app.debug'),
                'environment' => config('app.env'),
            ],
            'database' => $this->getDatabaseInfo(),
            'cache' => $this->getCacheInfo(),
            'storage' => $this->getStorageInfo(),
            'memory' => $this->getMemoryInfo(),
            'extensions' => $this->getExtensions(),
            'bbs' => $this->getBbsStats(),
        ]);
    }

    /**
     * Sjekk database-tilkobling
     */
    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'latency_ms' => $latency,
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            Log::error('Health check: Database failed', ['error' => $e->getMessage()]);
            return [
                'healthy' => false,
                'error' => 'Database connection failed',
            ];
        }
    }

    /**
     * Sjekk cache
     */
    protected function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            $start = microtime(true);
            
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => $value === 'test',
                'latency_ms' => $latency,
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            Log::error('Health check: Cache failed', ['error' => $e->getMessage()]);
            return [
                'healthy' => false,
                'error' => 'Cache operation failed',
            ];
        }
    }

    /**
     * Sjekk storage
     */
    protected function checkStorage(): array
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            $start = microtime(true);
            
            Storage::put($testFile, 'test');
            $content = Storage::get($testFile);
            Storage::delete($testFile);
            
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => $content === 'test',
                'latency_ms' => $latency,
                'disk' => config('filesystems.default'),
            ];
        } catch (\Exception $e) {
            Log::error('Health check: Storage failed', ['error' => $e->getMessage()]);
            return [
                'healthy' => false,
                'error' => 'Storage operation failed',
            ];
        }
    }

    /**
     * Sjekk session
     */
    protected function checkSession(): array
    {
        try {
            return [
                'healthy' => true,
                'driver' => config('session.driver'),
                'lifetime' => config('session.lifetime'),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => 'Session check failed',
            ];
        }
    }

    /**
     * Sjekk PHP-konfigurasjon
     */
    protected function checkPhp(): array
    {
        return [
            'healthy' => true,
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
        ];
    }

    /**
     * Hent database-info
     */
    protected function getDatabaseInfo(): array
    {
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version ?? 'Unknown';
            
            return [
                'driver' => config('database.default'),
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'version' => $version,
                'tables' => DB::select('SHOW TABLES'),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Hent cache-info
     */
    protected function getCacheInfo(): array
    {
        return [
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
            'stores' => array_keys(config('cache.stores')),
        ];
    }

    /**
     * Hent storage-info
     */
    protected function getStorageInfo(): array
    {
        $disk = Storage::disk('local');
        $path = storage_path();
        
        return [
            'default_disk' => config('filesystems.default'),
            'storage_path' => $path,
            'free_space' => $this->formatBytes(disk_free_space($path)),
            'total_space' => $this->formatBytes(disk_total_space($path)),
        ];
    }

    /**
     * Hent minnebruk-info
     */
    protected function getMemoryInfo(): array
    {
        return [
            'current_usage' => $this->formatBytes(memory_get_usage(true)),
            'peak_usage' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Hent installerte PHP-extensions
     */
    protected function getExtensions(): array
    {
        $required = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'curl', 'xml'];
        $installed = get_loaded_extensions();
        
        $status = [];
        foreach ($required as $ext) {
            $status[$ext] = in_array($ext, $installed);
        }
        
        return [
            'required' => $status,
            'all_installed' => $installed,
        ];
    }

    /**
     * Hent BBS-spesifikk statistikk
     */
    protected function getBbsStats(): array
    {
        try {
            return [
                'total_users' => DB::table('users')->count(),
                'total_messages' => DB::table('messages')->count(),
                'total_forums' => DB::table('forums')->count(),
                'online_users' => DB::table('users')
                    ->where('last_activity', '>=', now()->subMinutes(5))
                    ->count(),
                'messages_today' => DB::table('messages')
                    ->whereDate('created_at', today())
                    ->count(),
                'new_users_today' => DB::table('users')
                    ->whereDate('created_at', today())
                    ->count(),
            ];
        } catch (\Exception $e) {
            return ['error' => 'Could not fetch BBS stats'];
        }
    }

    /**
     * Hent system uptime
     */
    protected function getUptime(): string
    {
        try {
            // Prøv /proc/uptime først (mest nøyaktig)
            if (@file_exists('/proc/uptime')) {
                $uptime = @file_get_contents('/proc/uptime');
                if ($uptime !== false) {
                    $seconds = (int) explode(' ', $uptime)[0];
                    return $this->formatUptime($seconds);
                }
            }
            
            // Fallback: exec uptime kommando
            if (function_exists('exec')) {
                $output = @exec('uptime -s 2>/dev/null');
                if ($output) {
                    $bootTime = strtotime($output);
                    if ($bootTime) {
                        $seconds = time() - $bootTime;
                        return $this->formatUptime($seconds);
                    }
                }
            }
            
            // Fallback: BBS uptime (tid siden første request i dag)
            $startTime = Cache::get('bbs_start_time');
            if (!$startTime) {
                $startTime = now()->timestamp;
                Cache::put('bbs_start_time', $startTime, now()->addYear());
            }
            $seconds = now()->timestamp - $startTime;
            return $this->formatUptime($seconds) . ' (session)';
            
        } catch (\Exception $e) {
            // Ignore
        }
        
        return 'Unknown';
    }

    /**
     * Formater sekunder til lesbar uptime-streng
     */
    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        $parts[] = "{$minutes}m";
        
        return implode(' ', $parts);
    }

    /**
     * Formater bytes til lesbar størrelse
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
