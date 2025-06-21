<?php

declare(strict_types=1);

use Pecee\SimpleRouter\SimpleRouter;
use Core\Security\CsrfProtection;

/*
 * CSRF Token API Endpoint
 */

SimpleRouter::get('/api/csrf-token', function() {
    try {
        $token = CsrfProtection::getToken();
        echo json_encode([
            'success' => true,
            'token' => $token,
            'expires_in' => 3600 // 1 hour
        ]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to generate CSRF token',
            'code' => 500
        ]);
    }
}); 