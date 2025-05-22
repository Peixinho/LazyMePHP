<?php // Ensure this is at the very top if not already

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

/*
 * Load Composer Autoloader (for Dotenv and other dependencies)
 */
require_once __DIR__.'/Ext/vendor/autoload.php';

/*
 * Load Environment Variables
 */
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

/*
 * Add LazyMePHP Internal Configuration File
 */
require_once __DIR__."/Configurations/Internal/InternalConfigurations.php";

/*
 * Initialize APP Configuration
 */
new LazyMePHP\Config\Internal\APP();

// Continue with other require_once statements for the framework
// Ensure use LazyMePHP\Config\Internal\APP; is present if APP::LOG_ACTIVITY() etc. are called directly without full namespace
// However, new LazyMePHP\Config\Internal\APP() is explicit.
use LazyMePHP\Config\Internal\APP;

// APP URL
$urlFiles = filter_input(INPUT_SERVER, "SERVER_NAME");

/*
 * Helper Functions to Aliviate Stress :P
 * Contains global namespaced functions, so it needs to be required explicitly.
 * PSR-4 autoloader handles class-based loading.
 */
require_once __DIR__."/Helpers/Helper.php";

// The following class-based files are now handled by PSR-4 autoloading:
// require_once __DIR__."/Security/CSRFProtection.php"; // Autoloaded
// require_once __DIR__."/Security/JWT.php"; // Autoloaded
// require_once __DIR__."/Enum/Enum.php"; // Autoloaded
// require_once __DIR__."/Validations/Validations.php"; // Autoloaded
// require_once __DIR__."/Messages/Messages.php"; // Autoloaded
// require_once __DIR__."/Values/Values.php"; // Autoloaded

/*
 * Router
 */
// require_once __DIR__."/Ext/vendor/autoload.php"; // Already loaded at the top

/* Load external routes file */
require_once __DIR__."/Routes/Routes.php";

/*
 * Routing
 */
ob_start();
Pecee\SimpleRouter\SimpleRouter::start();
$content = ob_get_contents();
ob_clean();

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
APP::DB_CONNECTION()->Close();
?>
