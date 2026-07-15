---
id: cache
title: Cache
sidebar_position: 8
---

# Cache

A unified cache layer backs query caching, rate limiting, and session-level storage. Switch drivers in `.env` without changing any application code.

## Configuration

```env
CACHE_DRIVER=redis    # array (default) | apcu | redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
```

| Driver | Description |
|---|---|
| `array` | In-process PHP array. Zero dependencies, data lost between requests. Good for development. |
| `apcu` | APCu shared memory. Persistent across requests, shared within a single server. |
| `redis` | Redis. Persistent across requests and servers. Recommended for production and multi-worker setups. |

## Usage

```php
use Core\Cache\Cache;

// Store
Cache::set('key', $value, 3600);   // TTL in seconds

// Retrieve
$value = Cache::get('key');         // null if missing or expired
$value = Cache::get('key', 'default');

// Check
Cache::has('key');                  // bool

// Delete
Cache::delete('key');

// Flush everything
Cache::flush();

// Atomic increment (useful for rate limiting and counters)
$hits = Cache::increment('api:hits', 1, 60);  // increment by 1, TTL 60s

// Get-or-compute
$users = Cache::remember('all-users', 300, fn() =>
    Model::query('users')->get()
);
```

## Query caching

The same driver backs `ModelQuery::remember()`:

```php
$rows = Model::query('orders')
    ->where('status', 'open')
    ->remember(120)
    ->get();
```

Switching `CACHE_DRIVER=redis` makes all query caches persistent across workers automatically — no other code change needed.

## Testing

```php
use Core\Cache\Cache;
use Core\Cache\ArrayStore;

// Swap in a fresh in-memory store before each test
Cache::swap(new ArrayStore());

// ... exercise code that uses Cache

// Reset to the env-configured driver
Cache::reset();
```
