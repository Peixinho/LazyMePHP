---
id: rate-limiting
title: Rate Limiting
sidebar_position: 1
---

# Rate Limiting

`Core\Http\RateLimit` is a middleware that limits the number of requests a client can make within a time window. It uses the configured [cache driver](../configuration) for storage, so limits are shared across workers when using Redis or APCu.

## Basic usage

```php
use Core\Http\RateLimit;

// 60 requests per minute per IP (default key resolver)
$router->post('/api/contact', [ContactController::class, 'send'])
       ->addMiddleware(new RateLimit(60, 60));
```

## Custom key resolver

```php
use Core\Auth\Auth;

// 5 requests per minute per authenticated user ID
$router->post('/api/ai', [AiController::class, 'complete'])
       ->addMiddleware(new RateLimit(5, 60, fn() => Auth::id() ?? 'anon'));
```

The key resolver is a callable that returns a string identifier. Use it to rate-limit per user, per API key, or any other dimension.

## Response when exceeded

When the limit is exceeded the middleware returns `429 Too Many Requests` with these headers:

```
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1720000060
Retry-After: 42
```

## Constructor

```php
new RateLimit(
    int      $maxRequests,   // maximum hits allowed in the window
    int      $windowSeconds, // window length in seconds
    callable $keyResolver    // optional — defaults to client IP
)
```
