<?php

declare(strict_types=1);

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace API;

use Core\Auth\AuthEndpoint;
use Core\ErrorHandler;
use Core\Exceptions\ApiException;
use Core\LazyMePHP;
use Pecee\SimpleRouter\SimpleRouter;

// Shared with the web front controller (public/index.php): composer autoload, .env,
// error_reporting/timezone, LazyMePHP init, ErrorUtil error/exception handlers, debug
// toolbar, model-observer auto-discovery, fatal-error shutdown handler.
require_once __DIR__ . '/../../App/bootstrap.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$GLOBALS['API_FOREIGN_DATA'] = (isset($_GET['foreignData']) && $_GET['foreignData'] === 'true');

header('Content-Type: application/json; charset=utf-8');

if (class_exists(\Core\Helpers\PerformanceUtil::class)) {
    \Core\Helpers\PerformanceUtil::startTimer('api_request');
}

/*
 * JWT auth on the API front door (JSON only — no Blade layout):
 *   POST /api/auth/login
 *   POST /api/auth/refresh
 *   POST /api/auth/logout
 *   GET  /api/auth/me
 *   ...plus forgot/reset/verify when configured
 *
 * Web UI can keep using /auth/* via public/index.php + LazyMePHP::boot().
 */
if (!empty($_ENV['AUTH_TABLE'] ?? '')) {
    SimpleRouter::group(['prefix' => '/api'], static function (): void {
        AuthEndpoint::register();
    });
}

/* Custom API route files in App/Api/ */
foreach (glob(__DIR__ . '/../../App/Api/*.php') ?: [] as $r) {
    require_once $r;
}

SimpleRouter::get('/api/not-found', static function (): void {
    ErrorHandler::handleNotFoundError('API endpoint');
});

SimpleRouter::get('/api/forbidden', static function (): void {
    ErrorHandler::handleForbiddenError('Access to this API endpoint is forbidden');
});

SimpleRouter::get('/api', static function (): void {
    ErrorHandler::handleApiError(
        'API endpoint not specified',
        'ENDPOINT_NOT_FOUND',
        null
    );
});

SimpleRouter::error(static function (\Pecee\Http\Request $request, \Exception $exception): void {
    $requestUri = $request->getUrl()->getPath();
    $method     = $request->getMethod();
    $context    = "API Request: $method $requestUri";

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
    exit;
});

SimpleRouter::start();

if (class_exists(\Core\Helpers\PerformanceUtil::class)) {
    $metrics = \Core\Helpers\PerformanceUtil::endTimer('api_request');
    if ($metrics && ($metrics['duration_ms'] ?? 0) > 1000) {
        \Core\Helpers\PerformanceUtil::logSlowOperation('api_request', $metrics);
    }
}

\Core\Helpers\ActivityLogger::logActivity();
if (LazyMePHP::DB_CONNECTION()) {
    LazyMePHP::DB_CONNECTION()->Close();
}
