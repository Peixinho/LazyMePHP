---
id: http-client
title: HTTP Client
sidebar_position: 12
---

# HTTP Client

LazyMePHP ships with a lightweight HTTP client backed by cURL. Use the static `Http` facade for one-liners, or chain fluent methods for more control.

## Quick start

```php
use Core\Http\Http;

$response = Http::get('https://api.example.com/users');

if ($response->ok()) {
    $users = $response->json();  // decoded array
}
```

## Sending requests

```php
Http::get('https://api.example.com/users');
Http::get('https://api.example.com/users', ['page' => 2, 'per_page' => 50]);  // query string

Http::post('https://api.example.com/users', ['name' => 'Alice', 'email' => 'alice@example.com']);
Http::put('https://api.example.com/users/1', ['name' => 'Alice Smith']);
Http::patch('https://api.example.com/users/1', ['active' => false]);
Http::delete('https://api.example.com/users/1');
```

Arrays are automatically JSON-encoded and `Content-Type: application/json` is set.
Pass a raw string to send an arbitrary body.

## Fluent builder

Use the builder when you need headers, auth, or timeout:

```php
// Bearer token
Http::withToken($accessToken)
    ->get('https://api.example.com/me');

// Custom headers
Http::withHeaders(['X-Api-Key' => $key, 'Accept' => 'application/json'])
    ->post('https://api.example.com/events', $payload);

// Basic auth
Http::withBasicAuth('user', 'password')
    ->get('https://api.example.com/private');

// Timeout (seconds)
Http::timeout(5)->get('https://slow-api.example.com/data');

// Base URL — subsequent paths are resolved relative to it
Http::baseUrl('https://api.example.com')
    ->withToken($token)
    ->get('/users');          // → https://api.example.com/users

// Disable SSL verification (dev only)
Http::withoutVerifying()->get($url);

// Chain everything
$client = Http::baseUrl('https://api.stripe.com/v1')
    ->withBasicAuth($stripeKey, '')
    ->timeout(10);

$response = $client->post('/charges', ['amount' => 2000, 'currency' => 'usd']);
```

## HttpResponse

| Method | Returns | Description |
|---|---|---|
| `status()` | `int` | HTTP status code |
| `ok()` | `bool` | True when 2xx |
| `failed()` | `bool` | True when not 2xx |
| `clientError()` | `bool` | True when 4xx |
| `serverError()` | `bool` | True when 5xx |
| `json()` | `mixed` | Decoded JSON body, or null on failure |
| `body()` | `string` | Raw response body |
| `throw()` | `static` | Throws `HttpException` on 4xx/5xx, returns `$this` on success |
| `onError($default)` | `mixed` | Returns `$default` when `failed()`, `$this` otherwise |

```php
// Throw on error
$data = Http::get($url)->throw()->json();

// Default on error
$result = Http::get($url)->onError([]);

// Status check
$res = Http::post($url, $body);
if ($res->clientError()) {
    // 4xx — validation error, auth failure, etc.
}
```

## Testing with `Http::fake()`

Swap real HTTP calls for in-memory stubs in your tests:

```php
Http::fake([
    'https://api.example.com/users'    => Http::response(['id' => 1, 'name' => 'Alice'], 200),
    'https://api.example.com/orders/*' => Http::response(['error' => 'not found'], 404),
]);

// Code under test runs normally — no real HTTP calls are made
$service->createUser('Alice');

// Assert what was sent
Http::assertSent(fn($req) => $req['url'] === 'https://api.example.com/users'
                          && $req['method'] === 'POST');

Http::assertSentCount(1);

// Unmatched URLs return 200 with '{}'
// Reset after each test
Http::resetFake();
```

Assertion methods:

| Method | Description |
|---|---|
| `Http::assertSent(callable)` | At least one recorded request satisfies the callback |
| `Http::assertNothingSent()` | No requests were recorded |
| `Http::assertSentCount(int)` | Exactly N requests were recorded |
| `Http::recorded()` | Returns all recorded request arrays |
