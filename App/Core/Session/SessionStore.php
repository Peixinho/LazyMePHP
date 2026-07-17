<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace Core\Session;

/**
 * The real session implementation (singleton wrapping $_SESSION).
 *
 * Use this directly when you need a typed instance (e.g. a typed property).
 * For ergonomic static calls (Session::get(...), Session::put(...), ...), use
 * the Session facade instead — see Session.php.
 */
class SessionStore
{
    private static ?SessionStore $instance = null;

    public static function getInstance() : SessionStore
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        // headers_sent() guard: a request with no real session (a GraphQL/API
        // call authenticated via Bearer token, not a cookie) can still end up
        // here — e.g. Core\Helpers\ActivityLogger's access-log resolver calls
        // Tools\Auth::id() -> Session::get() for every request, including
        // API-only ones. If the response has already been written (any output
        // large enough to exceed PHP's output buffer flushes early, headers
        // and all — GraphQL error responses with a full debug trace routinely
        // do), attempting session_set_cookie_params()/session_start() here
        // would emit "headers already sent" warnings that this app's own error
        // handler then escalates into a second, garbled error page appended to
        // the real response. There's nothing useful a session can do once
        // headers are already gone, so just skip it silently.
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $isProd = ($_ENV['APP_ENV'] ?? 'production') !== 'development';
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isProd,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Alias for put(). */
    public function set(string $key, mixed $value): void
    {
        $this->put($key, $value);
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /** Get a value and immediately remove it from the session. */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function forget(array|string $keys): void
    {
        foreach ((array)$keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /** Clear the entire session. */
    public function flush(): void
    {
        $_SESSION = [];
    }

    /** Return all session data. */
    public function all(): array
    {
        return $_SESSION ?? [];
    }

    // -------------------------------------------------------------------------
    // Flash messages
    // -------------------------------------------------------------------------

    /**
     * Store a value that will be available only for the next request.
     *
     *   Session::flash('success', 'User saved!');
     *   // In the next request:
     *   $msg = Session::getFlash('success');
     */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION['__flash'][$key] = $value;
    }

    /** Retrieve a flash value (available only once). */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['__flash'][$key] ?? $default;
        unset($_SESSION['__flash'][$key]);
        return $value;
    }

    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['__flash'][$key]);
    }

    /** Return all pending flash messages and clear them. */
    public function pullAllFlash(): array
    {
        $flash = $_SESSION['__flash'] ?? [];
        unset($_SESSION['__flash']);
        return $flash;
    }

    // -------------------------------------------------------------------------
    // Regeneration
    // -------------------------------------------------------------------------

    /** Regenerate session ID (call after login to prevent fixation attacks). */
    public function regenerate(bool $deleteOld = true): bool
    {
        return session_regenerate_id($deleteOld);
    }

    public function getId(): string
    {
        return session_id() ?: '';
    }
}
