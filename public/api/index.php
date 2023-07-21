<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\API;

/*
 * Add LazyMePHP Configuration File
 */
require_once __DIR__."/../../src/Configurations/Configurations.php";
require_once __DIR__."/../../src/Configurations/Internal/InternalConfigurations.php";
require_once __DIR__."/../../src/Helpers/Helper.php";

use \LazyMePHP\Config\Internal\APP;

/*
 * Include Generated Class Files
 */
if(file_exists(__DIR__."/../../src/Classes/includes.php"))
    require_once __DIR__."/../../src/Classes/includes.php";

/*
 * Router
 */
require_once APP::ROOT_PATH()."/src/Ext/vendor/autoload.php";

if(file_exists(__DIR__."/../../src/api/MaskAPI.php"))
	require_once __DIR__."/../../src/api/MaskAPI.php";
if(file_exists(__DIR__."/../../src/api/RouteAPI.php"))
	require_once __DIR__."/../../src/api/RouteAPI.php";

/*
 * Output result
 */
header('Content-type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT');

\Pecee\SimpleRouter\SimpleRouter::start();

/*
 * Runs logging activity
 */
APP::LOG_ACTIVITY();

?>
