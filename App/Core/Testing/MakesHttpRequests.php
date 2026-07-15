<?php

declare(strict_types=1);

namespace Core\Testing;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Trait for making HTTP requests in Pest tests.
 *
 * Usage in a test file:
 *
 *   uses(\Core\Testing\MakesHttpRequests::class);
 *
 * Then in tests:
 *
 *   $response = $this->postJson(
 *       fn($req) => (new UserController())->store($req),
 *       '/api/users',
 *       ['name' => 'Alice', 'email' => 'alice@example.com'],
 *   );
 *   $response->assertCreated()->assertJsonFragment(['name' => 'Alice']);
 *
 *   // Auth
 *   $this->withToken($jwtToken)->get(fn($req) => ..., '/api/me');
 *   $this->actingAs($user)->get(fn($req) => ..., '/api/me');
 *
 *   // Extra headers
 *   $this->withHeader('X-Tenant-Id', '42')->get(...);
 */
trait MakesHttpRequests
{
    /** @var array<string, string> */
    private array $requestHeaders = [];
    private ?string $bearerToken  = null;

    // -------------------------------------------------------------------------
    // Auth helpers
    // -------------------------------------------------------------------------

    public function withToken(string $token): static
    {
        $this->bearerToken = $token;
        return $this;
    }

    /**
     * Set the bearer token from an array or object that has a `token` (or `access_token`) field.
     *
     * @param array<string,mixed>|object $user
     */
    public function actingAs(array|object $user, string $tokenField = 'token'): static
    {
        $tok = is_array($user) ? ($user[$tokenField] ?? $user['access_token'] ?? '') : ($user->$tokenField ?? $user->access_token ?? '');
        $this->bearerToken = (string)$tok;
        return $this;
    }

    public function withHeader(string $name, string $value): static
    {
        $this->requestHeaders[$name] = $value;
        return $this;
    }

    // -------------------------------------------------------------------------
    // HTTP methods
    // -------------------------------------------------------------------------

    public function get(callable $handler, string $uri, array $params = []): TestResponse
    {
        return $this->call($handler, 'GET', $uri, $params);
    }

    public function post(callable $handler, string $uri, array $data = []): TestResponse
    {
        return $this->call($handler, 'POST', $uri, $data);
    }

    public function put(callable $handler, string $uri, array $data = []): TestResponse
    {
        return $this->call($handler, 'PUT', $uri, $data);
    }

    public function patch(callable $handler, string $uri, array $data = []): TestResponse
    {
        return $this->call($handler, 'PATCH', $uri, $data);
    }

    public function delete(callable $handler, string $uri, array $data = []): TestResponse
    {
        return $this->call($handler, 'DELETE', $uri, $data);
    }

    // -------------------------------------------------------------------------
    // JSON convenience (sets Content-Type: application/json)
    // -------------------------------------------------------------------------

    public function getJson(callable $handler, string $uri, array $params = []): TestResponse
    {
        return $this->call($handler, 'GET', $uri, $params, ['Accept' => 'application/json']);
    }

    public function postJson(callable $handler, string $uri, array $data = []): TestResponse
    {
        return $this->call($handler, 'POST', $uri, $data, [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ]);
    }

    public function putJson(callable $handler, string $uri, array $data = []): TestResponse
    {
        return $this->call($handler, 'PUT', $uri, $data, [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ]);
    }

    public function patchJson(callable $handler, string $uri, array $data = []): TestResponse
    {
        return $this->call($handler, 'PATCH', $uri, $data, [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ]);
    }

    public function deleteJson(callable $handler, string $uri): TestResponse
    {
        return $this->call($handler, 'DELETE', $uri, [], [
            'Accept' => 'application/json',
        ]);
    }

    // -------------------------------------------------------------------------
    // Core dispatch
    // -------------------------------------------------------------------------

    /**
     * Build a Request, invoke the handler, and wrap the result in TestResponse.
     *
     * @param callable(Request): mixed $handler
     * @param array<string, string>    $extraHeaders
     */
    public function call(callable $handler, string $method, string $uri, array $data = [], array $extraHeaders = []): TestResponse
    {
        $request = $this->buildRequest($method, $uri, $data, $extraHeaders);
        $result  = $handler($request);

        // Reset per-request state
        $this->requestHeaders = [];
        $this->bearerToken    = null;

        return TestResponse::fromRaw($result);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function buildRequest(string $method, string $uri, array $data, array $extraHeaders): Request
    {
        $allHeaders = array_merge($this->requestHeaders, $extraHeaders);
        if ($this->bearerToken !== null) {
            $allHeaders['Authorization'] = 'Bearer ' . $this->bearerToken;
        }

        // Build $_SERVER-style array
        $server = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI'    => $uri,
            'SERVER_NAME'    => 'localhost',
            'HTTP_HOST'      => 'localhost',
        ];

        foreach ($allHeaders as $name => $value) {
            $upper = strtoupper(str_replace('-', '_', $name));
            $key   = in_array($upper, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)
                ? $upper
                : 'HTTP_' . $upper;
            $server[$key] = $value;
        }

        $isJson  = str_contains($allHeaders['Content-Type'] ?? '', 'json');
        $content = $isJson && $data !== [] ? json_encode($data) : null;
        $query   = strtoupper($method) === 'GET' ? $data : [];
        $post    = !$isJson && strtoupper($method) !== 'GET' ? $data : [];

        return Request::create($uri, $method, array_merge($query, $post), [], [], $server, $content);
    }
}
