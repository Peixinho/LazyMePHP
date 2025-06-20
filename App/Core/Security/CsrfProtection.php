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

    /**
     * Generate or retrieve a CSRF token
     *
     * @return string
     */
    public static function getToken()
    {
        $session = Session::getInstance();

        // Generate new token if none exists or expired
        if (!$session->has(self::TOKEN_KEY) || self::isTokenExpired()) {
            $session->put(self::TOKEN_KEY, bin2hex(random_bytes(32)));
            $session->put('csrf_created_at', time());
        }

        return $session->get(self::TOKEN_KEY);
    }

    /**
     * Verify a CSRF token
     *
     * @param string $token
     * @return bool
     */
    public static function verifyToken(string $token): bool
    {
        $session = Session::getInstance();

        $storedToken = $session->get(self::TOKEN_KEY, null);

        if (!$storedToken || self::isTokenExpired()) {
            return false;
        }

        $isValid = hash_equals($storedToken, $token);

        if ($isValid) {
            // Track token usage
            $usageCount = $session->get('csrf_usage_count', 0);
            $usageCount++;
            $session->put('csrf_usage_count', $usageCount);
            
            // Regenerate token after 10 uses or if it's been used for more than 30 minutes
            $lastUsed = $session->get('csrf_last_used', time());
            $timeSinceLastUsed = time() - $lastUsed;
            
            if ($usageCount >= 10 || $timeSinceLastUsed > 1800) { // 10 uses or 30 minutes
                $session->forget([self::TOKEN_KEY, 'csrf_created_at', 'csrf_usage_count', 'csrf_last_used']);
                self::getToken(); // Generate new token
            } else {
                $session->put('csrf_last_used', time());
            }
        }

        return $isValid;
    }

    /**
     * Check if token is expired (e.g., after 1 hour)
     *
     * @return bool
     */
    protected static function isTokenExpired(): bool
    {
        $session = Session::getInstance();
        $createdAt = $session->get('csrf_created_at', 0);
        $expiryTime = 3600; // 1 hour in seconds
        return (time() - $createdAt) > $expiryTime;
    }

    /**
     * Render CSRF token as hidden input
     *
     * @return string
     */
    public static function renderInput(): string
    {
        return htmlspecialchars(self::getToken());
    }
}
