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
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

/*
 * Set Error Reporting
 */
if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

/*
 * Set Timezone
 */
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

/*
 * Initialize APP Configuration
 */
new Core\LazyMePHP();

/*
 * Set up unified error handling (register only once)
 */
set_error_handler(['\Core\Helpers\ErrorUtil', 'ErrorHandler']);
set_exception_handler(function(\Throwable $exception) {
    // Pass uncaught exceptions to ErrorUtil::ErrorHandler
    \Core\Helpers\ErrorUtil::ErrorHandler(E_ERROR, $exception->getMessage(), $exception->getFile(), $exception->getLine());
});

/*
 * Initialize Enhanced Debugging System
 */
if (Core\LazyMePHP::DEBUG_MODE()) {
    \Core\Debug\DebugHelper::initialize();
    
    // Register shutdown function to inject debug toolbar
    register_shutdown_function(function() {
        if (\Core\LazyMePHP::DEBUG_MODE()) {
            $debugToolbar = \Core\Debug\DebugToolbar::getInstance();
            $toolbarHtml = $debugToolbar->render();
            
            if (!empty($toolbarHtml)) {
                // Try to inject into HTML response
                $output = ob_get_contents();
                if ($output !== false) {
                    // Look for closing </body> tag
                    $pos = strripos($output, '</body>');
                    if ($pos !== false) {
                        $newOutput = substr($output, 0, $pos) . $toolbarHtml . substr($output, $pos);
                        ob_clean();
                        echo $newOutput;
                    } else {
                        // No </body> tag found, append at the end
                        echo $toolbarHtml;
                    }
                } else {
                    // No output buffer, just echo the toolbar
                    echo $toolbarHtml;
                }
            }
        }
    });
}

/*
 * Create Error Logging Table if it doesn't exist
 */
if (\Core\LazyMePHP::ACTIVITY_LOG()) {
    try {
        $db = \Core\LazyMePHP::DB_CONNECTION();
        if ($db) {
            // Check if __LOG_ERRORS table exists
            $tableExists = $db->Query("SHOW TABLES LIKE '__LOG_ERRORS'");
            if (!$tableExists || $tableExists->GetCount() === 0) {
                // Use LoggingTableSQL function for consistent table creation
                require_once __DIR__ . '/Tools/LoggingTableSQL';
                $dbType = \Core\LazyMePHP::DB_TYPE() ?? 'mysql';
                $createTableSQL = getLoggingTableSQL($dbType);
                
                // Execute the SQL statements
                $statements = explode(';', $createTableSQL);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        $db->Query($statement);
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Silently fail - error logging shouldn't break the app
        if (\Core\LazyMePHP::DEBUG_MODE()) {
            error_log("Failed to create error logging table: " . $e->getMessage());
        }
    }
}

/*
 * Add memory snapshot for bootstrap completion
 */
if (\Core\LazyMePHP::DEBUG_MODE()) {
    \Core\Debug\DebugHelper::addMemorySnapshot('Bootstrap Complete');
}

/*
 * Router
 */
require_once __DIR__."/Routes/Routes.php";

/*
 * Routing
 */
ob_start();

// Start performance monitoring for the entire request
if (class_exists('Core\Helpers\PerformanceUtil')) {
    \Core\Helpers\PerformanceUtil::startTimer('request_total');
}

try {
    Pecee\SimpleRouter\SimpleRouter::start();
    $content = ob_get_clean();
} catch (\Pecee\SimpleRouter\Exceptions\NotFoundHttpException $exception) {
    ob_end_clean();
    // Handle 404 errors
    \Core\ErrorHandler::handleWebNotFoundError();
    return;
} catch (\Core\Exceptions\ApiException $exception) {
    ob_end_clean();
    // Handle API exceptions with their specific HTTP status codes
    $httpCode = $exception->getCode();
    $message = $exception->getMessage();
    $errorCode = $exception->getErrorCode();
    $details = $exception->getDetails();
    
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        // API error - return JSON with correct status code
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'error_code' => $errorCode,
            'details' => $details
        ]);
    } else {
        // Web error - redirect to error page or show error
        if ($httpCode === 403) {
            // For CSRF errors, redirect back to the previous page with an error message
            $_SESSION['error'] = 'Security validation failed. Please try again.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        } else {
            \Core\ErrorHandler::handleWebServerError($exception);
        }
    }
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

// End performance monitoring and log if slow
if (class_exists('Core\Helpers\PerformanceUtil')) {
    $metrics = \Core\Helpers\PerformanceUtil::endTimer('request_total');
    if ($metrics && $metrics['duration_ms'] > 1000) {
        \Core\Helpers\PerformanceUtil::logSlowOperation(
            'request_total',
            $metrics
        );
    }
}

/*
 * Runs logging activity
 */
Core\LazyMePHP::LOG_ACTIVITY();

Core\LazyMePHP::DB_CONNECTION()->Close();

/*
 * Register fatal error shutdown handler (must be last to have priority)
 */
register_shutdown_function(['\Core\Helpers\ErrorUtil', 'FatalErrorShutdownHandler']);
