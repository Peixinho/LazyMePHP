<?php

declare(strict_types=1);

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace API;
use Core\LazyMePHP;
use Core\Http\ApiExitException;

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
 * Mask API
 */
if(file_exists(__DIR__."/../../App/api/MaskAPI.php"))
	require_once __DIR__."/../../App/api/MaskAPI.php";
/* Work our custom masks if sent, avoiding showing unwanted fields even if requested */
#$mask = $GLOBALS['API_FIELDS_AVAILABLE'];
#$customMask = (json_decode(file_get_contents('php://input'), true)); 
#if (!empty($customMask)) {
#  foreach ($customMask as $key => $value) {
#    $diffmask = array_diff($customMask[$key], $mask[$key]);
#    $customMask[$key] = array_flip(array_diff_key(array_flip($customMask[$key]), array_flip($diffmask)));
#  }
#  $GLOBALS['API_FIELDS_AVAILABLE'] = $customMask;
#}


/*
 * Check of our request needs foreing data
 */
$GLOBALS['API_FOREIGN_DATA'] = (isset($_GET['foreignData']) && $_GET['foreignData'] == 'true') ? true : false;


/* Load all api routes */
foreach(glob(__DIR__."/../../App/api/" . "/*.php") as $r)
  require_once $r;


/*
 * Output result
 */
header('Content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT');

\Pecee\SimpleRouter\SimpleRouter::get('/api/not-found', function() {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not found', 'code' => 404]);
});
\Pecee\SimpleRouter\SimpleRouter::get('/api/forbidden', function() {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden', 'code' => 403]);
});

\Pecee\SimpleRouter\SimpleRouter::error(function(\Pecee\Http\Request $request, \Exception $exception) {
    $code = $exception->getCode();
    if ($code < 400 || $code > 599) {
        $code = 500;
    }
    http_response_code($code);
    header('Content-Type: application/json');
    $response = [
        'success' => false,
        'error' => $exception->getMessage(),
        'code' => $code,
    ];
    if (getenv('APP_ENV') !== 'production') {
        $response['debug'] = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
    echo json_encode($response);
    throw new ApiExitException('API exited after error response');
});

// CSRF protection for API endpoints
$method = $_SERVER['REQUEST_METHOD'] ?? null;
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!\Core\Security\CsrfProtection::verifyToken($csrfToken)) {
        http_response_code(419); // Authentication Timeout
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid CSRF token']);
        throw new ApiExitException('API exited after CSRF failure');
    }
}

\Pecee\SimpleRouter\SimpleRouter::start();

/*
 * Runs logging activity
 */
LazyMePHP::LOG_ACTIVITY();
LazyMePHP::DB_CONNECTION()->Close();

?>
