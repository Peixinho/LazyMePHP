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
 * Initialize Enhanced Debugging System
 */
if (Core\LazyMePHP::DEBUG_MODE()) {
    \Core\Debug\DebugHelper::initialize();
    
    // Set up enhanced error handling for debug mode
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false; // Let PHP handle it normally
        }
        
        $debugToolbar = \Core\Debug\DebugToolbar::getInstance();
        $debugToolbar->addError($errstr, $errfile, $errline);
        
        return false; // Let PHP continue with normal error handling
    });
    
    set_exception_handler(function(\Throwable $exception) {
        $debugToolbar = \Core\Debug\DebugToolbar::getInstance();
        $debugToolbar->addError(
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        // Log to error handler
        \Core\ErrorHandler::logError($exception, 'Uncaught Exception');
    });
    
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
 * Set up global error handling
 */
if (!\Core\LazyMePHP::DEBUG_MODE()) {
    // Production error handling
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Log to error handler without displaying
        $exception = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        \Core\ErrorHandler::logError($exception, 'Error');
        
        return true; // Don't execute PHP internal error handler
    });
    
    set_exception_handler(function(\Throwable $exception) {
        \Core\ErrorHandler::logError($exception, 'Uncaught Exception');
        
        // Show generic error page in production
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Internal Server Error</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error-container { max-width: 600px; margin: 0 auto; }
                .error-code { font-size: 72px; color: #e74c3c; margin: 0; }
                .error-message { font-size: 24px; color: #2c3e50; margin: 20px 0; }
                .error-description { color: #7f8c8d; line-height: 1.6; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1 class="error-code">500</h1>
                <h2 class="error-message">Internal Server Error</h2>
                <p class="error-description">
                    We apologize, but something went wrong on our end. 
                    Our team has been notified and is working to fix the issue.
                </p>
                <p class="error-description">
                    Please try again later or contact support if the problem persists.
                </p>
            </div>
        </body>
        </html>';
    });
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
