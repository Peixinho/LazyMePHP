<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Static HTTP facade. Each method creates a fresh HttpClient.
 *
 *   Http::get('https://api.example.com/users');
 *   Http::post('https://api.example.com/users', ['name' => 'Alice']);
 *
 *   Http::withToken($jwt)->get('https://api.example.com/me');
 *   Http::withHeaders(['X-Api-Key' => $key])->timeout(5)->post($url, $payload);
 *   Http::withBasicAuth('user', 'pass')->get($url);
 */
class Http
{
    public static function new(): HttpClient
    {
        return new HttpClient();
    }

    public static function withHeaders(array $headers): HttpClient
    {
        return (new HttpClient())->withHeaders($headers);
    }

    public static function withToken(string $token, string $type = 'Bearer'): HttpClient
    {
        return (new HttpClient())->withToken($token, $type);
    }

    public static function withBasicAuth(string $user, string $password): HttpClient
    {
        return (new HttpClient())->withBasicAuth($user, $password);
    }

    public static function baseUrl(string $url): HttpClient
    {
        return (new HttpClient())->baseUrl($url);
    }

    public static function timeout(int $seconds): HttpClient
    {
        return (new HttpClient())->timeout($seconds);
    }

    public static function withoutVerifying(): HttpClient
    {
        return (new HttpClient())->withoutVerifying();
    }

    // -------------------------------------------------------------------------
    // Fake / testing
    // -------------------------------------------------------------------------

    /**
     * Enable fake mode. Pass URL pattern → HttpResponse stubs to return
     * pre-configured responses for matching requests.
     *
     *   Http::fake([
     *       'https://api.example.com/users'   => Http::response(['id' => 1], 200),
     *       'https://api.example.com/orders/*' => Http::response([], 404),
     *   ]);
     *
     * Unmatched URLs return 200 with an empty JSON body by default.
     */
    public static function fake(array $stubs = []): void
    {
        HttpClient::enableFake($stubs);
    }

    /** Reset fake mode. */
    public static function resetFake(): void
    {
        HttpClient::disableFake();
    }

    /** Build a stubbed HttpResponse to use in fake stubs. */
    public static function response(mixed $body = [], int $status = 200): HttpResponse
    {
        $raw = is_array($body) ? (json_encode($body) ?: '{}') : (string)$body;
        return new HttpResponse($status, $raw);
    }

    /**
     * Assert that at least one request was sent matching the callback.
     *
     *   Http::assertSent(fn($req) => $req['url'] === 'https://api.example.com/users');
     */
    public static function assertSent(callable $callback): void
    {
        foreach (HttpClient::recorded() as $req) {
            if ((bool)$callback($req)) return;
        }
        throw new \RuntimeException('Expected an HTTP request matching the callback, but none was found.');
    }

    /** Assert that no HTTP requests were made. */
    public static function assertNothingSent(): void
    {
        $count = count(HttpClient::recorded());
        if ($count > 0) {
            throw new \RuntimeException("Expected no HTTP requests, but {$count} were recorded.");
        }
    }

    /** Assert that exactly N requests were made. */
    public static function assertSentCount(int $expected): void
    {
        $actual = count(HttpClient::recorded());
        if ($actual !== $expected) {
            throw new \RuntimeException("Expected {$expected} HTTP request(s), but {$actual} were recorded.");
        }
    }

    /** Return all recorded requests. */
    public static function recorded(): array
    {
        return HttpClient::recorded();
    }

    // -------------------------------------------------------------------------
    // Static shortcuts
    // -------------------------------------------------------------------------

    public static function get(string $url, array $query = []): HttpResponse
    {
        return (new HttpClient())->get($url, $query);
    }

    public static function post(string $url, array|string $body = []): HttpResponse
    {
        return (new HttpClient())->post($url, $body);
    }

    public static function put(string $url, array|string $body = []): HttpResponse
    {
        return (new HttpClient())->put($url, $body);
    }

    public static function patch(string $url, array|string $body = []): HttpResponse
    {
        return (new HttpClient())->patch($url, $body);
    }

    public static function delete(string $url, array|string $body = []): HttpResponse
    {
        return (new HttpClient())->delete($url, $body);
    }
}
