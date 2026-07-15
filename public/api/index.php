<?php

declare(strict_types=1);

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace API;

// CORS — only allow explicitly configured origins; wildcard is not permitted.
$allowedOrigin = $_ENV['APP_CORS_ORIGIN'] ?? '';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowedOrigin !== '' && $requestOrigin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
}

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Start session if not already started (cookie flags are set in Core\Session\Session)
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
\Core\Helpers\ActivityLogger::logActivity();
LazyMePHP::DB_CONNECTION()->Close();
