<?php

declare(strict_types=1);

/**
 * LazyMePHP CrudController
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core;

use Core\Http\Request;
use Core\Model;
use Core\Validations\Validations;
use Core\Validations\ValidationsMethod;

/**
 * Abstract base controller — subclass to add custom behaviour.
 *
 * The framework supplies GenericCrudController as the default concrete
 * implementation (used when no Controllers\TableName subclass exists).
 *
 * Override any of the lifecycle hooks to inject logic without replacing
 * the full action:
 *
 *   namespace Controllers;
 *   use Core\CrudController;
 *   use Core\Model;
 *
 *   class Users extends CrudController {
 *       protected static string $table = 'users';
 *
 *       protected function foreignKeys(): array {
 *           return ['role_id' => 'roles'];
 *       }
 *
 *       protected function beforeSave(Model $obj, array &$data, bool $isUpdate): void {
 *           $data['updated_at'] = date('Y-m-d H:i:s');
 *       }
 *
 *       protected function afterDelete(mixed $id): void {
 *           // e.g. clean up related files
 *       }
 *   }
 */
abstract class CrudController
{
    protected static string $table = '';

    protected Request $request;
    protected string $tableName;

    public function __construct(string|Request $tableOrRequest, ?Request $request = null)
    {
        if ($tableOrRequest instanceof Request) {
            $this->request   = $tableOrRequest;
            $this->tableName = static::$table;
        } else {
            $this->tableName = $tableOrRequest;
            $this->request   = $request ?? new Request();
        }

        if ($this->tableName === '') {
            throw new \InvalidArgumentException(
                'Table name required: pass it as constructor argument or set $table in a subclass.'
            );
        }

        if ($this->request->post('filter')) {
            $params = [];
            foreach ($this->request->post() as $k => $g) {
                if ($g) $params[rawurlencode((string)$k)] = rawurlencode((string)$g);
            }
            $qs = implode('&', array_map(fn($k, $v) => "$k=$v", array_keys($params), $params));
            header('Location: /' . rawurlencode($this->tableName) . ($qs ? '?' . $qs : ''));
        }
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Return a controller for $table, using a custom subclass from the
     * Controllers\ namespace if one exists, otherwise GenericCrudController.
     */
    public static function forTable(string $table, Request $request): self
    {
        $customClass = "Controllers\\" . self::controllerClassName($table);
        if (class_exists($customClass)) {
            return new $customClass($request);
        }
        return new GenericCrudController($table, $request);
    }

    /**
     * snake_case table name -> StudlyCase class name, matching what
     * `php LazyMePHP make:controller <table>` scaffolds (e.g. checklist_tasks -> ChecklistTasks).
     * A literal "Controllers\\$table" lookup only ever matches single-word table
     * names, and only by accident on case-insensitive filesystems — this is the
     * one place both forTable() and isHidden() must resolve a custom class name.
     */
    private static function controllerClassName(string $table): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $table)));
    }

    // -------------------------------------------------------------------------
    // Hooks — override in subclasses
    // -------------------------------------------------------------------------

    /**
     * Set to true in a subclass to exclude this table from auto-wiring.
     * Hidden tables get no CRUD routes and are absent from the GraphQL schema.
     *
     *   class AuditLog extends CrudController {
     *       protected static string $table = 'audit_log';
     *       public static bool $hidden = true;
     *   }
     */
    public static bool $hidden = false;

    /** Returns true if the given table's controller has opted out of auto-wiring. */
    public static function isHidden(string $table): bool
    {
        $class = "Controllers\\" . self::controllerClassName($table);
        return class_exists($class) && $class::$hidden;
    }

    /**
     * Foreign-key relationships for dropdown loading: ['fk_column' => 'related_table'].
     * Columns with a real FK constraint in the schema are auto-detected already —
     * only declare this for relationships without a DB-level constraint, or to
     * override the auto-detected target table.
     */
    protected function foreignKeys(): array { return []; }

    /** Override or extend the schema-derived validation rules. */
    protected function extraValidationRules(): array { return []; }

    /**
     * Fields to expose via the GraphQL API. Empty array = all fields exposed.
     * Override to hide sensitive columns (passwords, tokens, etc.):
     *
     *   public function exposedFields(): array {
     *       return ['id', 'name', 'email'];
     *   }
     */
    public function exposedFields(): array { return []; }

    /**
     * Called before the record is saved.
     * Modify $data to change what gets written; set properties on $obj directly
     * for anything that bypasses validation.
     */
    protected function beforeSave(Model $obj, array &$data, bool $isUpdate): void {}

    /** Called after the record has been saved successfully. */
    protected function afterSave(Model $obj, bool $isUpdate): void {}

    /** Called before the record is deleted. Throw to abort. */
    protected function beforeDelete(Model $obj): void {}

    /** Called after the record has been deleted. */
    protected function afterDelete(mixed $id): void {}

    // -------------------------------------------------------------------------
    // View resolution
    // -------------------------------------------------------------------------

    /**
     * Return the Blade view name for the given action.
     * Prefers App/Views/{table}/{action}.blade.php when it exists,
     * otherwise falls back to the generic App/Views/_Crud/{action}.blade.php.
     */
    public function viewName(string $action): string
    {
        $viewsDir = __DIR__ . '/../Views';
        if (is_file("$viewsDir/{$this->tableName}/$action.blade.php")) {
            return "{$this->tableName}.$action";
        }
        return "_Crud.$action";
    }

    // -------------------------------------------------------------------------
    // Schema helpers
    // -------------------------------------------------------------------------

    private function tableSchema(): array
    {
        return Model::schemaFor($this->tableName);
    }

    private function pkColumn(): ?string
    {
        foreach ($this->tableSchema() as $col => $meta) {
            if ($meta['pk']) return $col;
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    protected function validationRules(): array
    {
        $schema = $this->tableSchema();
        $rules  = [];

        foreach ($schema as $column => $meta) {
            if ($meta['pk']) continue;

            $dbType   = strtolower($meta['type']);
            $nullable = $meta['nullable'];
            $col      = strtolower($column);

            [$validations, $type] = $this->inferValidation($dbType, $col);

            $rules[$column] = [
                'validations' => $validations,
                'required'    => !$nullable,
                'type'        => $type,
            ];
        }

        return array_merge($rules, $this->extraValidationRules());
    }

    private function inferValidation(string $dbType, string $colName): array
    {
        if (str_contains($dbType, 'bool') || str_contains($dbType, 'bit')) {
            return [[ValidationsMethod::BOOLEAN], 'bool'];
        }
        if (str_contains($dbType, 'int')) {
            return [[ValidationsMethod::INT], 'int'];
        }
        if (str_contains($dbType, 'real')   || str_contains($dbType, 'float') ||
            str_contains($dbType, 'double') || str_contains($dbType, 'decimal') ||
            str_contains($dbType, 'numeric')) {
            return [[ValidationsMethod::FLOAT], 'float'];
        }
        if (str_contains($dbType, 'datetime') || str_contains($dbType, 'timestamp')) {
            return [[ValidationsMethod::DATETIME], 'iso_date'];
        }
        if (str_contains($dbType, 'date')) {
            return [[ValidationsMethod::DATE], 'iso_date'];
        }

        if (str_contains($colName, 'email')) {
            return [[ValidationsMethod::EMAIL], 'string'];
        }
        if (str_contains($colName, 'phone') || str_contains($colName, 'tel')) {
            return [[ValidationsMethod::STRING, ValidationsMethod::REGEXP, '/^[+]?[0-9\s\-\(\)]+$/'], 'string'];
        }
        if (str_contains($colName, 'url') || str_contains($colName, 'website') || str_contains($colName, 'link')) {
            return [[ValidationsMethod::STRING, ValidationsMethod::REGEXP, '/^https?:\/\/.+/'], 'string'];
        }

        return [[ValidationsMethod::STRING], 'string'];
    }

    // -------------------------------------------------------------------------
    // CRUD actions
    // -------------------------------------------------------------------------

    /**
     * List records with optional filtering and pagination.
     *
     * Returns both a generic 'records' key and a tableName key for
     * backward compatibility with hand-written table-specific views.
     */
    public function index(?int $page = null, ?int $limit = null): array
    {
        $query      = Model::query($this->tableName);
        $filters    = [];
        $knownCols  = array_keys($this->tableSchema());

        foreach ($this->request->get() as $method => $val) {
            if (!$method || !$val) continue;
            if (str_starts_with($method, 'FindBy')) {
                $col = lcfirst(substr($method, 6));
                if (!in_array($col, $knownCols, true)) continue;
                $query->where($col, $val);
                $filters[$method] = $val;
            } elseif (str_starts_with($method, 'OrderBy')) {
                $col = lcfirst(substr($method, 7));
                if (!in_array($col, $knownCols, true)) continue;
                $query->orderBy($col, strtoupper((string)$val) === 'DESC' ? 'DESC' : 'ASC');
                $filters[$method] = $val;
            }
        }

        $length  = $query->count();
        if ($page && $limit > 0) {
            $query->limit($limit, ($page - 1) * $limit);
        }
        $records = $query->get();
        $schema  = $this->tableSchema();
        $fkData  = $this->loadForeignKeys();

        return array_merge([
            $this->tableName => $records,
            'records'        => $records,
            'schema'         => $schema,
            'table'          => $this->tableName,
            'pk'             => $this->pkColumn(),
            'length'         => $length,
            'filters'        => $filters,
        ], $fkData);
    }

    /**
     * Load a single record for editing (or a blank model for new records).
     */
    public function edit(mixed $id = null, mixed $result = null): array
    {
        $record = new Model($this->tableName, $id);
        $schema = $this->tableSchema();
        $fkData = $this->loadForeignKeys();

        return array_merge([
            $this->tableName => $record,
            'record'         => $record,
            'schema'         => $schema,
            'table'          => $this->tableName,
            'pk'             => $this->pkColumn(),
            'result'         => $result,
        ], $fkData);
    }

    /**
     * Validate $inputData then INSERT or UPDATE. Used by both save() (web forms)
     * and GraphQL mutations so validation rules and lifecycle hooks always run.
     *
     * Returns the saved Model on success, or false on validation failure.
     * On failure the validation errors are stored in Messages so web forms can
     * display them; GraphQL callers should check the return value instead.
     */
    public function saveData(array $inputData, mixed $id = null): Model|false
    {
        $obj      = new Model($this->tableName, $id);
        $isUpdate = $id !== null;

        $rules = $this->validationRules();
        if ($isUpdate) {
            $rules = array_filter($rules, fn($k) => array_key_exists($k, $inputData), ARRAY_FILTER_USE_KEY);
        }

        $validated = Validations::ValidateJsonData($inputData, $rules);

        if ($validated['success']) {
            $data = $validated['validated_data'];
            $this->beforeSave($obj, $data, $isUpdate);

            foreach ($data as $field => $value) {
                $obj->$field = $value;
            }
            $obj->Save();
            $this->afterSave($obj, $isUpdate);

            if ($isUpdate) {
                \Messages\Messages::RecordUpdated($this->tableName);
            } else {
                \Messages\Messages::RecordCreated($this->tableName);
            }

            return $obj;
        }

        \Messages\Messages::ValidationErrors($validated['errors'], ['type' => $this->tableName]);
        return false;
    }

    /**
     * Validate input from the current HTTP request then INSERT or UPDATE.
     * Reads JSON body first, falls back to form POST data.
     */
    public function save(mixed $id = null): Model|false
    {
        $jsonBody  = $this->request->json();
        $inputData = !empty($jsonBody) ? $jsonBody : $this->request->post();
        return $this->saveData($inputData, $id);
    }

    /** Delete a record by primary key. */
    public function delete(mixed $id = null): void
    {
        if ($id === null) return;

        $obj = new Model($this->tableName, $id);
        $this->beforeDelete($obj);
        $obj->Delete();
        $this->afterDelete($id);
        \Messages\Messages::RecordDeleted($this->tableName);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Column => related table, combining schema-detected FK constraints with
     * any explicit foreignKeys() declarations (explicit wins on conflicts).
     */
    private function resolvedForeignKeys(): array
    {
        $auto = [];
        foreach ($this->tableSchema() as $col => $meta) {
            if (!empty($meta['references']['table'])) {
                $auto[$col] = $meta['references']['table'];
            }
        }
        return array_merge($auto, $this->foreignKeys());
    }

    /** Best-effort human-readable label for a foreign-key option row. */
    private function fkOptionLabel(Model $row): string
    {
        foreach (['name', 'title', 'label', 'username', 'email'] as $candidate) {
            if (isset($row->$candidate) && $row->$candidate !== '') {
                return (string) $row->$candidate;
            }
        }
        return (string) $row->getPrimaryKey();
    }

    /**
     * Loads dropdown data for foreign-key columns.
     * Returns:
     *   - table-keyed raw Model[] lists (for hand-written views), plus
     *   - 'foreignKeys' => ['col' => ['table' => t, 'options' => [['value'=>,'label'=>], ...]]]
     *     consumed automatically by the generic _Crud edit view.
     */
    private function loadForeignKeys(): array
    {
        $data       = [];
        $foreignKeys = [];

        foreach ($this->resolvedForeignKeys() as $col => $fkTable) {
            $rows = Model::query($fkTable)->get();
            $data[$fkTable] = $rows;
            $foreignKeys[$col] = [
                'table'   => $fkTable,
                'options' => array_map(fn(Model $row) => [
                    'value' => $row->getPrimaryKey(),
                    'label' => $this->fkOptionLabel($row),
                ], $rows),
            ];
        }

        $data['foreignKeys'] = $foreignKeys;
        return $data;
    }
}

/**
 * Concrete no-op controller used as the generic fallback by CrudController::forTable().
 * No logic — all behaviour comes from CrudController.
 */
final class GenericCrudController extends CrudController {}
