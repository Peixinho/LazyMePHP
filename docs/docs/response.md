---
sidebar_position: 14
---

# HTTP Response

`Core\Http\Response` is a fluent, immutable value object for building HTTP responses that can be returned from controllers and middleware — unlike `JsonResponse` which exits immediately, `Response` lets you build, pass around, and send responses at the right moment.

## Quick start

```php
use Core\Http\Response;

// In a controller
return Response::json(['id' => $user->id], 201);

return Response::html('<h1>Hello</h1>');

return Response::redirect('/dashboard');

return Response::noContent();
```

Call `send()` to actually emit the response:

```php
$response = $controller->store($request);
if ($response instanceof Response) {
    $response->send();
}
```

## Factory methods

```php
Response::make(string $body, int $status, string $contentType)
Response::json(mixed $data, int $status = 200)
Response::html(string $body, int $status = 200)
Response::text(string $body, int $status = 200)
Response::redirect(string $url, int $status = 302)
Response::noContent()                  // 204
Response::notFound(string $body)       // 404
Response::forbidden(string $body)      // 403
Response::unauthorized(string $body)   // 401
```

## Fluent modifiers

All modifiers return a **new** `Response` instance — the original is unchanged:

```php
$response = Response::json(['id' => 1])
    ->withStatus(201)
    ->withHeader('X-Resource-Id', '1')
    ->withHeader('X-Request-Id', $requestId)
    ->withCookie('session', $token, ['secure' => true, 'httponly' => true]);
```

| Method | Description |
|---|---|
| `withStatus(int)` | Override HTTP status code |
| `withBody(string)` | Replace the response body |
| `withContentType(string)` | Override the Content-Type header |
| `withHeader(name, value)` | Add / replace a response header |
| `withCookie(name, value, options[])` | Queue a cookie to be set |

## Status predicates

```php
$r->isOk()          // 2xx
$r->isRedirect()    // 3xx
$r->isClientError() // 4xx
$r->isServerError() // 5xx
```

## In middleware

```php
class MaintenanceMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if ($this->maintenanceMode()) {
            return Response::json(['error' => 'Service unavailable'], 503)
                ->withHeader('Retry-After', '3600');
        }
        return $next($request);
    }
}
```

## Sending

```php
$response->send();
// Equivalent to:
// http_response_code($status);
// header('Content-Type: ...');
// foreach ($headers as $name => $value) header("$name: $value");
// foreach ($cookies as ...) setcookie(...);
// echo $body;
```

## Accessing properties

```php
$response->getStatus();       // int
$response->getBody();         // string
$response->getContentType();  // string
$response->getHeaders();      // array<string, string>
```
