---
sidebar_position: 9
---

# Middleware

Middleware wraps HTTP handlers in a pipeline. Each layer can inspect or modify the request before passing it on, and inspect or modify the response on the way back.

---

## Built-in middleware

### `AuthMiddleware`

Guards routes with Bearer JWT or static API token authentication.

```php
use Core\Http\Middleware\AuthMiddleware;

Pipeline::send($request)
    ->through([AuthMiddleware::class])
    ->then($handler);
```

Configure via environment variables:

| Variable | Description |
|----------|-------------|
| `AUTH_GUARD` | `jwt` (default) or `token` |
| `JWT_SECRET` | Secret key for HMAC-SHA256 JWT verification |
| `API_TOKEN` | Static token for `AUTH_GUARD=token` |

Returns **401 Unauthorized** when authentication fails.

### `CorsMiddleware`

Sets CORS response headers and handles `OPTIONS` preflight requests.

| Variable | Default |
|----------|---------|
| `CORS_ORIGINS` | `*` |
| `CORS_METHODS` | `GET,POST,PUT,PATCH,DELETE,OPTIONS` |
| `CORS_HEADERS` | `Content-Type,Authorization,X-Requested-With` |
| `CORS_MAX_AGE` | `86400` |
| `CORS_CREDENTIALS` | `false` |

### `ThrottleMiddleware`

Per-IP rate limiting using APCu (falls back to in-memory for single requests).

| Variable | Default |
|----------|---------|
| `THROTTLE_MAX_REQUESTS` | `60` |
| `THROTTLE_WINDOW` | `60` (seconds) |

Adds `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` headers. Returns **429 Too Many Requests** when the limit is exceeded.

### `MaintenanceMiddleware`

Blocks all requests when the app is in maintenance mode. See [Maintenance Mode](./maintenance.md).

### `SecurityHeadersMiddleware`

Adds secure HTTP headers: `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, and a strict `Content-Security-Policy`.

---

## Pipeline

The `Pipeline` class runs middleware in order:

```php
use Core\Http\Middleware\Pipeline;
use Core\Http\Middleware\MaintenanceMiddleware;
use Core\Http\Middleware\CorsMiddleware;
use Core\Http\Middleware\AuthMiddleware;

$response = Pipeline::send($request)
    ->through([
        MaintenanceMiddleware::class,
        CorsMiddleware::class,
        AuthMiddleware::class,
    ])
    ->then(function (Request $req) {
        // your actual handler
        return Response::json(['ok' => true]);
    });
```

---

## Writing custom middleware

```bash
php LazyMePHP make:middleware RequiresSubscription
# → App/Http/Middleware/RequiresSubscription.php
```

The scaffold produces:

```php
class RequiresSubscription
{
    public function handle(Request $request, callable $next): mixed
    {
        // Check before passing to the next handler:
        $user = Auth::user();
        if (!$user || !$user->hasActiveSubscription()) {
            abort(402, 'Subscription required.');
        }

        $response = $next($request);

        // Optionally inspect / modify $response here.

        return $response;
    }
}
```

Register it in your pipeline:

```php
Pipeline::send($request)
    ->through([
        CorsMiddleware::class,
        AuthMiddleware::class,
        RequiresSubscription::class,
    ])
    ->then($handler);
```

---

## Middleware groups (manual)

There's no automatic group registration — just compose arrays:

```php
$public = [CorsMiddleware::class, MaintenanceMiddleware::class];
$auth   = [...$public, AuthMiddleware::class, ThrottleMiddleware::class];
$admin  = [...$auth, RequiresSubscription::class];

// Use per-route:
Pipeline::send($request)->through($admin)->then($adminHandler);
```
