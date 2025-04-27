<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace LazyMePHP\Security;

class CsrfProtection
{
    const TOKEN_KEY = '_csrf_token';

    /**
     * Generate or retrieve a CSRF token
     *
     * @return string
     */
    public static function getToken()
    {
        // Start session if not already started (customize for LazyMePHP)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate new token if none exists or expired
        if (empty($_SESSION[self::TOKEN_KEY]) || self::isTokenExpired()) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
            $_SESSION['csrf_created_at'] = time();
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Verify a CSRF token
     *
     * @param string $token
     * @return bool
     */
    public static function verifyToken(string $token):bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $storedToken = $_SESSION[self::TOKEN_KEY] ?? null;

        if (!$storedToken || self::isTokenExpired()) {
            return false;
        }

        $isValid = hash_equals($storedToken, $token);

        // Regenerate token after successful verification to prevent reuse
        if ($isValid) {
            unset($_SESSION[self::TOKEN_KEY]);
            unset($_SESSION['csrf_created_at']);
            self::getToken(); // Generate new token
        }

        return $isValid;
    }

    /**
     * Check if token is expired (e.g., after 1 hour)
     *
     * @return bool
     */
    protected static function isTokenExpired():bool
    {
        $createdAt = $_SESSION['csrf_created_at'] ?? 0;
        $expiryTime = 3600; // 1 hour in seconds
        return (time() - $createdAt) > $expiryTime;
    }

    /**
     * Render CSRF token as hidden input
     *
     * @return string
     */
    public static function renderInput():string
    {
        return \htmlspecialchars(self::getToken());
    }
}
?>
