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
     * Generate a new CSRF token (only generates if none exists)
     *
     * @return string
     */
    public static function getToken(): string
    {
        $session = Session::getInstance();
        
        // Check if token already exists
        $existingToken = $session->get(self::TOKEN_KEY, null);
        if ($existingToken) {
            error_log("CSRF Token reused: " . substr($existingToken, 0, 8) . "...");
            return $existingToken;
        }
        
        // Generate new token only if none exists
        $newToken = bin2hex(random_bytes(32));
        $session->put(self::TOKEN_KEY, $newToken);
        $session->put('csrf_created_at', time());
        
        error_log("CSRF Token generated: " . substr($newToken, 0, 8) . "...");
        
        return $newToken;
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

        error_log("CSRF Verification - Stored token: " . ($storedToken ? substr($storedToken, 0, 8) . "..." : "NULL"));
        error_log("CSRF Verification - Received token: " . substr($token, 0, 8) . "...");

        if (!$storedToken) {
            error_log("CSRF Verification failed: No stored token");
            return false;
        }

        // Use hash_equals for timing attack protection
        $isValid = hash_equals($storedToken, $token);
        error_log("CSRF Verification result: " . ($isValid ? "VALID" : "INVALID"));
        
        if ($isValid) {
            // Generate a new token after successful validation to prevent replay attacks
            $newToken = bin2hex(random_bytes(32));
            $session->put(self::TOKEN_KEY, $newToken);
            $session->put('csrf_created_at', time());
            error_log("CSRF New token generated after validation: " . substr($newToken, 0, 8) . "...");
        }
        
        return $isValid;
    }

    /**
     * Check if token exists (for display purposes)
     *
     * @return bool
     */
    public static function hasToken(): bool
    {
        $session = Session::getInstance();
        return $session->has(self::TOKEN_KEY);
    }

    /**
     * Clear CSRF token
     */
    public static function clearToken(): void
    {
        $session = Session::getInstance();
        $session->forget([self::TOKEN_KEY, 'csrf_created_at']);
    }

    /**
     * Get current token (without generating new one)
     *
     * @return string
     */
    public static function getCurrentToken(): string
    {
        $session = Session::getInstance();
        $token = $session->get(self::TOKEN_KEY, null);
        
        if (!$token) {
            // Generate token if none exists
            return self::getToken();
        }
        
        return $token;
    }

    /**
     * Render CSRF token as hidden input
     *
     * @return string
     */
    public static function renderInput(): string
    {
        return htmlspecialchars(self::getCurrentToken());
    }
}
