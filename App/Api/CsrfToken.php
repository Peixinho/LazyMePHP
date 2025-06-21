<?php

declare(strict_types=1);

use Pecee\SimpleRouter\SimpleRouter;
use Core\Security\CsrfProtection;
use Core\Debug\DebugHelper;

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

/*
 * Debug Information API Endpoint
 */

SimpleRouter::get('/api/debug', function() {
    // Only allow in debug mode
    if (!\Core\LazyMePHP::DEBUG_MODE()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Debug endpoint is only available in debug mode',
            'code' => 403
        ]);
        return;
    }
    
    try {
        $debugInfo = DebugHelper::getDebugInfo();
        
        echo json_encode([
            'success' => true,
            'data' => $debugInfo,
            'generated_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to generate debug information',
            'code' => 500,
            'details' => $e->getMessage()
        ]);
    }
});

/*
 * Debug Report API Endpoint
 */

SimpleRouter::get('/api/debug/report', function() {
    // Only allow in debug mode
    if (!\Core\LazyMePHP::DEBUG_MODE()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Debug endpoint is only available in debug mode',
            'code' => 403
        ]);
        return;
    }
    
    try {
        $report = DebugHelper::generateDebugReport();
        
        header('Content-Type: text/plain');
        echo $report;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to generate debug report',
            'code' => 500,
            'details' => $e->getMessage()
        ]);
    }
});

/*
 * Debug Dump API Endpoint
 */

SimpleRouter::get('/api/debug/dump', function() {
    // Only allow in debug mode
    if (!\Core\LazyMePHP::DEBUG_MODE()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Debug endpoint is only available in debug mode',
            'code' => 403
        ]);
        return;
    }
    
    try {
        $filename = $_GET['filename'] ?? null;
        $filepath = DebugHelper::dumpDebugInfo($filename);
        
        echo json_encode([
            'success' => true,
            'filepath' => $filepath,
            'filename' => basename($filepath),
            'message' => 'Debug information dumped to file successfully'
        ]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to dump debug information',
            'code' => 500,
            'details' => $e->getMessage()
        ]);
    }
}); 