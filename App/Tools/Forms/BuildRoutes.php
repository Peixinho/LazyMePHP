<?php

namespace Tools\Forms;

require_once __DIR__ . '/../Helper';

/**
 * Routes are now handled by Core\AutoRouter — no code generation needed.
 *
 * AutoRouter::registerAll($blade) registers CRUD routes for all tables at runtime.
 * AutoRouter::register($table, $blade) registers routes for a single table.
 *
 * Both are already wired in App/Routes/Routes.php. Nothing to generate.
 */
class BuildRoutes {
    public function __construct($routesPath, $db) {
        echo "\n\u{1F4A1} Routes are now powered by Core\\AutoRouter — no code generation needed.\n";
        echo "   AutoRouter::registerAll() in App/Routes/Routes.php handles all tables at runtime.\n\n";
        \Tools\Helper\read();
    }
}
