<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace Core\Session;

class Session
{
    private static ?Session $instance = null;

    public static function getInstance() : Session
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
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

    // -------------------------------------------------------------------------
    // Static facade
    // -------------------------------------------------------------------------

    public static function __callStatic(string $method, array $args): mixed
    {
        return self::getInstance()->$method(...$args);
    }
}
?>
