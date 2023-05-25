<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\API;

function utf8ize( $mixed ) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
    } elseif (is_string($mixed)) {
        return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
    }
    return $mixed;
}

/*
 * Add LazyMePHP Configuration File
 */
require_once "../../src/Configurations/Configurations.php";

use \LazyMePHP\Config\Internal\APP;

/*
 * Router
 */
require_once APP::ROOT_PATH()."/src/Router/Router.php";

/*
 * Include Generated Class Files
 */
if(file_exists(APP::ROOT_PATH()."/src/Classes/includes.php"))
    require_once APP::ROOT_PATH()."/src/Classes/includes.php";

/*
 * Routes
 */
use \LazyMePHP\Router\Router;
Router::SetDefaultRouting("APIRequest", "./APIRequest.php");

if(file_exists("src/RouteAPI.php"))
	require_once "src/RouteAPI.php";

/*
 * Request Method
 */
$method =$_SERVER['REQUEST_METHOD'];

/*
 * Dispatch based on URL
 * basically it requires the API file
 */
$param = "\LazyMePHP\API\\".Router::Dispatch($_GET, $method);

/*
 * Parse the query (if used)
 */
$query = $_GET;

/*
 * Remove controller from query
 */
unset($query['controller']);

/*
 * Instantiate Table's API
 */
$obj = new $param();

/*
 * Parse input
 */
$data = file_get_contents('php://input');

/*
 * Output result
 */
header('Content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT');
echo json_encode($obj->$method($query, $data));

/*
 * Runs logging activity
 */
APP::LOG_ACTIVITY();

?>
