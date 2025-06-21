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
if (!isset($_ENV['TESTING']) && file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

/*
 * Initialize APP Configuration
 */
new Core\LazyMePHP();

/*
 * Initialize Debug Mode (if enabled)
 */
if (Core\LazyMePHP::DEBUG_MODE()) {
    Core\Debug\DebugHelper::init();
}

/*
 * Router
 */
require_once __DIR__."/Routes/Routes.php";

/*
 * Routing
 */
ob_start();
try {
    Pecee\SimpleRouter\SimpleRouter::start();
    $content = ob_get_clean();
} catch (\Pecee\SimpleRouter\Exceptions\NotFoundHttpException $exception) {
    ob_end_clean();
    // Handle 404 errors
    \Core\ErrorHandler::handleWebNotFoundError();
    return;
} catch (\Throwable $exception) {
    ob_end_clean();
    // Handle other errors
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        // API error - return JSON
        \Core\ErrorHandler::handleApiError(
            'Internal server error',
            'INTERNAL_ERROR',
            null,
            $exception
        );
    } else {
        // Web error - show error page
        \Core\ErrorHandler::handleWebServerError($exception);
    }
    return;
}

/*
 * Runs logging activity
 */
Core\LazyMePHP::LOG_ACTIVITY();

Core\LazyMePHP::DB_CONNECTION()->Close();
