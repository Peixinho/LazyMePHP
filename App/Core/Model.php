<?php

declare(strict_types=1);

/**
 * LazyMePHP Model
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core;

use Core\DB\IDB;
use Core\DB\DatabaseException;
use Core\LazyMePHP;
use Core\Events\ModelEvents;
use Core\Relationships\Relationship;
use Core\Relationships\HasOne;
use Core\Relationships\HasMany;
use Core\Relationships\BelongsTo;
use Core\Relationships\BelongsToMany;

/**
 * Dynamic ORM model — full CRUD against any table, no code generation needed.
 *
 * Use directly by passing the table name, or subclass for typed access:
 *
 *   // Direct usage
 *   $user = new Model('users');
 *   $user->name = 'Alice';
 *   $user->save();
 *
 *   $user = new Model('users', 1);    // load by primary key
 *   $user->name = 'Updated';
 *   $user->save();
 *
 *   $user->delete();
 *
 *   // Fluent query builder
 *   $users = Model::query('users')
 *       ->where('active', 1)
 *       ->orderBy('name')
 *       ->get();
 *
 *   // Subclass (gives you a named type and shorter call sites)
 *   class User extends Model {
 *       protected static string $table = 'users';
 *   }
 *   $user = new User(1);
 *   $users = User::query()->where('active', 1)->get();
 */
class Model implements IDB
{
    // -------------------------------------------------------------------------
    // Subclass contract
    // -------------------------------------------------------------------------

    /** Override in subclasses to skip passing the table name each time. */
    protected static string $table = '';

    /**
     * Global scopes — automatically applied to every query for this model.
     * Keyed by scope name so they can be removed individually.
     *
     * Override or populate via addGlobalScope() / removeGlobalScope().
     *
     * @var array<string, callable(ModelQuery): ModelQuery>
     */
    protected static array $globalScopes = [];

    /**
     * Universal scopes — applied to ALL model queries regardless of class.
     * Stored only on the base Model so they are truly shared (not per-subclass).
     * Register via Model::addUniversalScope() (e.g. from TenantMiddleware).
     *
     * @var array<string, callable(ModelQuery): void>
     */
    private static array $universalScopes = [];

    // -------------------------------------------------------------------------
    // Internal state
    // -------------------------------------------------------------------------

    private string $tableName;
    private ?string $primaryKey = null;
    private bool $exists = false;

    /** @var array<string, array{type:string, nullable:bool, pk:bool, default:mixed}> */
    private array $schema = [];

    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, array{mixed, mixed}> change log for activity logging */
    private array $changeLog = [];

    /** @var array<string, mixed> eagerly or lazily loaded relations */
    private array $relations = [];

    /**
     * Attribute casts — override in subclasses.
     *
     *   protected array $casts = [
     *       'metadata'   => 'array',      // JSON decode on read, encode on write
     *       'is_active'  => 'bool',
     *       'score'      => 'float',
     *       'created_at' => 'datetime',   // returns DateTimeImmutable
     *       'role'       => MyEnum::class, // BackedEnum::from($value)
     *   ];
     *
     * @var array<string, string>
     */
    protected array $casts = [];

    /** @var array<string, array<string, array{type:string, nullable:bool, pk:bool, default:mixed}>> */
    private static array $schemaCache = [];

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    /**
     * @param string|null $table  Table name (optional if subclass sets $table)
     * @param mixed       $id     PK value to load, array for bulk hydration, or null for new record
     */
    public function __construct(?string $table = null, mixed $id = null)
    {
        $this->tableName = $table ?? static::$table;
        if ($this->tableName === '') {
            throw new \InvalidArgumentException('Table name required: pass it as constructor argument or set $table in subclass.');
        }

        $this->loadSchema();

        if (is_array($id)) {
            $this->hydrateFromArray($id);
        } elseif ($id !== null) {
            $this->loadByPrimaryKey($id);
        }
    }

    // -------------------------------------------------------------------------
    // Static factories
    // -------------------------------------------------------------------------

    /**
     * Return a query builder for the given table.
     * Subclasses can call `static::query()` without a table name.
     */
    public static function query(?string $table = null): ModelQuery
    {
        $t = $table ?? static::$table;
        if ($t === '') {
            throw new \InvalidArgumentException('Table name required.');
        }
        return new ModelQuery($t, static::class);
    }

    /**
     * Hydrate an array of raw DB rows into Model instances.
     * Use this after a raw query (CTE, UNION, window function, subquery in FROM)
     * when ModelQuery cannot express the SQL.
     *
     *   $result = LazyMePHP::DB_CONNECTION()->query($cte, $bindings);
     *   $rows   = [];
     *   while ($row = $result->fetchArray()) $rows[] = $row;
     *   $models = Model::hydrate('users', $rows);
     *   // each model has all columns — schema columns + computed aliases
     */
    public static function hydrate(string $table, array $rows): array
    {
        return array_map(fn($row) => new static($table, $row), $rows);
    }

    /**
     * Load a single record by primary key, or null if not found.
     */
    public static function find(string $table, mixed $id): ?static
    {
        $model = new static($table, $id);
        return $model->exists ? $model : null;
    }

    /**
     * Register an observer for a table.
     * The observer's methods (creating, created, updating, updated, deleting, deleted, saving, saved)
     * are called automatically during model lifecycle events.
     *
     *   Model::observe('users', new UserObserver());
     */
    public static function observe(string $table, object $observer): void
    {
        ModelEvents::registerObserver($table, $observer);
    }

    // -------------------------------------------------------------------------
    // Model-level validation
    // -------------------------------------------------------------------------

    /**
     * Validation rules for this model's attributes.
     * Override in subclasses to enforce rules on Save().
     *
     *   protected static array $rules = [
     *       'email'  => 'required|email',
     *       'name'   => 'required|min:2|max:100',
     *       'age'    => 'integer|min:0',
     *       'status' => 'in:active,inactive,pending',
     *   ];
     *
     * Supported rules: required, email, integer, numeric, min:N, max:N, in:a,b,c, url, boolean
     */
    protected static array $rules = [];

    /**
     * Validate the model's current data against static::$rules.
     * Returns an array of error messages, empty when valid.
     *
     * @return array<string, list<string>>  field → list of error messages
     */
    public function validate(): array
    {
        $errors = [];

        foreach (static::$rules as $field => $ruleString) {
            $value  = $this->data[$field] ?? null;
            $rules  = explode('|', $ruleString);

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                $error = match ($name) {
                    'required' => ($value === null || $value === '') ? "{$field} is required." : null,
                    'email'    => ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL))
                                    ? "{$field} must be a valid email address." : null,
                    'integer'  => ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT))
                                    ? "{$field} must be an integer." : null,
                    'numeric'  => ($value !== null && $value !== '' && !is_numeric($value))
                                    ? "{$field} must be numeric." : null,
                    'boolean'  => ($value !== null && $value !== '' && !in_array($value, [0, 1, '0', '1', true, false], true))
                                    ? "{$field} must be boolean." : null,
                    'url'      => ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL))
                                    ? "{$field} must be a valid URL." : null,
                    'min'      => ($value !== null && $value !== '' && is_numeric($value) && (float)$value < (float)$param)
                                    ? "{$field} must be at least {$param}."
                                    : (($value !== null && !is_numeric($value) && mb_strlen((string)$value) < (int)$param)
                                    ? "{$field} must be at least {$param} characters." : null),
                    'max'      => ($value !== null && $value !== '' && is_numeric($value) && (float)$value > (float)$param)
                                    ? "{$field} must be at most {$param}."
                                    : (($value !== null && !is_numeric($value) && mb_strlen((string)$value) > (int)$param)
                                    ? "{$field} must be at most {$param} characters." : null),
                    'in'       => ($value !== null && $value !== '' && !in_array((string)$value, explode(',', (string)$param), true))
                                    ? "{$field} must be one of: {$param}." : null,
                    'confirmed' => (function () use ($value, $field): ?string {
                        $confirmation = $this->data[$field . '_confirmation'] ?? null;
                        return ($value !== $confirmation) ? "{$field} confirmation does not match." : null;
                    })(),
                    'unique'   => (function () use ($value, $field, $param): ?string {
                        if ($value === null || $value === '') return null;
                        [$table, $col, $exceptId] = array_pad(explode(',', (string)$param), 3, null);
                        $db  = \Core\LazyMePHP::DB_CONNECTION();
                        $sql = "SELECT COUNT(*) as \"cnt\" FROM \"{$table}\" WHERE \"{$col}\" = ?";
                        $args = [$value];
                        if ($exceptId !== null) {
                            $sql  .= " AND \"id\" != ?";
                            $args[] = $exceptId;
                        }
                        $result = $db->query($sql, $args);
                        $row    = $result->fetchArray();
                        return ((int) ($row['cnt'] ?? 0)) > 0 ? "{$field} is already taken." : null;
                    })(),
                    'exists'   => (function () use ($value, $field, $param): ?string {
                        if ($value === null || $value === '') return null;
                        [$table, $col] = array_pad(explode(',', (string)$param), 2, null);
                        $db     = \Core\LazyMePHP::DB_CONNECTION();
                        $result = $db->query("SELECT COUNT(*) as \"cnt\" FROM \"{$table}\" WHERE \"{$col}\" = ?", [$value]);
                        $row    = $result->fetchArray();
                        return ((int) ($row['cnt'] ?? 0)) === 0 ? "{$field} does not exist." : null;
                    })(),
                    default    => null,
                };

                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * True when validate() returns no errors.
     */
    public function passes(): bool
    {
        return empty($this->validate());
    }

    /**
     * Add a validation error to this model (for external use, e.g. service layer).
     * @var array<string, list<string>>
     */
    private array $validationErrors = [];

    public function addError(string $field, string $message): void
    {
        $this->validationErrors[$field][] = $message;
    }

    public function errors(): array
    {
        return array_merge_recursive($this->validate(), $this->validationErrors);
    }

    /** @internal Used by ModelQuery to apply global scopes without bypassing visibility. */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes;
    }

    /**
     * Register a scope that is automatically applied to every query across ALL models.
     * Useful for cross-cutting concerns like multi-tenancy.
     *
     *   Model::addUniversalScope('tenant', function(ModelQuery $q): void {
     *       if (Tenant::id() !== null) $q->where('tenant_id', Tenant::id());
     *   });
     */
    public static function addUniversalScope(string $name, callable $scope): void
    {
        self::$universalScopes[$name] = $scope;
    }

    public static function removeUniversalScope(string $name): void
    {
        unset(self::$universalScopes[$name]);
    }

    /** @internal */
    public static function getUniversalScopes(): array
    {
        return self::$universalScopes;
    }

    /**
     * Add a global scope that is automatically applied to every query for this model class.
     *
     *   class ActiveUser extends Model {
     *       protected static string $table = 'users';
     *   }
     *   ActiveUser::addGlobalScope('active', fn($q) => $q->where('active', 1));
     *
     *   // Now every ActiveUser::query()->get() silently adds WHERE active = 1
     */
    public static function addGlobalScope(string $name, callable $scope): void
    {
        static::$globalScopes[$name] = $scope;
    }

    /**
     * Remove a named global scope for this model class.
     *
     *   ActiveUser::removeGlobalScope('active');
     */
    public static function removeGlobalScope(string $name): void
    {
        unset(static::$globalScopes[$name]);
    }

    /**
     * Return a query builder with ALL global scopes bypassed.
     *
     *   ActiveUser::withoutGlobalScopes()->get(); // returns all users, including inactive
     */
    public static function withoutGlobalScopes(?string $table = null): ModelQuery
    {
        $t = $table ?? static::$table;
        if ($t === '') throw new \InvalidArgumentException('Table name required.');
        $q = new ModelQuery($t, static::class);
        $q->bypassGlobalScopes();
        return $q;
    }

    /**
     * Bulk-insert multiple rows into a table. Returns the number of rows inserted.
     *
     *   Model::insertMany('products', [
     *       ['name' => 'Widget', 'price' => 9.99],
     *       ['name' => 'Gadget', 'price' => 19.99],
     *   ]);
     */
    public static function insertMany(string $table, array $rows): int
    {
        if (empty($rows)) return 0;

        $db      = LazyMePHP::DB_CONNECTION();
        $columns = array_keys($rows[0]);
        $cols    = implode(', ', array_map(fn($k) => "\"$k\"", $columns));
        $ph      = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $count   = 0;

        foreach ($rows as $row) {
            $db->query("INSERT INTO \"{$table}\" ({$cols}) VALUES {$ph}", array_values($row));
            $count++;
        }

        return $count;
    }

    /**
     * Execute a callable inside a database transaction.
     * Rolls back automatically on exception.
     *
     *   Model::transaction(function() {
     *       $user = new Model('users');
     *       $user->name = 'Alice';
     *       $user->save();
     *       // ...
     *   });
     */
    public static function transaction(callable $callback): mixed
    {
        return LazyMePHP::DB_CONNECTION()->transaction($callback);
    }

    // -------------------------------------------------------------------------
    // Schema introspection
    // -------------------------------------------------------------------------

    private static ?string $schemaCacheDirOverride = null;

    private static function schemaCacheDir(): string
    {
        return self::$schemaCacheDirOverride ?? __DIR__ . '/../Cache/schema';
    }

    /** Override the cache directory — intended for tests only. Pass null to restore the default. */
    public static function setSchemaCacheDir(?string $dir): void
    {
        self::$schemaCacheDirOverride = $dir;
    }

    private function loadSchema(): void
    {
        if (isset(self::$schemaCache[$this->tableName])) {
            $this->schema = self::$schemaCache[$this->tableName];
            $this->primaryKey = $this->findPrimaryKey();
            return;
        }

        // Check file cache (OPcache-friendly, written by `schema:cache` CLI command)
        $cacheFile = self::schemaCacheDir() . '/' . $this->tableName . '.php';
        if (is_file($cacheFile)) {
            $schema = require $cacheFile;
            if (is_array($schema) && !empty($schema)) {
                self::$schemaCache[$this->tableName] = $schema;
                $this->schema = $schema;
                $this->primaryKey = $this->findPrimaryKey();
                return;
            }
        }

        $db = LazyMePHP::DB_CONNECTION();
        $dbType = strtolower(LazyMePHP::DB_TYPE() ?? 'mysql');

        switch ($dbType) {
            case 'sqlite':
                $result = $db->query("PRAGMA table_info(\"{$this->tableName}\")");
                while ($row = $result->fetchArray()) {
                    $this->schema[$row['name']] = [
                        'type'     => strtolower((string)$row['type']),
                        'nullable' => (int)$row['notnull'] === 0,
                        'pk'       => (int)$row['pk'] === 1,
                        'default'  => $row['dflt_value'],
                    ];
                }
                break;

            case 'mysql':
                $result = $db->query(
                    "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                     ORDER BY ORDINAL_POSITION",
                    [LazyMePHP::DB_NAME(), $this->tableName]
                );
                while ($row = $result->fetchArray()) {
                    $this->schema[$row['COLUMN_NAME']] = [
                        'type'     => strtolower((string)$row['DATA_TYPE']),
                        'nullable' => $row['IS_NULLABLE'] === 'YES',
                        'pk'       => $row['COLUMN_KEY'] === 'PRI',
                        'default'  => $row['COLUMN_DEFAULT'],
                    ];
                }
                break;

            case 'mssql':
                $result = $db->query(
                    "SELECT c.COLUMN_NAME, c.DATA_TYPE, c.IS_NULLABLE, c.COLUMN_DEFAULT,
                            CASE WHEN pk.COLUMN_NAME IS NOT NULL THEN 1 ELSE 0 END AS IS_PK
                     FROM INFORMATION_SCHEMA.COLUMNS c
                     LEFT JOIN (
                         SELECT ku.COLUMN_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                         JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE ku
                           ON tc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
                         WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY' AND tc.TABLE_NAME = ?
                     ) pk ON c.COLUMN_NAME = pk.COLUMN_NAME
                     WHERE c.TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
                    [$this->tableName, $this->tableName]
                );
                while ($row = $result->fetchArray()) {
                    $this->schema[$row['COLUMN_NAME']] = [
                        'type'     => strtolower((string)$row['DATA_TYPE']),
                        'nullable' => $row['IS_NULLABLE'] === 'YES',
                        'pk'       => (int)$row['IS_PK'] === 1,
                        'default'  => $row['COLUMN_DEFAULT'],
                    ];
                }
                break;

            default:
                throw new \RuntimeException("Unsupported DB type: $dbType");
        }

        self::$schemaCache[$this->tableName] = $this->schema;
        $this->primaryKey = $this->findPrimaryKey();
    }

    private function findPrimaryKey(): ?string
    {
        foreach ($this->schema as $col => $meta) {
            if ($meta['pk']) return $col;
        }
        return null;
    }

    /** Expose schema for use by the query builder and Select class. */
    public static function schemaFor(string $table): array
    {
        $dummy = new self($table);
        return $dummy->schema;
    }

    /** Flush the in-process schema cache (useful in tests). */
    public static function clearSchemaCache(): void
    {
        self::$schemaCache = [];
    }

    /**
     * List all table names.
     * Reads file names from the schema cache dir when populated (via schema:cache CLI).
     * Falls back to a live DB query when the cache dir is empty or absent.
     */
    public static function listTables(): array
    {
        $dir = self::schemaCacheDir();
        if (is_dir($dir)) {
            $files = glob($dir . '/*.php') ?: [];
            if (!empty($files)) {
                return array_values(array_map(fn($f) => basename($f, '.php'), $files));
            }
        }
        return self::listTablesFromDb();
    }

    private static function listTablesFromDb(): array
    {
        $db     = LazyMePHP::DB_CONNECTION();
        $dbType = strtolower(LazyMePHP::DB_TYPE() ?? 'mysql');

        $sql = match($dbType) {
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE '#__%' ESCAPE '#' ORDER BY name",
            'mysql'  => "SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . LazyMePHP::DB_NAME() . "' AND TABLE_NAME NOT LIKE '\\_\\_%'",
            'mssql'  => "SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME NOT LIKE '\\_\\_%'",
            default  => throw new \RuntimeException("Unsupported DB type: $dbType"),
        };

        $result = $db->query($sql);
        $tables = [];
        while ($row = $result->fetchArray()) {
            $name = $row['name'] ?? $row['TABLE_NAME'] ?? '';
            if ($name !== '') $tables[] = $name;
        }
        return $tables;
    }

    /**
     * Write a table's schema to a PHP file so future requests skip the DB query.
     * Called by `./LazyMePHP schema:cache`.
     */
    public static function warmSchemaCache(string $table): void
    {
        $schema = self::schemaFor($table);
        $dir    = self::schemaCacheDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file   = $dir . '/' . $table . '.php';
        $export = var_export($schema, true);
        file_put_contents($file, "<?php\n// Generated by LazyMePHP schema:cache — regenerate after schema changes.\nreturn $export;\n");
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * Remove schema cache files.
     * Pass a table name to clear one table, or null to clear all.
     * Called by `./LazyMePHP schema:clear`.
     */
    public static function clearFileSchemaCache(?string $table = null): void
    {
        $dir = self::schemaCacheDir();
        if (!is_dir($dir)) return;

        $files = $table !== null
            ? [$dir . '/' . $table . '.php']
            : (glob($dir . '/*.php') ?: []);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($file, true);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Hydration
    // -------------------------------------------------------------------------

    private function loadByPrimaryKey(mixed $value): void
    {
        if ($this->primaryKey === null) return;

        $db = LazyMePHP::DB_CONNECTION();
        $result = $db->query(
            "SELECT * FROM \"{$this->tableName}\" WHERE \"{$this->primaryKey}\" = ?",
            [$value]
        );
        $row = $result->fetchArray();
        if ($row !== null) {
            $this->data  = $row;
            $this->exists = true;
        }
    }

    private function hydrateFromArray(array $data): void
    {
        // Store ALL columns from the DB result — this preserves computed columns
        // (COUNT, SUM, aliases from JOINs, etc.) that are not in the table schema.
        // The schema check in __set() still guards user assignments; this path bypasses
        // that guard intentionally because the source is a trusted DB result.
        foreach ($data as $key => $value) {
            $this->data[$key] = $value;
        }
        // Only mark as existing when the primary key is present and non-null
        // (array hydration from DB results always includes the PK; factory-generated
        // attribute bags do not, so they must go through INSERT on Save())
        $this->exists = $this->primaryKey !== null && isset($this->data[$this->primaryKey]);
    }

    // -------------------------------------------------------------------------
    // Magic property access
    // -------------------------------------------------------------------------

    public function __get(string $name): mixed
    {
        // Already-loaded relation (eager or previous lazy load)
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }
        // Raw data column (with optional cast)
        if (array_key_exists($name, $this->data)) {
            return $this->applyCast($name, $this->data[$name]);
        }
        // Lazy-load: call the relationship method if it exists and returns a Relationship
        if (method_exists($this, $name)) {
            $rel = $this->$name();
            if ($rel instanceof Relationship) {
                $result = $rel->getResults();
                $this->relations[$name] = $result;
                return $result;
            }
        }
        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        if (!array_key_exists($name, $this->schema)) return;

        if (LazyMePHP::ACTIVITY_LOG() && array_key_exists($name, $this->data) && $this->data[$name] !== $value) {
            $this->changeLog[$name] = [$this->data[$name], $value];
        }
        // Encode arrays/objects to JSON when cast is 'array'|'json'
        $cast = $this->casts[$name] ?? null;
        if ($cast !== null && in_array($cast, ['array', 'json'], true) && (is_array($value) || is_object($value))) {
            $value = json_encode($value);
        }
        $this->data[$name] = $value;
    }

    /** Apply the declared cast for $name to $value. */
    private function applyCast(string $name, mixed $value): mixed
    {
        $cast = $this->casts[$name] ?? null;
        if ($cast === null || $value === null) return $value;

        return match ($cast) {
            'int', 'integer'    => (int)$value,
            'float', 'double'   => (float)$value,
            'bool', 'boolean'   => (bool)$value,
            'string'            => (string)$value,
            'array', 'json'     => is_string($value)
                                    ? ((array)(json_decode($value, true) ?? []))
                                    : (array)$value,
            'datetime', 'date'  => new \DateTimeImmutable((string)$value),
            'timestamp'         => new \DateTimeImmutable('@' . (int)$value),
            default             => (
                enum_exists($cast) && is_subclass_of($cast, \BackedEnum::class)
                    ? $cast::from($value)
                    : $value
            ),
        };
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]) || isset($this->relations[$name]);
    }

    /** Called by Relationship::eagerLoad() to inject pre-fetched results. */
    public function setRelation(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
    }

    /** Return all loaded relation results keyed by relation name. */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Proxy GetX / SetX calls so Blade views generated against the old model
     * classes continue to work without modification.
     */
    public function __call(string $name, array $args): mixed
    {
        if (str_starts_with($name, 'Get')) {
            $field = lcfirst(substr($name, 3));
            return $this->data[$field] ?? null;
        }
        if (str_starts_with($name, 'Set') && count($args) === 1) {
            $field = lcfirst(substr($name, 3));
            $this->$field = $args[0];
            return $this;
        }
        throw new \BadMethodCallException("Call to undefined method " . static::class . "::$name()");
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getPrimaryKey(): mixed
    {
        return $this->primaryKey !== null ? ($this->data[$this->primaryKey] ?? null) : null;
    }

    public function getPrimaryKeyColumn(): ?string
    {
        return $this->primaryKey;
    }

    public function getTable(): string
    {
        return $this->tableName;
    }

    public function getColumns(): array
    {
        return array_keys($this->schema);
    }

    public function toArray(): array
    {
        $out = $this->data;
        foreach ($this->relations as $name => $value) {
            if (is_array($value)) {
                $out[$name] = array_map(fn($v) => $v instanceof Model ? $v->toArray() : $v, $value);
            } else {
                $out[$name] = $value instanceof Model ? $value->toArray() : $value;
            }
        }
        return $out;
    }

    /**
     * Return only the listed columns (field mask).
     *
     * @param list<string> $columns
     */
    public function only(array $columns): array
    {
        return array_intersect_key($this->data, array_flip($columns));
    }

    // -------------------------------------------------------------------------
    // Relationship helpers — call these inside subclass relationship methods
    // -------------------------------------------------------------------------

    /**
     * One-to-many: related table holds a FK pointing back to this model.
     *
     *   public function posts(): HasMany {
     *       return $this->hasMany('posts', 'user_id');
     *   }
     */
    protected function hasMany(string $relatedTable, string $foreignKey, ?string $localKey = null): HasMany
    {
        return new HasMany($this, $relatedTable, $foreignKey, $localKey ?? $this->primaryKey ?? 'id');
    }

    /**
     * One-to-one: related table holds a FK pointing back to this model.
     *
     *   public function profile(): HasOne {
     *       return $this->hasOne('profiles', 'user_id');
     *   }
     */
    protected function hasOne(string $relatedTable, string $foreignKey, ?string $localKey = null): HasOne
    {
        return new HasOne($this, $relatedTable, $foreignKey, $localKey ?? $this->primaryKey ?? 'id');
    }

    /**
     * Inverse: this model's table holds the FK.
     *
     *   public function author(): BelongsTo {
     *       return $this->belongsTo('users', 'user_id');
     *   }
     *
     * @param string $foreignKey  Column on THIS table (e.g. 'user_id')
     * @param string|null $localKey   PK on the RELATED table (default 'id')
     */
    protected function belongsTo(string $relatedTable, string $foreignKey, ?string $localKey = null): BelongsTo
    {
        return new BelongsTo($this, $relatedTable, $foreignKey, $localKey ?? 'id');
    }

    /**
     * Many-to-many through a pivot table.
     *
     *   public function tags(): BelongsToMany {
     *       return $this->belongsToMany('tags', 'post_tags', 'post_id', 'tag_id');
     *   }
     *
     * @param string $pivotTable        The join table
     * @param string $foreignKey        Pivot column → this model's PK
     * @param string $relatedForeignKey Pivot column → related model's PK
     * @param string|null $localKey     This model's PK (auto-detected when null)
     * @param string|null $relatedKey   Related model's PK (auto-detected when null)
     */
    protected function belongsToMany(
        string  $relatedTable,
        string  $pivotTable,
        string  $foreignKey,
        string  $relatedForeignKey,
        ?string $localKey   = null,
        ?string $relatedKey = null,
    ): BelongsToMany {
        return new BelongsToMany(
            $this, $relatedTable, $pivotTable,
            $foreignKey, $relatedForeignKey,
            $localKey ?? $this->primaryKey ?? 'id',
            $relatedKey,
        );
    }

    // -------------------------------------------------------------------------
    // IDB — CRUD
    // -------------------------------------------------------------------------

    public function Save(): mixed
    {
        $db  = LazyMePHP::DB_CONNECTION();
        $row = array_filter($this->data, fn($k) => $k !== $this->primaryKey, ARRAY_FILTER_USE_KEY);

        // Fire saving + creating|updating (cancellable)
        if (!ModelEvents::fire($this->tableName, 'saving', $this)) return false;

        if ($this->exists) {
            if (!ModelEvents::fire($this->tableName, 'updating', $this)) return false;

            $set    = implode(', ', array_map(fn($k) => "\"$k\" = :$k", array_keys($row)));
            $params = array_combine(array_map(fn($k) => ":$k", array_keys($row)), array_values($row));
            $params[":{$this->primaryKey}"] = $this->data[$this->primaryKey];
            $ret    = $db->query("UPDATE \"{$this->tableName}\" SET $set WHERE \"{$this->primaryKey}\" = :{$this->primaryKey}", $params);
            $method = 'U';
        } else {
            if (!ModelEvents::fire($this->tableName, 'creating', $this)) return false;

            $cols       = implode(', ', array_map(fn($k) => "\"$k\"", array_keys($row)));
            $holders    = implode(', ', array_map(fn($k) => ":$k", array_keys($row)));
            $params     = array_combine(array_map(fn($k) => ":$k", array_keys($row)), array_values($row));
            $ret        = $db->query("INSERT INTO \"{$this->tableName}\" ($cols) VALUES ($holders)", $params);
            if ($this->primaryKey !== null) {
                $this->data[$this->primaryKey] = $db->getLastInsertedId();
            }
            $this->exists = true;
            $method = 'I';
        }

        if (LazyMePHP::ACTIVITY_LOG()) {
            if ($method === 'I') {
                $insertLog = [];
                foreach ($row as $field => $value) {
                    $insertLog[$field] = [null, $value];
                }
                \Core\Helpers\LoggingHelper::logInsert($this->tableName, $insertLog, (string)$this->getPrimaryKey());
            } elseif (!empty($this->changeLog)) {
                \Core\Helpers\LoggingHelper::logUpdate($this->tableName, $this->changeLog, (string)$this->primaryKey, (string)$this->getPrimaryKey());
            }
            $this->changeLog = [];
        }

        // Fire saved + created|updated
        ModelEvents::fire($this->tableName, $method === 'I' ? 'created' : 'updated', $this);
        ModelEvents::fire($this->tableName, 'saved', $this);

        ModelQuery::invalidateTable($this->tableName);

        return $ret;
    }

    public function Delete(): bool
    {
        if ($this->primaryKey === null || !isset($this->data[$this->primaryKey])) {
            return false;
        }

        if (!ModelEvents::fire($this->tableName, 'deleting', $this)) return false;

        if (LazyMePHP::ACTIVITY_LOG()) {
            \Core\Helpers\LoggingHelper::logDelete($this->tableName, (string)$this->primaryKey, (string)$this->getPrimaryKey());
        }

        LazyMePHP::DB_CONNECTION()->query(
            "DELETE FROM \"{$this->tableName}\" WHERE \"{$this->primaryKey}\" = ?",
            [$this->data[$this->primaryKey]]
        );
        $this->exists = false;

        ModelEvents::fire($this->tableName, 'deleted', $this);
        ModelQuery::invalidateTable($this->tableName);
        return true;
    }

    /**
     * Atomically increment a column on this record by $amount.
     * Updates the in-memory value so the model reflects the new state.
     *
     *   $post->increment('views');
     *   $product->increment('stock', 10);
     */
    public function increment(string $column, int|float $amount = 1): bool
    {
        if (!$this->exists || $this->primaryKey === null) return false;
        (new ModelQuery($this->tableName))
            ->where($this->primaryKey, $this->data[$this->primaryKey])
            ->increment($column, $amount);
        $this->data[$column] = ($this->data[$column] ?? 0) + $amount;
        return true;
    }

    /**
     * Atomically decrement a column on this record by $amount.
     *
     *   $product->decrement('stock');
     */
    public function decrement(string $column, int|float $amount = 1): bool
    {
        return $this->increment($column, -$amount);
    }

    /**
     * Update a timestamp column to now without touching other fields.
     * Defaults to `updated_at` if the column exists, otherwise the first DATETIME/TIMESTAMP column.
     *
     *   $model->touch();
     *   $model->touch('last_seen_at');
     */
    public function touch(?string $column = null): bool
    {
        if (!$this->exists || $this->primaryKey === null) return false;

        if ($column === null) {
            if (array_key_exists('updated_at', $this->schema)) {
                $column = 'updated_at';
            } else {
                foreach ($this->schema as $col => $def) {
                    if (str_contains(strtolower($def['type']), 'datetime')
                        || str_contains(strtolower($def['type']), 'timestamp')) {
                        $column = $col;
                        break;
                    }
                }
            }
        }

        if ($column === null) return false;

        $now = date('Y-m-d H:i:s');
        LazyMePHP::DB_CONNECTION()->query(
            "UPDATE \"{$this->tableName}\" SET \"$column\" = ? WHERE \"{$this->primaryKey}\" = ?",
            [$now, $this->data[$this->primaryKey]]
        );
        $this->data[$column] = $now;
        ModelQuery::invalidateTable($this->tableName);
        return true;
    }

    /**
     * @param array<string, list<string>>|null $mask  Keys: table name; values: allowed columns
     */
    public function Serialize(?array $mask = null): array
    {
        if ($mask !== null && array_key_exists($this->tableName, $mask)) {
            return $this->only($mask[$this->tableName]);
        }
        return $this->data;
    }
}
