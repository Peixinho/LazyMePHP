<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Typed wrapper around the current HTTP request.
 *
 *   $request = Request::capture();
 *
 *   $name   = $request->input('name', 'Guest');
 *   $file   = $request->file('avatar');
 *   $token  = $request->bearerToken();
 *   $isJson = $request->wantsJson();
 */
class Request
{
    private array $query;
    private array $post;
    private array $server;
    private array $cookies;
    private array $files;
    private array $headers;
    private ?string $content;

    /** When true, get()/post() read from superglobals at call time (backward compat). */
    private bool $liveMode = false;

    public function __construct(
        array   $query   = [],
        array   $post    = [],
        array   $server  = [],
        array   $cookies = [],
        array   $files   = [],
        ?string $content = null,
    ) {
        // Detect no-arg construction → live mode for backward compatibility
        $this->liveMode = (func_num_args() === 0);

        $this->query   = $query;
        $this->post    = $post;
        $this->server  = $server;
        $this->cookies = $cookies;
        $this->files   = $files;
        $this->content = $content;
        $this->headers = $this->extractHeaders($this->liveMode ? $_SERVER : $server);
    }

    /** Build a Request from PHP's superglobals. */
    public static function capture(): static
    {
        $content = file_get_contents('php://input') ?: null;
        return new static($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES, $content);
    }

    /** Build a Request manually (useful in tests). */
    public static function create(
        string  $uri     = '/',
        string  $method  = 'GET',
        array   $params  = [],
        array   $cookies = [],
        array   $files   = [],
        array   $server  = [],
        ?string $content = null,
    ): static {
        $method = strtoupper($method);
        $server = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $uri,
            'SERVER_NAME'    => 'localhost',
            'HTTP_HOST'      => 'localhost',
        ], $server);

        $query = $post = [];
        if ($method === 'GET') {
            $query = $params;
        } else {
            $post = $params;
        }

        return new static($query, $post, $server, $cookies, $files, $content);
    }

    // -------------------------------------------------------------------------
    // Input
    // -------------------------------------------------------------------------

    /** Merge of query string + POST body + JSON body. */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->jsonBody());
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function filled(string $key): bool
    {
        $val = $this->input($key);
        return $val !== null && $val !== '' && $val !== [];
    }

    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    /** Query string only. */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    /** Alias for query() — in live mode reads $_GET at call time (backward compat). */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($this->liveMode) {
            return $key === null ? $_GET : ($_GET[$key] ?? $default);
        }
        return $this->query($key, $default);
    }

    /** Return decoded JSON body (null when body is not JSON). */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $body = $this->jsonBody();
        if ($key === null) return $body;
        return $body[$key] ?? $default;
    }

    /** POST body only — in live mode reads $_POST at call time (backward compat). */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($this->liveMode) {
            return $key === null ? $_POST : ($_POST[$key] ?? $default);
        }
        return $key === null ? $this->post : ($this->post[$key] ?? $default);
    }

    // -------------------------------------------------------------------------
    // Files
    // -------------------------------------------------------------------------

    public function file(string $key): ?UploadedFile
    {
        if (!isset($this->files[$key])) return null;
        $f = $this->files[$key];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        return new UploadedFile($f);
    }

    public function hasFile(string $key): bool
    {
        return $this->file($key)?->isValid() === true;
    }

    // -------------------------------------------------------------------------
    // Method
    // -------------------------------------------------------------------------

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function isGet(): bool    { return $this->isMethod('GET'); }
    public function isPost(): bool   { return $this->isMethod('POST'); }
    public function isPut(): bool    { return $this->isMethod('PUT'); }
    public function isPatch(): bool  { return $this->isMethod('PATCH'); }
    public function isDelete(): bool { return $this->isMethod('DELETE'); }

    // -------------------------------------------------------------------------
    // Path / URL
    // -------------------------------------------------------------------------

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return strtok($uri, '?') ?: '/';
    }

    public function url(): string
    {
        $scheme = (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $this->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $this->path();
    }

    public function fullUrl(): string
    {
        $scheme = (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $this->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . ($this->server['REQUEST_URI'] ?? '/');
    }

    /** Check path against one or more patterns (supports * wildcards). */
    public function is(string ...$patterns): bool
    {
        foreach ($patterns as $pattern) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $this->path())) return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Headers
    // -------------------------------------------------------------------------

    public function header(string $name, mixed $default = null): mixed
    {
        $key = strtolower(str_replace(['-', '_'], '-', $name));
        return $this->headers[$key] ?? $default;
    }

    public function hasHeader(string $name): bool
    {
        return $this->header($name) !== null;
    }

    /** Extract the Bearer token from Authorization header. */
    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');
        if (str_starts_with((string)$auth, 'Bearer ')) {
            return substr((string)$auth, 7);
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // IP
    // -------------------------------------------------------------------------

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['REMOTE_ADDR']
            ?? '127.0.0.1';
    }

    // -------------------------------------------------------------------------
    // Content negotiation
    // -------------------------------------------------------------------------

    public function wantsJson(): bool
    {
        $accept = (string)$this->header('accept', '');
        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }

    public function isJson(): bool
    {
        return str_contains((string)$this->header('content-type', ''), '/json');
    }

    public function contentType(): string
    {
        return (string)$this->header('content-type', '');
    }

    // -------------------------------------------------------------------------
    // Cookies
    // -------------------------------------------------------------------------

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    // -------------------------------------------------------------------------
    // Raw body
    // -------------------------------------------------------------------------

    public function getContent(): ?string
    {
        return $this->content;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function jsonBody(): array
    {
        if (!$this->isJson() || $this->content === null) return [];
        $decoded = json_decode($this->content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name           = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}
