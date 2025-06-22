<?php

declare(strict_types=1);

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace API;

// Set CORS headers immediately
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configure session for cross-port sharing
ini_set('session.cookie_domain', 'localhost');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); // Allow HTTP for localhost
ini_set('session.cookie_httponly', '0'); // Allow JavaScript access if needed

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Core\LazyMePHP;
use Core\Http\ApiExitException;
use Core\ErrorHandler;
use Core\Exceptions\ApiException;

/*
 * Router
 */
require_once __DIR__."/../../vendor/autoload.php";

/*
 * Load Environment Variables
 */
if (file_exists(__DIR__.'/../../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
    $dotenv->load();
}

/*
 *
 * Initialize LazyMePHP
 *
 */
new LazyMePHP();

/*
 * Check of our request needs foreing data
 */
$GLOBALS['API_FOREIGN_DATA'] = (isset($_GET['foreignData']) && $_GET['foreignData'] == 'true') ? true : false;

/* Load all api routes */
foreach(glob(__DIR__."/../../App/Api/" . "/*.php") as $r)
  require_once $r;

/*
 * Output result
 */
header('Content-type: application/json');

// Start performance monitoring for API requests
if (class_exists('Core\Helpers\PerformanceUtil')) {
    \Core\Helpers\PerformanceUtil::startTimer('api_request');
}

\Pecee\SimpleRouter\SimpleRouter::get('/api/not-found', function() {
    ErrorHandler::handleNotFoundError('API endpoint');
});

\Pecee\SimpleRouter\SimpleRouter::get('/api/forbidden', function() {
    ErrorHandler::handleForbiddenError('Access to this API endpoint is forbidden');
});

\Pecee\SimpleRouter\SimpleRouter::get('/api', function() {
    ErrorHandler::handleApiError(
        'API endpoint not specified',
        'ENDPOINT_NOT_FOUND',
        null
    );
});

\Pecee\SimpleRouter\SimpleRouter::error(function(\Pecee\Http\Request $request, \Exception $exception) {
    // Log the error with request context
    $requestUri = $request->getUrl()->getPath();
    $method = $request->getMethod();
    $context = "API Request: $method $requestUri";
    
    if ($exception instanceof ApiException) {
        ErrorHandler::handleApiError(
            $exception->getMessage(),
            $exception->getErrorCode(),
            $exception->getDetails(),
            $exception
        );
    } else {
        ErrorHandler::logError($exception, $context);
        ErrorHandler::handleApiError(
            'Internal server error',
            'INTERNAL_ERROR',
            null,
            $exception
        );
    }
    exit; // Exit gracefully instead of throwing ApiExitException
});

\Pecee\SimpleRouter\SimpleRouter::start();

// End performance monitoring and log if slow
if (class_exists('Core\Helpers\PerformanceUtil')) {
    $metrics = \Core\Helpers\PerformanceUtil::endTimer('api_request');
    if ($metrics && $metrics['duration_ms'] > 1000) {
        \Core\Helpers\PerformanceUtil::logSlowOperation(
            'api_request',
            $metrics
        );
    }
}

/*
 * Runs logging activity
 */
LazyMePHP::LOG_ACTIVITY();
LazyMePHP::DB_CONNECTION()->Close();

// Load environment variables
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

?>
