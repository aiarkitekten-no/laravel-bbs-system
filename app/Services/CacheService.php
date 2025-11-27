<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PUNKTET BBS Cache Service
 * 
 * Sentralisert cache-håndtering for hele systemet
 * Støtter tagged caching og automatisk invalidering
 */
class CacheService
{
    // Cache TTL verdier (sekunder)
    const TTL_SHORT = 300;      // 5 minutter - volatile data
    const TTL_MEDIUM = 1800;    // 30 minutter - semi-statisk data
    const TTL_LONG = 3600;      // 1 time - statisk data
    const TTL_DAY = 86400;      // 24 timer - sjeldent endret
    const TTL_WEEK = 604800;    // 7 dager - nesten permanent

    // Cache tags
    const TAG_MESSAGES = 'messages';
    const TAG_USERS = 'users';
    const TAG_FORUMS = 'forums';
    const TAG_FILES = 'files';
    const TAG_STATS = 'stats';
    const TAG_POLLS = 'polls';
    const TAG_DOORS = 'doors';
    const TAG_ANSI = 'ansi';
    const TAG_ADMIN = 'admin';

    /**
     * Hent verdi fra cache, eller kjør callback og cache resultatet
     */
    public static function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        $fullKey = self::buildKey($key);
        
        if (!empty($tags) && self::supportsTags()) {
            return Cache::tags($tags)->remember($fullKey, $ttl, $callback);
        }
        
        return Cache::remember($fullKey, $ttl, $callback);
    }

    /**
     * Hent verdi fra cache uten TTL (permanent til invalidering)
     */
    public static function rememberForever(string $key, callable $callback, array $tags = [])
    {
        $fullKey = self::buildKey($key);
        
        if (!empty($tags) && self::supportsTags()) {
            return Cache::tags($tags)->rememberForever($fullKey, $callback);
        }
        
        return Cache::rememberForever($fullKey, $callback);
    }

    /**
     * Lagre verdi i cache
     */
    public static function put(string $key, $value, int $ttl = null, array $tags = []): bool
    {
        $fullKey = self::buildKey($key);
        $ttl = $ttl ?? self::TTL_MEDIUM;
        
        try {
            if (!empty($tags) && self::supportsTags()) {
                return Cache::tags($tags)->put($fullKey, $value, $ttl);
            }
            
            return Cache::put($fullKey, $value, $ttl);
        } catch (\Exception $e) {
            Log::error('Cache put failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Hent verdi fra cache
     */
    public static function get(string $key, $default = null, array $tags = [])
    {
        $fullKey = self::buildKey($key);
        
        if (!empty($tags) && self::supportsTags()) {
            return Cache::tags($tags)->get($fullKey, $default);
        }
        
        return Cache::get($fullKey, $default);
    }

    /**
     * Slett en cache-nøkkel
     */
    public static function forget(string $key, array $tags = []): bool
    {
        $fullKey = self::buildKey($key);
        
        try {
            if (!empty($tags) && self::supportsTags()) {
                return Cache::tags($tags)->forget($fullKey);
            }
            
            return Cache::forget($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache forget failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Slett alle nøkler med en spesifikk tag
     */
    public static function flushTag(string $tag): bool
    {
        if (!self::supportsTags()) {
            Log::warning('Cache driver does not support tags, cannot flush tag', ['tag' => $tag]);
            return false;
        }
        
        try {
            Cache::tags([$tag])->flush();
            Log::info('Cache tag flushed', ['tag' => $tag]);
            return true;
        } catch (\Exception $e) {
            Log::error('Cache tag flush failed', ['tag' => $tag, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Slett all BBS-relatert cache
     */
    public static function flushAll(): bool
    {
        try {
            if (self::supportsTags()) {
                $tags = [
                    self::TAG_MESSAGES,
                    self::TAG_USERS,
                    self::TAG_FORUMS,
                    self::TAG_FILES,
                    self::TAG_STATS,
                    self::TAG_POLLS,
                    self::TAG_DOORS,
                    self::TAG_ANSI,
                    self::TAG_ADMIN,
                ];
                
                foreach ($tags as $tag) {
                    Cache::tags([$tag])->flush();
                }
            } else {
                Cache::flush();
            }
            
            Log::info('All BBS cache flushed');
            return true;
        } catch (\Exception $e) {
            Log::error('Cache flush all failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Sjekk om en nøkkel eksisterer i cache
     */
    public static function has(string $key, array $tags = []): bool
    {
        $fullKey = self::buildKey($key);
        
        if (!empty($tags) && self::supportsTags()) {
            return Cache::tags($tags)->has($fullKey);
        }
        
        return Cache::has($fullKey);
    }

    /**
     * Increment en numerisk verdi
     */
    public static function increment(string $key, int $value = 1): int|bool
    {
        $fullKey = self::buildKey($key);
        return Cache::increment($fullKey, $value);
    }

    /**
     * Decrement en numerisk verdi
     */
    public static function decrement(string $key, int $value = 1): int|bool
    {
        $fullKey = self::buildKey($key);
        return Cache::decrement($fullKey, $value);
    }

    // ===================
    // BBS-spesifikke metoder
    // ===================

    /**
     * Cache meldingstråd
     */
    public static function cacheThread(int $threadId, $data): bool
    {
        return self::put("thread:{$threadId}", $data, self::TTL_SHORT, [self::TAG_MESSAGES]);
    }

    /**
     * Hent cached meldingstråd
     */
    public static function getThread(int $threadId)
    {
        return self::get("thread:{$threadId}", null, [self::TAG_MESSAGES]);
    }

    /**
     * Cache forum-liste
     */
    public static function cacheForumList($data): bool
    {
        return self::put('forums:list', $data, self::TTL_MEDIUM, [self::TAG_FORUMS]);
    }

    /**
     * Hent cached forum-liste
     */
    public static function getForumList()
    {
        return self::get('forums:list', null, [self::TAG_FORUMS]);
    }

    /**
     * Cache bruker-profil
     */
    public static function cacheUserProfile(int $userId, $data): bool
    {
        return self::put("user:{$userId}:profile", $data, self::TTL_MEDIUM, [self::TAG_USERS]);
    }

    /**
     * Hent cached bruker-profil
     */
    public static function getUserProfile(int $userId)
    {
        return self::get("user:{$userId}:profile", null, [self::TAG_USERS]);
    }

    /**
     * Cache system-statistikk
     */
    public static function cacheStats(string $type, $data): bool
    {
        return self::put("stats:{$type}", $data, self::TTL_SHORT, [self::TAG_STATS]);
    }

    /**
     * Hent cached system-statistikk
     */
    public static function getStats(string $type)
    {
        return self::get("stats:{$type}", null, [self::TAG_STATS]);
    }

    /**
     * Cache online brukere
     */
    public static function cacheOnlineUsers($data): bool
    {
        return self::put('users:online', $data, 60, [self::TAG_USERS]); // 1 minutt
    }

    /**
     * Hent cached online brukere
     */
    public static function getOnlineUsers()
    {
        return self::get('users:online', null, [self::TAG_USERS]);
    }

    /**
     * Cache fil-kategori
     */
    public static function cacheFileCategory(int $categoryId, $data): bool
    {
        return self::put("files:category:{$categoryId}", $data, self::TTL_MEDIUM, [self::TAG_FILES]);
    }

    /**
     * Hent cached fil-kategori
     */
    public static function getFileCategory(int $categoryId)
    {
        return self::get("files:category:{$categoryId}", null, [self::TAG_FILES]);
    }

    /**
     * Cache ANSI art
     */
    public static function cacheAnsiArt(int $artId, $data): bool
    {
        return self::put("ansi:{$artId}", $data, self::TTL_LONG, [self::TAG_ANSI]);
    }

    /**
     * Hent cached ANSI art
     */
    public static function getAnsiArt(int $artId)
    {
        return self::get("ansi:{$artId}", null, [self::TAG_ANSI]);
    }

    /**
     * Cache poll resultater
     */
    public static function cachePollResults(int $pollId, $data): bool
    {
        return self::put("poll:{$pollId}:results", $data, self::TTL_SHORT, [self::TAG_POLLS]);
    }

    /**
     * Hent cached poll resultater
     */
    public static function getPollResults(int $pollId)
    {
        return self::get("poll:{$pollId}:results", null, [self::TAG_POLLS]);
    }

    // ===================
    // Invaliderings-metoder
    // ===================

    /**
     * Invalider all message-relatert cache
     */
    public static function invalidateMessages(): void
    {
        self::flushTag(self::TAG_MESSAGES);
    }

    /**
     * Invalider bruker-relatert cache
     */
    public static function invalidateUser(int $userId): void
    {
        self::forget("user:{$userId}:profile", [self::TAG_USERS]);
    }

    /**
     * Invalider forum-relatert cache
     */
    public static function invalidateForums(): void
    {
        self::flushTag(self::TAG_FORUMS);
    }

    /**
     * Invalider fil-relatert cache
     */
    public static function invalidateFiles(): void
    {
        self::flushTag(self::TAG_FILES);
    }

    /**
     * Invalider statistikk-cache
     */
    public static function invalidateStats(): void
    {
        self::flushTag(self::TAG_STATS);
    }

    // ===================
    // Hjelpefunksjoner
    // ===================

    /**
     * Bygg full cache-nøkkel med prefix
     */
    private static function buildKey(string $key): string
    {
        return 'punktet:' . $key;
    }

    /**
     * Sjekk om cache-driveren støtter tags
     */
    private static function supportsTags(): bool
    {
        $driver = config('cache.default');
        return in_array($driver, ['redis', 'memcached', 'array']);
    }

    /**
     * Hent cache-statistikk
     */
    public static function getInfo(): array
    {
        return [
            'driver' => config('cache.default'),
            'supports_tags' => self::supportsTags(),
            'prefix' => config('cache.prefix'),
        ];
    }
}
