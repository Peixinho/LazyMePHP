<?php

declare(strict_types=1);

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace API;
use Core\LazyMePHP;

use function LazyMePHP\Helper\response;

/*
 * Router
 */
require_once __DIR__."/../../App/Ext/vendor/autoload.php";

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

\Pecee\SimpleRouter\SimpleRouter::get('/api/not-found', function() {return "{\"status\": 0}";});
\Pecee\SimpleRouter\SimpleRouter::get('/api/forbidden', function() {return "{\"status\": 0}";});

\Pecee\SimpleRouter\SimpleRouter::error(function(\Pecee\Http\Request $request, \Exception $exception) {
    switch($exception->getCode()) {
        // Page not found
        case 404:
            response()->redirect('/api/not-found');
        // Forbidden
        case 403:
            response()->redirect('/api/forbidden');
    }
    
});
\Pecee\SimpleRouter\SimpleRouter::start();

/*
 * Runs logging activity
 */
LazyMePHP::LOG_ACTIVITY();
LazyMePHP::DB_CONNECTION()->Close();

?>
