<?php
/**
 * Centralized Authentication for Logging System
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standalone bootstrap for logging dashboard
require_once __DIR__ . '/../vendor/autoload.php';

// Load Environment Variables
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

// Initialize LazyMePHP without routing
new Core\LazyMePHP();

/**
 * Check if user is authenticated for logging access
 * @return bool
 */
function isLoggingAuthenticated(): bool {
    return isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
}

/**
 * Require authentication for logging access
 * @param bool $isApiEndpoint Whether this is an API endpoint (returns JSON) or webpage (redirects)
 * @return void
 */
function requireLoggingAuth(bool $isApiEndpoint = false): void {
    if (!isLoggingAuthenticated()) {
        if ($isApiEndpoint) {
            // API endpoint - return JSON error
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'code' => 'UNAUTHORIZED'
            ]);
        } else {
            // Webpage - redirect to login
            header('Location: login.php');
        }
        exit;
    }
}

/**
 * Get current user info for logging
 * @return array|null
 */
function getCurrentUser(): ?array {
    if (!isLoggingAuthenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Unknown',
        'email' => $_SESSION['user_email'] ?? null
    ];
}
?> 