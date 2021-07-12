<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

use LazyMePHP\Config\Internal\APP;

/*
 * Session
 */
session_start();

// APP URL
$urlFiles = filter_input(INPUT_SERVER, "SERVER_NAME");

/*
 * Add LazyMePHP Configuration File
 */
require_once "Configurations/Configurations.php";

/*
 * Enum
 */
require_once "Enum/Enum.class.php";

/*
 * Helper Functions to Aliviate Stress :P
 */
require_once "Helpers/Helper.php";

/*
 * Validations
 */
require_once "Validations/Validations.php";

/*
 * Error and Success Messages
 */
require_once "Messages/Messages.php";

/*
 * Values
 */
require_once APP::ROOT_PATH()."/src/php/Values/Values.php";

/*
 * Router
 */
require_once APP::ROOT_PATH()."/src/php/Router/Router.php";

/*
 * Include Generated Class Files
 */
if(file_exists(APP::ROOT_PATH()."/src/php/Classes/includes.php"))
    require_once APP::ROOT_PATH()."/src/php/Classes/includes.php";

/*
 * Default Controller Routes
 * when routing is not found
 */
use LazyMePHP\Router\Router;
Router::SetDefaultRouting("Default", APP::ROOT_PATH()."/src/php/Controllers/Default_controller.php");

/*
 * App Files if Needed
 */
// require_once ...
require_once APP::ROOT_PATH()."/src/php/Controllers/RouteForms.php";





/*
 * Commands to Execute if Needed
 */
// execute_this(...);







/*
 * END Commands to Execute if needed
 */

/*
 * !LAST COMAND TO BE EXECUTED!
 * Dispatch based on URL
 */
if (APP::APP_URL_ENCRYPTION() && key_exists('query', parse_url($_SERVER['REQUEST_URI'])))
	$url = parse_url($_SERVER['REQUEST_URI'])['path'].APP::URLDECODE($_SERVER['REQUEST_URI']);
else
	$url = $_SERVER['REQUEST_URI'];

Router::Dispatch($url);

?>
