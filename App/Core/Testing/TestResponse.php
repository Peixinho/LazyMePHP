<?php

declare(strict_types=1);

namespace Core\Testing;

use Core\Http\Response;

/**
 * Wraps a controller/handler response with fluent assertion helpers.
 *
 * Returned by MakesHttpRequests trait methods:
 *
 *   $response = $this->postJson(fn($req) => $controller->store($req), '/api/users', $data);
 *
 *   $response->assertCreated()
 *            ->assertJsonFragment(['name' => 'Alice'])
 *            ->assertHeader('Content-Type', 'application/json');
 */
class TestResponse
{
    private string $body;
    private int    $status;
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $body, int $status = 200, array $headers = [])
    {
        $this->body    = $body;
        $this->status  = $status;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public static function fromResponse(Response $r): static
    {
        return new static($r->getBody(), $r->getStatus(), $r->getHeaders());
    }

    public static function fromRaw(mixed $value): static
    {
        if ($value instanceof Response) {
            return static::fromResponse($value);
        }
        if (is_array($value) || is_object($value)) {
            return new static(
                (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                200,
                ['content-type' => 'application/json; charset=utf-8'],
            );
        }
        return new static((string)$value, 200);
    }

    // -------------------------------------------------------------------------
    // Status assertions
    // -------------------------------------------------------------------------

    public function assertStatus(int $expected): static
    {
        expect($this->status)->toBe($expected);
        return $this;
    }

    public function assertOk(): static          { return $this->assertStatus(200); }
    public function assertCreated(): static     { return $this->assertStatus(201); }
    public function assertAccepted(): static    { return $this->assertStatus(202); }
    public function assertNoContent(): static   { return $this->assertStatus(204); }
    public function assertNotFound(): static    { return $this->assertStatus(404); }
    public function assertForbidden(): static   { return $this->assertStatus(403); }
    public function assertUnauthorized(): static { return $this->assertStatus(401); }
    public function assertUnprocessable(): static { return $this->assertStatus(422); }
    public function assertServerError(): static { return $this->assertStatus(500); }

    public function assertSuccessful(): static
    {
        expect($this->status)->toBeGreaterThanOrEqual(200);
        expect($this->status)->toBeLessThan(300);
        return $this;
    }

    public function assertRedirect(?string $url = null): static
    {
        expect($this->status)->toBeGreaterThanOrEqual(300);
        expect($this->status)->toBeLessThan(400);
        if ($url !== null) {
            $this->assertHeader('location', $url);
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // JSON assertions
    // -------------------------------------------------------------------------

    public function assertJson(array $expected): static
    {
        $decoded = $this->json();
        expect($decoded)->toMatchArray($expected);
        return $this;
    }

    public function assertJsonFragment(array $fragment): static
    {
        $decoded = $this->json();
        expect($decoded)->toMatchArray($fragment);
        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): static
    {
        $value = $this->jsonPath($path);
        expect($value)->toBe($expected);
        return $this;
    }

    public function assertJsonCount(int $count, ?string $key = null): static
    {
        $data = $key !== null ? $this->jsonPath($key) : $this->json();
        expect($data)->toHaveCount($count);
        return $this;
    }

    public function assertJsonStructure(array $structure): static
    {
        $this->assertStructure($this->json(), $structure);
        return $this;
    }

    public function assertJsonMissing(array $data): static
    {
        $decoded = $this->json();
        foreach ($data as $key => $value) {
            expect($decoded)->not->toHaveKey($key);
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // Body assertions
    // -------------------------------------------------------------------------

    public function assertSee(string $text): static
    {
        expect($this->body)->toContain($text);
        return $this;
    }

    public function assertDontSee(string $text): static
    {
        expect($this->body)->not->toContain($text);
        return $this;
    }

    public function assertBodyIs(string $expected): static
    {
        expect($this->body)->toBe($expected);
        return $this;
    }

    public function assertEmpty(): static
    {
        expect($this->body)->toBe('');
        return $this;
    }

    // -------------------------------------------------------------------------
    // Header assertions
    // -------------------------------------------------------------------------

    public function assertHeader(string $name, ?string $value = null): static
    {
        $key = strtolower($name);
        expect($this->headers)->toHaveKey($key);
        if ($value !== null) {
            expect($this->headers[$key])->toContain($value);
        }
        return $this;
    }

    public function assertHeaderMissing(string $name): static
    {
        expect($this->headers)->not->toHaveKey(strtolower($name));
        return $this;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function json(?string $key = null): mixed
    {
        $decoded = json_decode($this->body, true);
        if ($key === null) return $decoded;
        return $this->extractPath($decoded, $key);
    }

    public function body(): string  { return $this->body; }
    public function status(): int   { return $this->status; }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array { return $this->headers; }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function jsonPath(string $path): mixed
    {
        return $this->extractPath($this->json(), $path);
    }

    private function extractPath(mixed $data, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (!is_array($data)) return null;
            $data = $data[$segment] ?? null;
        }
        return $data;
    }

    private function assertStructure(mixed $data, array $structure): void
    {
        foreach ($structure as $key => $value) {
            if (is_int($key)) {
                // Numeric key — check the field exists
                expect($data)->toHaveKey($value);
            } else {
                expect($data)->toHaveKey($key);
                if (is_array($value)) {
                    $this->assertStructure($data[$key], $value);
                }
            }
        }
    }
}
