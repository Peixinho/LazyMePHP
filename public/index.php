<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

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

// 4. Execute the router and capture the output
try {
    ob_start();
    \Pecee\SimpleRouter\SimpleRouter::start();
    $pageContent = ob_get_clean();
} catch (\Throwable $e) {
    ob_end_clean(); // Discard buffer on error
    // Use the custom error page component
    echo \Core\Helpers\ErrorPage::generate([
        'error_id' => \Core\Helpers\ErrorUtil::generateErrorId(),
        'type' => $e->getCode(),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit;
}

// 5. Render the main layout, injecting the router's content
echo $blade->run("_Layouts.app", [
    'pageContent' => $pageContent ?? ''
]);

// 6. Perform Post-Request Tasks
// (These could also be in a register_shutdown_function if needed)
if (class_exists('Core\LazyMePHP')) {
    \Core\LazyMePHP::LOG_ACTIVITY();
    if (\Core\LazyMePHP::DB_CONNECTION()) {
        \Core\LazyMePHP::DB_CONNECTION()->Close();
    }
}
