<?php

declare(strict_types=1);

namespace Core\Cache;

/**
 * Cache facade.
 *
 * Driver is selected by CACHE_DRIVER env var: redis | apcu | array (default).
 *
 * Redis options: REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB
 *
 *   Cache::get('key')
 *   Cache::set('key', $value, 60)
 *   Cache::remember('key', 60, fn() => expensiveQuery())
 *   Cache::increment('rate:ip:127.0.0.1', 1, 60)
 */
class Cache
{
    private static ?CacheStore $instance = null;

    public static function store(): CacheStore
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = strtolower($_ENV['CACHE_DRIVER'] ?? 'array');

        self::$instance = match ($driver) {
            'redis' => new RedisStore(
                host:     $_ENV['REDIS_HOST']     ?? '127.0.0.1',
                port:     (int) ($_ENV['REDIS_PORT']     ?? 6379),
                password: $_ENV['REDIS_PASSWORD'] ?? '',
                db:       (int) ($_ENV['REDIS_DB']       ?? 0),
            ),
            'apcu'  => function_exists('apcu_fetch')
                        ? new ApcuStore()
                        : new ArrayStore(),
            default => new ArrayStore(),
        };

        // Warn in production: ArrayStore is per-process and per-request.
        // Rate limiting, query caching, and session-independent state will not
        // persist across PHP-FPM workers or between requests.
        if (self::$instance instanceof ArrayStore
            && strtolower($_ENV['APP_ENV'] ?? 'local') === 'production'
        ) {
            trigger_error(
                'CACHE_DRIVER is unset or "array" in production. '
                . 'Cache data is lost between requests and not shared across workers. '
                . 'Set CACHE_DRIVER=redis or CACHE_DRIVER=apcu.',
                E_USER_WARNING
            );
        }

        return self::$instance;
    }

    /** For tests — swap the underlying store. */
    public static function swap(CacheStore $store): void
    {
        self::$instance = $store;
    }

    /** Reset to re-read env (useful between tests). */
    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function get(string $key): mixed
    {
        if (class_exists('Core\Debug\Profiler')) {
            \Core\Debug\Profiler::start('cache', "get:{$key}");
        }
        $result = self::store()->get($key);
        if (class_exists('Core\Debug\Profiler')) {
            \Core\Debug\Profiler::stop();
        }
        return $result;
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): void
    {
        if (class_exists('Core\Debug\Profiler')) {
            \Core\Debug\Profiler::start('cache', "set:{$key}");
        }
        self::store()->set($key, $value, $ttl);
        if (class_exists('Core\Debug\Profiler')) {
            \Core\Debug\Profiler::stop();
        }
    }

    public static function delete(string $key): void
    {
        self::store()->delete($key);
    }

    public static function has(string $key): bool
    {
        return self::store()->has($key);
    }

    public static function flush(): void
    {
        self::store()->flush();
    }

    public static function increment(string $key, int $by = 1, int $ttl = 60): int
    {
        return self::store()->increment($key, $by, $ttl);
    }

    /**
     * Get from cache, or execute $callback, store, and return the result.
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (class_exists('Core\Debug\Profiler')) {
            \Core\Debug\Profiler::start('cache', "remember:{$key}");
        }

        $cached = self::store()->get($key);
        if ($cached !== null) {
            if (class_exists('Core\Debug\Profiler')) {
                \Core\Debug\Profiler::stop();
            }
            return $cached;
        }

        $value = $callback();
        self::store()->set($key, $value, $ttl);

        if (class_exists('Core\Debug\Profiler')) {
            \Core\Debug\Profiler::stop();
        }
        return $value;
    }
}
