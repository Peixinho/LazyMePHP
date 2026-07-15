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
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $this->schema)) {
                $this->data[$key] = $value;
            }
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
        // Raw data column
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
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
        $this->data[$name] = $value;
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

// =============================================================================
// ModelQuery — fluent query builder
// =============================================================================

/**
 * Fluent query builder returned by Model::query().
 *
 * All methods are chainable; call get() or first() to execute.
 *
 *   $users = Model::query('users')
 *       ->where('active', 1)
 *       ->where('age', 18, '>=')
 *       ->orWhere('admin', 1)
 *       ->orderBy('name')
 *       ->limit(20)
 *       ->get();
 */
class ModelQuery
{
    private string $tableName;
    private string $modelClass;
    /** @var list<string> */
    private array $conditions = [];
    /** @var list<mixed> */
    private array $bindings = [];
    private string $orderClause = '';
    private string $groupClause = '';
    private int $limitCount = 0;
    private int $limitOffset = 0;
    private bool $hasCondition = false;
    /** @var list<string> relation names to eager-load */
    private array $with = [];
    private bool $includeTrashed = false;
    private bool $onlyTrashedFlag = false;
    private int $cacheTtl = 0;
    private ?string $cacheKey = null;
    private bool $skipGlobalScopes = false;

    public function __construct(string $tableName, ?string $modelClass = null)
    {
        $this->tableName  = $tableName;
        $this->modelClass = $modelClass ?? Model::class;
    }

    /** Called by Model::withoutGlobalScopes() — skips global scope application. */
    public function bypassGlobalScopes(): static
    {
        $this->skipGlobalScopes = true;
        return $this;
    }

    /**
     * Cache the query result for the given number of seconds.
     * Uses APCu when available, otherwise a per-process in-memory cache (tests/dev).
     *
     *   User::query()->where('active', 1)->remember(60)->get();
     */
    public function remember(int $ttl, ?string $key = null): static
    {
        $this->cacheTtl  = $ttl;
        $this->cacheKey  = $key;
        return $this;
    }

    /**
     * Apply a named local scope defined on the model subclass.
     * Scopes are public methods prefixed with "scope":
     *
     *   class Post extends Model {
     *       public function scopePublished(ModelQuery $q): ModelQuery {
     *           return $q->where('published', 1);
     *       }
     *   }
     *
     *   Post::query()->scope('published')->get();
     *   // or via magic method:
     *   Post::query()->published()->get();
     */
    public function scope(string $name, mixed ...$args): static
    {
        $method = 'scope' . ucfirst($name);
        if (!method_exists($this->modelClass, $method)) {
            throw new \BadMethodCallException(
                "Scope '{$name}' does not exist on {$this->modelClass}"
            );
        }
        $instance = new ($this->modelClass)($this->tableName);
        return $instance->$method($this, ...$args) ?? $this;
    }

    /** Proxy scope calls as fluent methods: ->published() → ->scope('published') */
    public function __call(string $name, array $args): static
    {
        return $this->scope($name, ...$args);
    }

    /** Include soft-deleted rows in results (does not add any extra filter). */
    public function withTrashed(): static
    {
        $this->includeTrashed = true;
        return $this;
    }

    /** Return only soft-deleted rows. */
    public function onlyTrashed(): static
    {
        $this->includeTrashed  = true;
        $this->onlyTrashedFlag = true;
        return $this;
    }

    public function where(string $column, mixed $value, string $operator = '=', string $logic = 'AND'): static
    {
        $connector = $this->hasCondition ? " $logic " : '';
        $this->conditions[] = "{$connector}\"{$column}\" {$operator} ?";
        $this->bindings[]   = $value;
        $this->hasCondition = true;
        return $this;
    }

    public function orWhere(string $column, mixed $value, string $operator = '='): static
    {
        return $this->where($column, $value, $operator, 'OR');
    }

    public function whereLike(string $column, string $value, string $logic = 'AND'): static
    {
        return $this->where($column, "%{$value}%", 'LIKE', $logic);
    }

    public function whereNull(string $column, string $logic = 'AND'): static
    {
        $connector = $this->hasCondition ? " $logic " : '';
        $this->conditions[] = "{$connector}\"{$column}\" IS NULL";
        $this->hasCondition = true;
        return $this;
    }

    public function whereNotNull(string $column, string $logic = 'AND'): static
    {
        $connector = $this->hasCondition ? " $logic " : '';
        $this->conditions[] = "{$connector}\"{$column}\" IS NOT NULL";
        $this->hasCondition = true;
        return $this;
    }

    /**
     * Add a WHERE … IN (…) condition.
     * Passing an empty array produces a permanent false condition (returns no rows).
     *
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values, string $logic = 'AND'): static
    {
        $connector = $this->hasCondition ? " $logic " : '';
        if (empty($values)) {
            $this->conditions[] = "{$connector}1=0";
            $this->hasCondition = true;
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->conditions[] = "{$connector}\"{$column}\" IN ({$placeholders})";
        array_push($this->bindings, ...$values);
        $this->hasCondition = true;
        return $this;
    }

    /**
     * Eagerly load the named relationships when get() executes.
     * Only works when query() is called on a Model subclass that defines
     * the relationship methods.
     *
     *   User::query()->with('posts', 'profile')->get();
     */
    public function with(string ...$relations): static
    {
        $this->with = array_merge($this->with, $relations);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $d = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderClause .= ($this->orderClause ? ', ' : '') . "\"{$column}\" {$d}";
        return $this;
    }

    public function groupBy(string $column): static
    {
        $this->groupClause .= ($this->groupClause ? ', ' : '') . "\"{$column}\"";
        return $this;
    }

    public function limit(int $count, int $offset = 0): static
    {
        $this->limitCount  = $count;
        $this->limitOffset = $offset;
        return $this;
    }

    public function count(): int
    {
        // Apply global scopes (unless bypassed)
        if (!$this->skipGlobalScopes && method_exists($this->modelClass, 'getGlobalScopes')) {
            foreach (($this->modelClass)::getGlobalScopes() as $scope) {
                $scope($this);
            }
        }

        $db     = LazyMePHP::DB_CONNECTION();
        $conds  = $this->conditions;
        $binds  = $this->bindings;
        $hasCond = $this->hasCondition;

        if (method_exists($this->modelClass, 'softDeleteColumn')) {
            $col = ($this->modelClass)::softDeleteColumn();
            if (!$this->includeTrashed) {
                $conds[] = ($hasCond ? ' AND ' : '') . "\"{$col}\" IS NULL";
            } elseif ($this->onlyTrashedFlag) {
                $conds[] = ($hasCond ? ' AND ' : '') . "\"{$col}\" IS NOT NULL";
            }
        }

        $where  = $conds ? 'WHERE ' . implode('', $conds) : '';
        $result = $db->query("SELECT COUNT(*) AS cnt FROM \"{$this->tableName}\" {$where}", $binds);
        $row    = $result->fetchArray();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Execute the query and return model instances.
     * When ->with() relations are specified and the model class defines them,
     * they are loaded in a single batch query each (no N+1).
     *
     * @return list<Model>
     */
    public function get(): array
    {
        // Apply global scopes (unless bypassed)
        if (!$this->skipGlobalScopes && method_exists($this->modelClass, 'getGlobalScopes')) {
            foreach (($this->modelClass)::getGlobalScopes() as $scope) {
                $scope($this);
            }
        }

        // Return from cache when available
        if ($this->cacheTtl > 0) {
            $cached = $this->fromCache();
            if ($cached !== null) {
                $class = $this->modelClass;
                return array_map(fn($row) => new $class($this->tableName, $row), $cached);
            }
        }

        $db      = LazyMePHP::DB_CONNECTION();
        $conds   = $this->conditions;
        $binds   = $this->bindings;
        $hasCond = $this->hasCondition;

        // Auto-apply soft-delete filter when the model uses the SoftDeletes trait
        if (method_exists($this->modelClass, 'softDeleteColumn')) {
            $col = ($this->modelClass)::softDeleteColumn();
            if (!$this->includeTrashed) {
                $prefix   = $hasCond ? ' AND ' : '';
                $conds[]  = "{$prefix}\"{$col}\" IS NULL";
                $hasCond  = true;
            } elseif ($this->onlyTrashedFlag) {
                $prefix   = $hasCond ? ' AND ' : '';
                $conds[]  = "{$prefix}\"{$col}\" IS NOT NULL";
                $hasCond  = true;
            }
        }

        $where  = $conds ? 'WHERE ' . implode('', $conds) : '';
        $group  = $this->groupClause ? "GROUP BY {$this->groupClause}" : '';
        $order  = $this->orderClause ? "ORDER BY {$this->orderClause}" : '';
        $limit  = $this->limitCount  ? $db->limit($this->limitCount, $this->limitOffset) : '';

        $result = $db->query(
            "SELECT * FROM \"{$this->tableName}\" {$where} {$group} {$order} {$limit}",
            $binds
        );

        $class = $this->modelClass;
        $rows  = [];
        while ($row = $result->fetchArray()) {
            $rows[] = new $class($this->tableName, $row);
        }

        // Eager-load relationships — one batch query per relation, no N+1
        if (!empty($rows) && !empty($this->with)) {
            $representative = $rows[0];
            foreach ($this->with as $relation) {
                if (!method_exists($representative, $relation)) continue;
                $rel = $representative->$relation();
                if ($rel instanceof \Core\Relationships\Relationship) {
                    $rel->eagerLoad($rows, $relation);
                }
            }
        }

        // Store in cache after fetching
        if ($this->cacheTtl > 0) {
            $this->toCache($rows);
        }

        return $rows;
    }

    /**
     * Return only the first matching record, or null.
     */
    public function first(): ?Model
    {
        $rows = $this->limit(1)->get();
        return $rows[0] ?? null;
    }

    /**
     * Return each row as a plain associative array instead of a Model.
     *
     * @param list<string>|null $columns  Columns to include (null = all)
     * @return list<array<string,mixed>>
     */
    public function toArray(?array $columns = null): array
    {
        $models = $this->get();
        return array_map(
            fn(Model $m) => $columns !== null ? $m->only($columns) : $m->toArray(),
            $models
        );
    }

    /**
     * Paginate results. Returns an array with data + pagination metadata.
     *
     *   $page = User::query()->where('active', 1)->paginate(15, 2);
     *   // $page['data']         → list<Model>
     *   // $page['total']        → total matching rows
     *   // $page['per_page']     → 15
     *   // $page['current_page'] → 2
     *   // $page['last_page']    → ceil(total/per_page)
     *   // $page['from']         → 16
     *   // $page['to']           → 30
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page    = max(1, $page);
        $total   = $this->count();
        $offset  = ($page - 1) * $perPage;
        $data    = $this->limit($perPage, $offset)->get();
        $from    = $total === 0 ? 0 : $offset + 1;
        $to      = min($offset + $perPage, $total);

        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $total === 0 ? 1 : (int)ceil($total / $perPage),
            'from'         => $from,
            'to'           => $to,
        ];
    }

    /**
     * Bulk-update all rows matching the current WHERE conditions.
     * Returns the number of affected rows.
     *
     *   Model::query('posts')->where('user_id', 5)->update(['status' => 'draft']);
     */
    public function update(array $data): int
    {
        if (empty($data)) return 0;

        $db     = LazyMePHP::DB_CONNECTION();
        $conds  = $this->conditions;
        $binds  = $this->bindings;

        $set     = implode(', ', array_map(fn($k) => "\"$k\" = ?", array_keys($data)));
        $where   = $conds ? 'WHERE ' . implode('', $conds) : '';
        $params  = array_merge(array_values($data), $binds);

        $db->query("UPDATE \"{$this->tableName}\" SET {$set} {$where}", $params);
        return 1; // PDO rowCount not exposed in all drivers; return 1 as a success signal
    }

    /**
     * Hard-delete all rows matching the current WHERE conditions.
     * (Use Model::Delete() for single soft-delete-aware deletion.)
     *
     *   Model::query('sessions')->where('expires_at', date('Y-m-d'), '<')->bulkDelete();
     */
    public function bulkDelete(): void
    {
        $db    = LazyMePHP::DB_CONNECTION();
        $where = $this->conditions ? 'WHERE ' . implode('', $this->conditions) : '';
        $db->query("DELETE FROM \"{$this->tableName}\" {$where}", $this->bindings);
    }

    // -------------------------------------------------------------------------
    // Internal caching helpers
    // -------------------------------------------------------------------------

    /** @var array<string, array{expires:int, data:list<array>}> */
    private static array $memCache = [];

    private function resolvedCacheKey(): string
    {
        if ($this->cacheKey !== null) return $this->cacheKey;
        $where = $this->conditions ? implode('', $this->conditions) : '';
        return 'mq_' . md5($this->tableName . '|' . $where . '|' . implode(',', $this->bindings));
    }

    private function fromCache(): ?array
    {
        if ($this->cacheTtl <= 0) return null;
        $key = $this->resolvedCacheKey();
        return \Core\Cache\Cache::get($key);
    }

    private function toCache(array $rows): void
    {
        if ($this->cacheTtl <= 0) return;
        $key  = $this->resolvedCacheKey();
        $data = array_map(fn($m) => $m->toArray(), $rows);
        \Core\Cache\Cache::set($key, $data, $this->cacheTtl);
    }

    /** Flush the query cache (delegates to the configured cache driver). */
    public static function clearMemCache(): void
    {
        self::$memCache = [];
        \Core\Cache\Cache::flush();
    }
}
