<?php

namespace Tools\Forms;

require_once __DIR__ . '/../Helper';

/**
 * Controllers are now handled by Core\CrudController — no code generation needed.
 *
 * Routes auto-resolve to a custom subclass when one exists in Controllers\,
 * otherwise CrudController::forTable() handles the request generically.
 *
 * To customise a table's controller, create App/Controllers/TableName.php:
 *
 *   namespace Controllers;
 *   use Core\CrudController;
 *
 *   class TableName extends CrudController {
 *       protected static string $table = 'table_name';
 *
 *       protected function foreignKeys(): array {
 *           return ['role_id' => 'roles'];
 *       }
 *   }
 */
class BuildControllers {
    public function __construct($controllersPath, $classesPath, $db) {
        echo "\n💡 Controllers are now powered by Core\\CrudController — no code generation needed.\n";
        echo "   Routes use CrudController::forTable() and auto-discover Controllers\\{Table} subclasses.\n\n";
        \Tools\Helper\read();
    }
}
