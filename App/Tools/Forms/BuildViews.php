<?php

namespace Tools\Forms;

require_once __DIR__ . '/../Helper';

/**
 * Views are now handled by generic Blade templates in App/Views/_Crud/ — no code generation needed.
 *
 * CrudController::viewName() resolves to App/Views/{table}/{action}.blade.php when it
 * exists, otherwise falls back to App/Views/_Crud/{action}.blade.php which renders
 * dynamically from the table schema.
 *
 * To customise a table's views, create App/Views/{TableName}/index.blade.php and/or
 * App/Views/{TableName}/edit.blade.php. All schema variables ($schema, $record, $pk,
 * $table, $records, $length, $current, $limit) are passed automatically.
 */
class BuildViews {
    public function __construct($viewsPath, $db) {
        echo "\n\u{1F4A1} Views are now powered by generic Blade templates in App/Views/_Crud/ — no code generation needed.\n";
        echo "   Create App/Views/{$db->GetTableName()}/index.blade.php or edit.blade.php to override.\n\n";
        \Tools\Helper\read();
    }
}
