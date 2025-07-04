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
    
    // Convert PHP error to exception and use ErrorHandler
    $exception = new \ErrorException($message, 0, $severity, $file, $line);
    \Core\ErrorHandler::handleWebException($exception);
    exit;
});

// Handle fatal errors and other shutdown issues
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Convert fatal error to exception and use ErrorHandler
        $exception = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        \Core\ErrorHandler::handleWebException($exception);
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
    // Use the comprehensive ErrorHandler which logs to __LOG_ERRORS
    \Core\ErrorHandler::handleWebException($exception, $request->getUrl()->getPath());
    exit;
});

// 5. Execute the router and capture the output
try {
    ob_start();
    \Pecee\SimpleRouter\SimpleRouter::start();
    $pageContent = ob_get_clean();
} catch (\Throwable $e) {
    ob_end_clean(); // Discard buffer on error
    
    // Use the comprehensive ErrorHandler which logs to __LOG_ERRORS
    \Core\ErrorHandler::handleWebException($e, $_SERVER['REQUEST_URI'] ?? '');
    exit;
}

// 6. Render the main layout, injecting the router's content
echo $blade->run("_Layouts.app", [
    'pageContent' => $pageContent ?? ''
]);

// 7. Perform Post-Request Tasks
// (These could also be in a register_shutdown_function if needed)
if (class_exists('Core\LazyMePHP')) {
    \Core\Helpers\ActivityLogger::logActivity();
    if (\Core\LazyMePHP::DB_CONNECTION()) {
        \Core\LazyMePHP::DB_CONNECTION()->Close();
    }
}
