<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Fluent HTTP client builder backed by cURL.
 *
 * Use the static Http facade for one-liners:
 *   Http::get('https://api.example.com/users');
 *
 * Use this class for requests that need custom headers, auth, or timeout:
 *   (new HttpClient)->withToken($token)->timeout(5)->post($url, ['name' => 'Alice']);
 */
class HttpClient
{
    private array   $headers = [];
    private int     $timeout = 30;
    private bool    $verify  = true;
    private ?string $baseUrl = null;

    // -------------------------------------------------------------------------
    // Fake / testing state (static — shared across all instances)
    // -------------------------------------------------------------------------

    private static bool   $fakeMode = false;
    /** @var array<string, HttpResponse> URL pattern → response */
    private static array  $stubs    = [];
    /** @var list<array{method: string, url: string, body: mixed}> */
    private static array  $recorded = [];

    public static function enableFake(array $stubs = []): void
    {
        self::$fakeMode = true;
        self::$stubs    = $stubs;
        self::$recorded = [];
    }

    public static function disableFake(): void
    {
        self::$fakeMode = false;
        self::$stubs    = [];
        self::$recorded = [];
    }

    public static function recorded(): array  { return self::$recorded; }
    public static function isFakeMode(): bool { return self::$fakeMode; }

    // -------------------------------------------------------------------------
    // Fluent configuration
    // -------------------------------------------------------------------------

    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeaders(['Authorization' => "{$type} {$token}"]);
    }

    public function withBasicAuth(string $user, string $password): static
    {
        return $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode("{$user}:{$password}"),
        ]);
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function withoutVerifying(): static
    {
        $this->verify = false;
        return $this;
    }

    public function baseUrl(string $url): static
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    // -------------------------------------------------------------------------
    // HTTP verbs
    // -------------------------------------------------------------------------

    public function get(string $url, array $query = []): HttpResponse
    {
        if ($query) $url .= '?' . http_build_query($query);
        return $this->send('GET', $url);
    }

    public function post(string $url, array|string $body = []): HttpResponse
    {
        return $this->send('POST', $url, $body);
    }

    public function put(string $url, array|string $body = []): HttpResponse
    {
        return $this->send('PUT', $url, $body);
    }

    public function patch(string $url, array|string $body = []): HttpResponse
    {
        return $this->send('PATCH', $url, $body);
    }

    public function delete(string $url, array|string $body = []): HttpResponse
    {
        return $this->send('DELETE', $url, $body);
    }

    // -------------------------------------------------------------------------
    // Core send
    // -------------------------------------------------------------------------

    private function send(string $method, string $url, array|string $body = []): HttpResponse
    {
        if ($this->baseUrl !== null && !str_starts_with($url, 'http')) {
            $url = $this->baseUrl . '/' . ltrim($url, '/');
        }

        // Intercept when fake mode is active
        if (self::$fakeMode) {
            self::$recorded[] = ['method' => $method, 'url' => $url, 'body' => $body];
            foreach (self::$stubs as $pattern => $response) {
                if (self::urlMatches($pattern, $url)) {
                    return $response;
                }
            }
            // No stub matched — return a default 200 with empty body
            return new HttpResponse(200, '{}');
        }

        $ch = curl_init();

        $rawBody      = null;
        $extraHeaders = [];
        if ($body !== [] && $body !== '') {
            if (is_array($body)) {
                $rawBody        = json_encode($body);
                $extraHeaders[] = 'Content-Type: application/json';
            } else {
                $rawBody = $body;
            }
            $extraHeaders[] = 'Content-Length: ' . strlen((string)$rawBody);
        }

        $curlHeaders = array_merge(
            array_map(fn($n, $v) => "{$n}: {$v}", array_keys($this->headers), $this->headers),
            $extraHeaders,
        );

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verify,
            CURLOPT_SSL_VERIFYHOST => $this->verify ? 2 : 0,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($rawBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        }

        $rawResponse = curl_exec($ch);
        $status      = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error       = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        return new HttpResponse($status, (string)$rawResponse);
    }

    /** Match a URL against a pattern (supports * wildcards). */
    private static function urlMatches(string $pattern, string $url): bool
    {
        if ($pattern === '*') return true;
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
        return (bool)preg_match($regex, $url);
    }
}
