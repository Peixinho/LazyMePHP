<?php

declare(strict_types=1);

namespace Core\Cache;

interface CacheStore
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl): void;
    public function delete(string $key): void;
    public function increment(string $key, int $by = 1, int $ttl = 60): int;
    public function flush(): void;
    public function has(string $key): bool;
}
