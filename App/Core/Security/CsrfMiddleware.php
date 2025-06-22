<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\Security;

use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;
use Core\Helpers\NotificationHelper;

class CsrfMiddleware implements IMiddleware
{
    public function __construct()
    {
        error_log("CSRF Middleware class instantiated");
    }

    /**
     * Handle CSRF protection
     *
     * @param Request $request
     * @return void
     */
    public function handle(Request $request): void
    {
        // Debug: Always log that middleware is being called
        error_log("=== CSRF MIDDLEWARE CALLED ===");
        error_log("Method: " . $request->getMethod());
        error_log("Path: " . $request->getUrl()->getPath());
        
        // Skip CSRF for GET requests
        if ($request->getMethod() === 'GET') {
            error_log("Skipping CSRF for GET request");
            return;
        }

        // Skip CSRF for API endpoints (they use different auth)
        $path = $request->getUrl()->getPath();
        if (strpos($path, '/api/') === 0) {
            error_log("Skipping CSRF for API endpoint");
            return;
        }

        // Debug: Log that middleware is being executed
        error_log("CSRF Middleware executing for: " . $request->getMethod() . " " . $path);

        // Get token from request
        $token = $this->getTokenFromRequest($request);
        
        // Debug: Log token status
        error_log("CSRF Token from request: " . ($token ? "Present" : "Missing"));
        
        if (!$token) {
            error_log("CSRF Error: Token missing");
            // Set CSRF error notification in session
            NotificationHelper::error('Security token is missing. Please refresh the page and try again.');
            
            // Redirect back to referer or home
            $referer = $request->getHeader('Referer') ?: '/';
            header("Location: {$referer}");
            exit;
        }

        // Verify token
        if (!CsrfProtection::verifyToken($token)) {
            error_log("CSRF Error: Token invalid");
            // Set CSRF error notification in session
            NotificationHelper::error('Security token is invalid. Please refresh the page and try again.');
            
            // Redirect back to referer or home
            $referer = $request->getHeader('Referer') ?: '/';
            header("Location: {$referer}");
            exit;
        }

        error_log("CSRF Token validation successful");
    }

    /**
     * Get CSRF token from request
     *
     * @param Request $request
     * @return string|null
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // Check POST data first
        $token = $request->getInputHandler()->value('csrf_token');
        if ($token) {
            return $token;
        }

        // Check headers
        $token = $request->getHeader('X-CSRF-TOKEN');
        if ($token) {
            return $token;
        }

        // Check Authorization header with CSRF prefix
        $authHeader = $request->getHeader('Authorization');
        if ($authHeader && strpos($authHeader, 'CSRF ') === 0) {
            return substr($authHeader, 5);
        }

        return null;
    }
} 