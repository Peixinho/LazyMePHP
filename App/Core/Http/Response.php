<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Fluent HTTP response that can be returned from controllers and middleware.
 *
 * Unlike JsonResponse (which sends immediately via exit), Response is a
 * value object — build it, return it, and let the framework call send().
 *
 *   return Response::json(['id' => $user->id], 201)
 *       ->withHeader('X-Resource-Id', (string)$user->id);
 *
 *   return Response::html('<h1>Hello</h1>');
 *
 *   return Response::redirect('/dashboard', 302);
 *
 *   return Response::noContent();
 *
 * In routes / controllers, call send() yourself or let the router do it:
 *
 *   $response = $controller->store($request);
 *   if ($response instanceof Response) $response->send();
 */
class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<int, array{name:string,value:string,options:array<string,mixed>}> */
    private array $cookies = [];

    public function __construct(
        private string $body       = '',
        private int    $status     = 200,
        private string $contentType = 'text/html; charset=utf-8',
    ) {}

    // -------------------------------------------------------------------------
    // Static factories
    // -------------------------------------------------------------------------

    public static function make(string $body = '', int $status = 200, string $contentType = 'text/html; charset=utf-8'): static
    {
        return new static($body, $status, $contentType);
    }

    /** @param mixed $data Any JSON-serialisable value */
    public static function json(mixed $data, int $status = 200): static
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return new static($body, $status, 'application/json; charset=utf-8');
    }

    public static function html(string $body, int $status = 200): static
    {
        return new static($body, $status, 'text/html; charset=utf-8');
    }

    public static function text(string $body, int $status = 200): static
    {
        return new static($body, $status, 'text/plain; charset=utf-8');
    }

    public static function redirect(string $url, int $status = 302): static
    {
        return (new static('', $status))->withHeader('Location', $url);
    }

    public static function noContent(): static
    {
        return new static('', 204);
    }

    public static function notFound(string $body = 'Not Found'): static
    {
        return new static($body, 404, 'text/plain; charset=utf-8');
    }

    public static function forbidden(string $body = 'Forbidden'): static
    {
        return new static($body, 403, 'text/plain; charset=utf-8');
    }

    public static function unauthorized(string $body = 'Unauthorized'): static
    {
        return new static($body, 401, 'text/plain; charset=utf-8');
    }

    // -------------------------------------------------------------------------
    // Fluent modifiers (all return new instances — immutable)
    // -------------------------------------------------------------------------

    public function withStatus(int $status): static
    {
        $clone         = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withBody(string $body): static
    {
        $clone       = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function withContentType(string $contentType): static
    {
        $clone              = clone $this;
        $clone->contentType = $contentType;
        return $clone;
    }

    public function withHeader(string $name, string $value): static
    {
        $clone                  = clone $this;
        $clone->headers[$name]  = $value;
        return $clone;
    }

    /** @param array<string,mixed> $options  Keys: expires, path, domain, secure, httponly, samesite */
    public function withCookie(string $name, string $value, array $options = []): static
    {
        $clone           = clone $this;
        $clone->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
        return $clone;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getStatus(): int     { return $this->status; }
    public function getBody(): string    { return $this->body; }
    public function getContentType(): string { return $this->contentType; }

    /** @return array<string, string> */
    public function getHeaders(): array  { return $this->headers; }

    public function isRedirect(): bool   { return $this->status >= 300 && $this->status < 400; }
    public function isOk(): bool         { return $this->status >= 200 && $this->status < 300; }
    public function isClientError(): bool { return $this->status >= 400 && $this->status < 500; }
    public function isServerError(): bool { return $this->status >= 500; }

    // -------------------------------------------------------------------------
    // Sending
    // -------------------------------------------------------------------------

    /** Emit status, headers, cookies, and body to the PHP output buffer. */
    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: ' . $this->contentType);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        foreach ($this->cookies as $cookie) {
            $opts = array_merge(['path' => '/', 'httponly' => true, 'samesite' => 'Lax'], $cookie['options']);
            setcookie($cookie['name'], $cookie['value'], $opts);
        }

        echo $this->body;
    }
}
