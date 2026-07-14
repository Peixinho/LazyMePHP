<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\Security;

use Core\Session\Session;

class CsrfProtection
{
    const TOKEN_KEY = '_csrf_token';

    public static function getToken(): string
    {
        $session = Session::getInstance();

        $existing = $session->get(self::TOKEN_KEY, null);
        if ($existing) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $session->put(self::TOKEN_KEY, $token);
        $session->put('csrf_created_at', time());

        return $token;
    }

    public static function verifyToken(string $token): bool
    {
        $session = Session::getInstance();
        $stored  = $session->get(self::TOKEN_KEY, null);

        if (!$stored) {
            return false;
        }

        $isValid = hash_equals($stored, $token);

        if ($isValid) {
            // Rotate after successful use to prevent replay attacks
            $session->put(self::TOKEN_KEY, bin2hex(random_bytes(32)));
            $session->put('csrf_created_at', time());
        }

        return $isValid;
    }

    public static function hasToken(): bool
    {
        return Session::getInstance()->has(self::TOKEN_KEY);
    }

    public static function clearToken(): void
    {
        Session::getInstance()->forget([self::TOKEN_KEY, 'csrf_created_at']);
    }

    public static function getCurrentToken(): string
    {
        $token = Session::getInstance()->get(self::TOKEN_KEY, null);
        return $token ?? self::getToken();
    }

    /** Returns the current token escaped for use in an HTML hidden input value. */
    public static function renderInput(): string
    {
        return htmlspecialchars(self::getCurrentToken(), ENT_QUOTES, 'UTF-8');
    }
}
