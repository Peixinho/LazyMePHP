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
if (isset($_ENV['APP_DEBUG_MODE']) && $_ENV['APP_DEBUG_MODE'] === 'true') {
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
    
    // Register shutdown function to inject debug toolbar (HTML pages only)
    register_shutdown_function(function() {
        try {
            if (!\Core\LazyMePHP::DEBUG_MODE()) {
                return;
            }

            // Never pollute JSON / SSE / plain-text API responses
            foreach (headers_list() as $header) {
                if (stripos($header, 'Content-Type:') !== 0) {
                    continue;
                }
                $type = strtolower(trim(substr($header, strlen('Content-Type:'))));
                if (str_contains($type, 'application/json')
                    || str_contains($type, 'text/event-stream')
                    || str_contains($type, 'text/plain')
                ) {
                    return;
                }
            }

            $debugToolbar = \Core\Debug\DebugToolbar::getInstance();
            $toolbarHtml = $debugToolbar->render();

            if (empty($toolbarHtml)) {
                return;
            }

            // Prefer injecting before </body> when an output buffer still holds the page.
            // public/index.php does ob_get_clean() then echoes the layout with no buffer,
            // so fall back to appending (same as before) for normal HTML responses.
            $output = ob_get_contents();
            if ($output !== false && $output !== '') {
                $pos = strripos($output, '</body>');
                if ($pos !== false) {
                    $newOutput = substr($output, 0, $pos) . $toolbarHtml . substr($output, $pos);
                    ob_clean();
                    echo $newOutput;
                    return;
                }
            }

            echo $toolbarHtml;
        } catch (\Throwable $e) {
            // If debug toolbar fails, at least try to show a simple error indicator
            if (Core\LazyMePHP::DEBUG_MODE()) {
                echo "<!-- Debug toolbar failed to render: " . htmlspecialchars($e->getMessage()) . " -->";
            }
        }
    });
}

/*
 * Add memory snapshot for bootstrap completion
 */
if (\Core\LazyMePHP::DEBUG_MODE()) {
    \Core\Debug\DebugHelper::addMemorySnapshot('Bootstrap Complete');
}

/*
 * Auto-discover and register model observers from App/Observers/.
 *
 * Each observer class must declare `protected static string $table = 'table_name';`
 * so it can be matched to the correct model events.
 */
$_observerDir = __DIR__ . '/Observers';
if (is_dir($_observerDir)) {
    foreach (glob($_observerDir . '/*.php') ?: [] as $_observerFile) {
        require_once $_observerFile;
        $_observerClass = basename($_observerFile, '.php');
        if (class_exists($_observerClass)) {
            try {
                $_ref   = new ReflectionClass($_observerClass);
                $_table = $_ref->hasProperty('table')
                    ? $_ref->getStaticPropertyValue('table', null)
                    : null;
                if ($_table) {
                    \Core\Events\ModelEvents::registerObserver($_table, new $_observerClass());
                }
            } catch (\Throwable) {
                // Skip unloadable observers silently
            }
        }
    }
}
unset($_observerDir, $_observerFile, $_observerClass, $_ref, $_table);

/*
 * Register fatal error shutdown handler (must be last to have priority)
 */
register_shutdown_function(['\Core\Helpers\ErrorUtil', 'FatalErrorShutdownHandler']);
