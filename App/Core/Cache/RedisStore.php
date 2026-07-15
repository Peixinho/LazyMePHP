<?php

declare(strict_types=1);

namespace Core\Cache;

class RedisStore implements CacheStore
{
    private \Redis $redis;

    public function __construct(string $host = '127.0.0.1', int $port = 6379, string $password = '', int $db = 0)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('The redis PHP extension is required to use the Redis cache driver.');
        }

        $this->redis = new \Redis();
        $this->redis->connect($host, $port);
        if ($password !== '') {
            $this->redis->auth($password);
        }
        if ($db !== 0) {
            $this->redis->select($db);
        }
    }

    public function get(string $key): mixed
    {
        $val = $this->redis->get($key);
        if ($val === false) return null;
        return unserialize($val);
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, serialize($value));
        } else {
            $this->redis->set($key, serialize($value));
        }
    }

    public function delete(string $key): void
    {
        $this->redis->del($key);
    }

    public function increment(string $key, int $by = 1, int $ttl = 60): int
    {
        $new = (int) $this->redis->incrBy($key, $by);
        if ($new === $by) {
            // First increment — set TTL
            $this->redis->expire($key, $ttl);
        }
        return $new;
    }

    public function flush(): void
    {
        $this->redis->flushDB();
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }

    public function connection(): \Redis
    {
        return $this->redis;
    }
}
