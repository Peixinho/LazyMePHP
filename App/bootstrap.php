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
require_once __DIR__.'/../vendor/autoload.php';

/*
 * Load Environment Variables
 */
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

// Continue with other require_once statements for the framework
// Ensure use LazyMePHP\Config\Internal\APP; is present if APP::LOG_ACTIVITY() etc. are called directly without full namespace
// However, new LazyMePHP\Config\Internal\APP() is explicit.

/*
 * Initialize APP Configuration
 */
new Core\LazyMePHP();

/*
 * Router
 */
require_once __DIR__."/Routes/Routes.php";

/*
 * Routing
 */
ob_start();
Pecee\SimpleRouter\SimpleRouter::start();
$content = ob_get_clean();


/*
 * Runs logging activity
 */
Core\LazyMePHP::LOG_ACTIVITY();
Core\LazyMePHP::DB_CONNECTION()->Close();
