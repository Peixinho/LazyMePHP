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
            session_start();
        }
    }

    public function get(string $key, mixed $default = null) : mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value) : void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key) : bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(array|string $keys) : void
    {
        $keysArray = is_array($keys) ? $keys : [$keys];
        foreach ($keysArray as $key) {
            unset($_SESSION[$key]);
        }
    }
}
?>
