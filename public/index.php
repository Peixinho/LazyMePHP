<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

// The bootstrap file handles all the core initialization, routing, and error handling.
// It also captures the output of the routed controller into a buffer.
require_once __DIR__ . "/../App/bootstrap.php";

// The $content variable is made available from the executed route within bootstrap.php
if (isset($content)) {
    // Set up Blade for rendering the final layout
    $views = __DIR__ . '/../App/Views/';
    $cache = __DIR__ . '/../App/Views/_compiled';
    $blade = new \eftec\bladeone\BladeOne($views, $cache, \eftec\bladeone\BladeOne::MODE_AUTO);

    // Share settings with all views
    $blade->share('settings', [
        'appName' => $_ENV['APP_NAME'] ?? 'LazyMePHP',
        'appLogo' => '/img/logo.png',
    ]);
    
    // Render the main layout, injecting the page-specific content
    echo $blade->run("_Layouts.app", [
        'pageContent' => $content
    ]);
}
