<?php

declare(strict_types=1);

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace API;
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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT');

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

// CSRF protection for API endpoints
$method = $_SERVER['REQUEST_METHOD'] ?? null;
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Skip CSRF protection for certain endpoints
$skipCsrfEndpoints = [
    '/api/csrf-token',
];

$shouldSkipCsrf = false;
foreach ($skipCsrfEndpoints as $endpoint) {
    if (strpos($requestUri, $endpoint) !== false) {
        $shouldSkipCsrf = true;
        break;
    }
}

if (in_array($method, ['POST', 'PUT', 'DELETE']) && !$shouldSkipCsrf) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!\Core\Security\CsrfProtection::verifyToken($csrfToken)) {
        ErrorHandler::handleApiError(
            'Invalid CSRF token',
            'UNAUTHORIZED'
        );
        exit; // Exit gracefully instead of throwing ApiExitException
    }
}

\Pecee\SimpleRouter\SimpleRouter::start();

/*
 * Runs logging activity
 */
LazyMePHP::LOG_ACTIVITY();
LazyMePHP::DB_CONNECTION()->Close();

?>
