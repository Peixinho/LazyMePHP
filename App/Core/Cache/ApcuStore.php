<?php

declare(strict_types=1);

namespace Core\Cache;

class ApcuStore implements CacheStore
{
    public function get(string $key): mixed
    {
        $val = apcu_fetch($key, $ok);
        return $ok ? $val : null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        apcu_store($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        apcu_delete($key);
    }

    public function increment(string $key, int $by = 1, int $ttl = 60): int
    {
        // apcu_inc doesn't set TTL on first creation; use add then inc
        apcu_add($key, 0, $ttl);
        return (int) apcu_inc($key, $by);
    }

    public function flush(): void
    {
        apcu_clear_cache();
    }

    public function has(string $key): bool
    {
        return apcu_exists($key);
    }
}
