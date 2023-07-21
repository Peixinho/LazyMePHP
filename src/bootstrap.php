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
require_once __DIR__."/Configurations/Configurations.php";

/*
 * Enum
 */
require_once __DIR__."/Enum/Enum.class.php";

/*
 * Helper Functions to Aliviate Stress :P
 */
require_once __DIR__."/Helpers/Helper.php";

/*
 * Validations
 */
require_once __DIR__."/Validations/Validations.php";

/*
 * Error and Success Messages
 */
require_once __DIR__."/Messages/Messages.php";

/*
 * Values
 */
require_once __DIR__."/Values/Values.php";

/*
 * Router
 */
require_once __DIR__."/Ext/vendor/autoload.php";

/* Load external routes file */
require_once __DIR__."/Routes/Routes.php";
require_once __DIR__."/Controllers/RouteForms.php";

// Start the routing

/*
 * Include Generated Class Files
 */
if(file_exists(__DIR__."/Classes/includes.php"))
    require_once __DIR__."/Classes/includes.php";

/*
 * App Files if Needed
 */




/*
 * Commands to Execute if Needed
 */
// execute_this(...);






/*
 * Runs logging activity
 */
APP::LOG_ACTIVITY();

?>
