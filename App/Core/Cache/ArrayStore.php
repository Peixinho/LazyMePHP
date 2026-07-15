<?php

declare(strict_types=1);

namespace Core\Cache;

class ArrayStore implements CacheStore
{
    private array $store = [];

    public function get(string $key): mixed
    {
        if (!isset($this->store[$key])) return null;
        ['expires' => $exp, 'value' => $val] = $this->store[$key];
        if ($exp !== null && $exp < time()) {
            unset($this->store[$key]);
            return null;
        }
        return $val;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->store[$key] = [
            'expires' => $ttl > 0 ? time() + $ttl : null,
            'value'   => $value,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function increment(string $key, int $by = 1, int $ttl = 60): int
    {
        $current = (int) ($this->get($key) ?? 0);
        $new     = $current + $by;
        if ($current === 0) {
            $this->set($key, $new, $ttl);
        } else {
            $this->store[$key]['value'] = $new;
        }
        return $new;
    }

    public function flush(): void
    {
        $this->store = [];
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
