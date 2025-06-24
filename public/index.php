<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

// 0. Set up global error handling for fatal errors and other issues
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false; // Don't execute PHP internal error handler
    }
    
    if (class_exists('Core\ErrorHandler')) {
        $exception = new \ErrorException($message, 0, $severity, $file, $line);
        \Core\ErrorHandler::handleWebException($exception);
    } else {
        // Fallback error handling
        http_response_code(500);
        echo "Fatal Error: $message in $file on line $line";
    }
    exit;
});

// Handle fatal errors and other shutdown issues
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (class_exists('Core\ErrorHandler')) {
            $exception = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            \Core\ErrorHandler::handleWebException($exception);
        } else {
            // Fallback error handling
            http_response_code(500);
            echo "Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}";
        }
    }
});

// 1. Bootstrap the Application
require_once __DIR__ . "/../App/bootstrap.php";
require_once __DIR__ . '/../App/Core/BladeFactory.php';

// 2. Get the shared BladeOne instance
$blade = \Core\BladeFactory::getBlade();

// 3. Load Application Routes within a Base Path Group
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
\Pecee\SimpleRouter\SimpleRouter::group(['prefix' => $basePath], function () use ($blade) {
    // Load all route files. The $blade variable is available to them.
    foreach(glob(__DIR__."/../App/Routes/" . "/*.php") as $routeFile) {
        require_once $routeFile;
    }
});

// 4. Set up comprehensive error handling for all router exceptions
\Pecee\SimpleRouter\SimpleRouter::error(function(\Pecee\Http\Request $request, \Exception $exception) {
    if (class_exists('Core\ErrorHandler')) {
        // Use the comprehensive exception handler
        \Core\ErrorHandler::handleWebException($exception, $request->getUrl()->getPath());
    } else {
        // Fallback if ErrorHandler is not available
        http_response_code(500);
        echo "Internal Server Error";
    }
    exit;
});

// 5. Execute the router and capture the output
try {
    ob_start();
    \Pecee\SimpleRouter\SimpleRouter::start();
    $pageContent = ob_get_clean();
} catch (\Throwable $e) {
    ob_end_clean(); // Discard buffer on error

    if (class_exists('Core\ErrorHandler')) {
        // Use the comprehensive exception handler
        \Core\ErrorHandler::handleWebException($e, $_SERVER['REQUEST_URI'] ?? '');
    } else {
        // Fallback error handling if ErrorHandler is not available
        http_response_code(500);
        
        // Use the custom error page component
        echo \Core\Helpers\ErrorPage::generate([
            'error_id' => \Core\Helpers\ErrorUtil::generateErrorId(),
            'type' => $e->getCode(),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    exit;
}

// 6. Render the main layout, injecting the router's content
echo $blade->run("_Layouts.app", [
    'pageContent' => $pageContent ?? ''
]);

// 7. Perform Post-Request Tasks
// (These could also be in a register_shutdown_function if needed)
if (class_exists('Core\LazyMePHP')) {
    \Core\LazyMePHP::LOG_ACTIVITY();
    if (\Core\LazyMePHP::DB_CONNECTION()) {
        \Core\LazyMePHP::DB_CONNECTION()->Close();
    }
}
