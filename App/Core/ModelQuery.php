<?php

declare(strict_types=1);

namespace Core;

use Core\LazyMePHP;

/**
 * Fluent query builder returned by Model::query().
 *
 * Usage:
 *   Model::query('users')->where('active', 1)->orderBy('name')->get();
 *   User::query()->with('posts')->paginate(15, (int)($_GET['page'] ?? 1));
 */
class ModelQuery
{
    private string $tableName;
    private string $modelClass;
    /** @var list<string> */
    private array $conditions = [];
    /** @var list<mixed> */
    private array $bindings = [];
    /** @var list<string> join fragments */
    private array $joins = [];
    /** @var list<string> column expressions for SELECT (empty = *) */
    private array $selectColumns = [];
    /** @var list<string> HAVING fragments */
    private array $havingClauses = [];
    /** @var list<mixed> */
    private array $havingBindings = [];
    private string $orderClause = '';
    private string $groupClause = '';
    private int $limitCount = 0;
    private int $limitOffset = 0;
    private bool $hasCondition = false;
    /** @var list<string> relation names to eager-load */
    private array $with = [];
    /** @var list<string> relation names to count via subquery */
    private array $withCount = [];
    /** @var list<array{relation: string, fn: string, column: string, alias: string}> */
    private array $withAggregates = [];
    private bool $includeTrashed = false;
    private bool $onlyTrashedFlag = false;
    private int $cacheTtl = 0;
    private ?string $cacheKey = null;
    private bool $skipGlobalScopes = false;
    private bool $globalScopesApplied = false;

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

    /** The table this query targets — used by universal scopes to check schema. */
    public function getTable(): string
    {
        return $this->tableName;
    }

    /** Apply global scopes exactly once (idempotent). */
    private function applyGlobalScopes(): void
    {
        if ($this->skipGlobalScopes || $this->globalScopesApplied) return;
        $this->globalScopesApplied = true;
        // Universal scopes (apply to every model, e.g. multi-tenancy)
        foreach (Model::getUniversalScopes() as $scope) {
            $scope($this);
        }
        // Per-class global scopes
        if (method_exists($this->modelClass, 'getGlobalScopes')) {
            foreach (($this->modelClass)::getGlobalScopes() as $scope) {
                $scope($this);
            }
        }
    }

    /**
     * Restrict the columns returned by SELECT.
     *
     *   User::query()->select('id', 'name', 'email')->get();
     *   User::query()->select('COUNT(*) as cnt')->get();
     */
    public function select(string ...$columns): static
    {
        $this->selectColumns = array_merge($this->selectColumns, $columns);
        return $this;
    }

    /**
     * Add a JOIN clause.
     *
     *   Post::query()
     *       ->join('users', 'posts.user_id', 'users.id')
     *       ->select('posts.*', 'users.name as author')
     *       ->get();
     */
    public function join(string $table, string $localKey, string $foreignKey, string $type = 'INNER'): static
    {
        $this->joins[] = strtoupper($type) . " JOIN \"{$table}\" ON {$this->quoteKey($localKey)} = {$this->quoteKey($foreignKey)}";
        return $this;
    }

    public function leftJoin(string $table, string $localKey, string $foreignKey): static
    {
        return $this->join($table, $localKey, $foreignKey, 'LEFT');
    }

    public function rightJoin(string $table, string $localKey, string $foreignKey): static
    {
        return $this->join($table, $localKey, $foreignKey, 'RIGHT');
    }

    /** Quote a key that may be `table.column` or just `column`. */
    private function quoteKey(string $key): string
    {
        if (str_contains($key, '.')) {
            [$t, $c] = explode('.', $key, 2);
            return "\"{$t}\".\"{$c}\"";
        }
        return "\"{$key}\"";
    }

    /**
     * Add a HAVING condition (use after groupBy()).
     *
     *   Model::query('orders')
     *       ->select('user_id', 'COUNT(*) as cnt')
     *       ->groupBy('user_id')
     *       ->having('cnt', 5, '>=')
     *       ->get();
     */
    public function having(string $column, mixed $value, string $operator = '='): static
    {
        $prefix                = $this->havingClauses ? ' AND ' : '';
        $this->havingClauses[] = "{$prefix}\"{$column}\" {$operator} ?";
        $this->havingBindings[] = $value;
        return $this;
    }

    /**
     * Add a raw WHERE fragment (caller is responsible for safety).
     *
     *   ->whereRaw('"score" > "baseline" * 1.5')
     *   ->whereRaw('"tag" IN (?, ?)', ['php', 'framework'])
     */
    public function whereRaw(string $sql, array $bindings = [], string $logic = 'AND'): static
    {
        $connector = $this->hasCondition ? " {$logic} " : '';
        $this->conditions[] = $connector . $sql;
        array_push($this->bindings, ...$bindings);
        $this->hasCondition = true;
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

    /**
     * Add a correlated COUNT subquery for each named relation.
     * The result is available as `$model->relation_count` (int).
     *
     *   User::query()->withCount('posts', 'comments')->get();
     *   // $user->posts_count, $user->comments_count
     */
    public function withCount(string ...$relations): static
    {
        $this->withCount = array_merge($this->withCount, $relations);
        return $this;
    }

    /**
     * Add AVG / SUM / MIN / MAX aggregate subqueries for a relation column.
     * Result is available as `$model->{relation}_{fn}_{column}` (e.g. `posts_avg_score`).
     *
     *   User::query()->withAvg('posts', 'score')->withSum('orders', 'amount')->get();
     *   // $user->posts_avg_score, $user->orders_sum_amount
     */
    public function withAvg(string $relation, string $column): static
    {
        return $this->withAggregate($relation, 'AVG', $column);
    }

    public function withSum(string $relation, string $column): static
    {
        return $this->withAggregate($relation, 'SUM', $column);
    }

    public function withMin(string $relation, string $column): static
    {
        return $this->withAggregate($relation, 'MIN', $column);
    }

    public function withMax(string $relation, string $column): static
    {
        return $this->withAggregate($relation, 'MAX', $column);
    }

    private function withAggregate(string $relation, string $fn, string $column): static
    {
        $alias                   = $relation . '_' . strtolower($fn) . '_' . $column;
        $this->withAggregates[]  = compact('relation', 'fn', 'column', 'alias');
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
        $this->applyGlobalScopes();

        $db      = LazyMePHP::DB_CONNECTION();
        $conds   = $this->conditions;
        $binds   = $this->bindings;
        $hasCond = $this->hasCondition;

        if (method_exists($this->modelClass, 'softDeleteColumn')) {
            $col = ($this->modelClass)::softDeleteColumn();
            if (!$this->includeTrashed) {
                $conds[] = ($hasCond ? ' AND ' : '') . "\"{$col}\" IS NULL";
            } elseif ($this->onlyTrashedFlag) {
                $conds[] = ($hasCond ? ' AND ' : '') . "\"{$col}\" IS NOT NULL";
            }
        }

        $joins  = $this->joins ? ' ' . implode(' ', $this->joins) : '';
        $where  = $conds ? 'WHERE ' . implode('', $conds) : '';
        $result = $db->query("SELECT COUNT(*) AS cnt FROM \"{$this->tableName}\"{$joins} {$where}", $binds);
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
        $this->applyGlobalScopes();

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

        // Build withCount + withAggregate subqueries
        $extraAliases    = [];
        $extraSubqueries = [];

        if (!empty($this->withCount) || !empty($this->withAggregates)) {
            $dummy = new $this->modelClass($this->tableName, null);

            foreach ($this->withCount as $relation) {
                if (!method_exists($dummy, $relation)) continue;
                $rel = $dummy->$relation();
                if (!($rel instanceof \Core\Relationships\Relationship)) continue;
                $alias             = $relation . '_count';
                $extraAliases[]    = [$alias, 'int'];
                $extraSubqueries[] = $rel->countSubquery($this->tableName) . " AS \"{$alias}\"";
            }

            foreach ($this->withAggregates as $agg) {
                if (!method_exists($dummy, $agg['relation'])) continue;
                $rel = $dummy->{$agg['relation']}();
                if (!($rel instanceof \Core\Relationships\Relationship)) continue;
                $alias             = $agg['alias'];
                $extraAliases[]    = [$alias, 'float'];
                $extraSubqueries[] = $rel->aggregateSubquery($this->tableName, $agg['fn'], $agg['column'])
                                   . " AS \"{$alias}\"";
            }
        }

        $base   = $this->selectColumns ? implode(', ', $this->selectColumns) : "\"{$this->tableName}\".*";
        $select = empty($extraSubqueries) ? $base : $base . ', ' . implode(', ', $extraSubqueries);
        $joins  = $this->joins ? ' ' . implode(' ', $this->joins) : '';
        $where  = $conds ? 'WHERE ' . implode('', $conds) : '';
        $group  = $this->groupClause ? "GROUP BY {$this->groupClause}" : '';
        $having = $this->havingClauses ? 'HAVING ' . implode('', $this->havingClauses) : '';
        $order  = $this->orderClause ? "ORDER BY {$this->orderClause}" : '';
        $limit  = $this->limitCount  ? $db->limit($this->limitCount, $this->limitOffset) : '';

        $allBindings = array_merge($binds, $this->havingBindings);

        $result = $db->query(
            "SELECT {$select} FROM \"{$this->tableName}\"{$joins} {$where} {$group} {$having} {$order} {$limit}",
            $allBindings
        );

        $class = $this->modelClass;
        $rows  = [];
        while ($row = $result->fetchArray()) {
            // Strip subquery aliases before constructing the model (not schema columns)
            $extras = [];
            foreach ($extraAliases as [$alias, $type]) {
                $raw = $row[$alias] ?? null;
                $extras[$alias] = match ($type) {
                    'int'   => (int)$raw,
                    'float' => $raw !== null ? (float)$raw : null,
                };
                unset($row[$alias]);
            }
            $model = new $class($this->tableName, $row);
            foreach ($extras as $alias => $val) {
                $model->setRelation($alias, $val);
            }
            $rows[] = $model;
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
     * Return true if any row matches the current conditions.
     *
     *   if (Model::query('users')->where('email', $email)->exists()) { ... }
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Return a flat array of values from a single column.
     *
     *   $emails = Model::query('users')->where('active', 1)->pluck('email');
     *   // ['alice@example.com', 'bob@example.com']
     *
     * @return list<mixed>
     */
    public function pluck(string $column): array
    {
        $rows = $this->select("\"{$column}\"")-> get();
        return array_map(fn(Model $m) => $m->$column, $rows);
    }

    /**
     * Return a single column value from the first matching row, or null.
     *
     *   $name = Model::query('users')->where('id', 5)->value('name');
     */
    public function value(string $column): mixed
    {
        $row = $this->select("\"{$column}\"")->first();
        return $row?->$column;
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

    // -------------------------------------------------------------------------
    // Aggregate queries
    // -------------------------------------------------------------------------

    /**
     * Return the SUM of a column over the matching rows.
     *
     *   Model::query('orders')->where('user_id', 5)->sum('total');
     */
    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    /**
     * Return the average value of a column over the matching rows.
     *
     *   Model::query('ratings')->where('product_id', 1)->avg('score');
     */
    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    /**
     * Return the maximum value of a column over the matching rows.
     *
     *   Model::query('products')->max('price');
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Return the minimum value of a column over the matching rows.
     *
     *   Model::query('products')->min('price');
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    private function aggregate(string $fn, string $column): mixed
    {
        $this->applyGlobalScopes();
        $db      = LazyMePHP::DB_CONNECTION();
        $conds   = $this->conditions;
        $binds   = $this->bindings;
        $hasCond = $this->hasCondition;

        if (method_exists($this->modelClass, 'softDeleteColumn') && !$this->includeTrashed) {
            $col     = ($this->modelClass)::softDeleteColumn();
            $conds[] = ($hasCond ? ' AND ' : '') . "\"{$col}\" IS NULL";
        }

        $joins  = $this->joins ? ' ' . implode(' ', $this->joins) : '';
        $where  = $conds ? 'WHERE ' . implode('', $conds) : '';
        $result = $db->query(
            "SELECT {$fn}(\"{$column}\") AS agg FROM \"{$this->tableName}\"{$joins} {$where}",
            $binds
        );
        $row = $result->fetchArray();
        return $row['agg'] ?? null;
    }

    // -------------------------------------------------------------------------
    // firstOrCreate / updateOrCreate
    // -------------------------------------------------------------------------

    /**
     * Find the first row matching $attributes, or create it.
     * $values are merged into the record only on creation.
     *
     *   Model::query('tags')->firstOrCreate(['slug' => 'php'], ['name' => 'PHP']);
     *
     * @param array<string,mixed> $attributes Columns used to look up the record
     * @param array<string,mixed> $values     Extra columns set only when creating
     */
    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        $q = new static($this->tableName, $this->modelClass);
        foreach ($attributes as $column => $value) {
            $q->where($column, $value);
        }
        $existing = $q->first();
        if ($existing !== null) {
            return $existing;
        }

        $class  = $this->modelClass;
        $record = new $class($this->tableName);
        foreach (array_merge($attributes, $values) as $col => $val) {
            $record->$col = $val;
        }
        $record->Save();
        return $record;
    }

    /**
     * Find the first row matching $attributes and update it with $values,
     * or create it with both $attributes + $values merged.
     *
     *   Model::query('users')->updateOrCreate(
     *       ['email' => 'alice@example.com'],
     *       ['name' => 'Alice', 'active' => 1]
     *   );
     *
     * @param array<string,mixed> $attributes Columns used to look up the record
     * @param array<string,mixed> $values     Columns to update (or set on creation)
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $q = new static($this->tableName, $this->modelClass);
        foreach ($attributes as $column => $value) {
            $q->where($column, $value);
        }
        $existing = $q->first();
        if ($existing !== null) {
            foreach ($values as $col => $val) {
                $existing->$col = $val;
            }
            $existing->Save();
            return $existing;
        }

        $class  = $this->modelClass;
        $record = new $class($this->tableName);
        foreach (array_merge($attributes, $values) as $col => $val) {
            $record->$col = $val;
        }
        $record->Save();
        return $record;
    }

    // -------------------------------------------------------------------------
    // Chunked processing
    // -------------------------------------------------------------------------

    /**
     * Process matching rows in chunks without loading all into memory.
     * The callback receives an array of Model instances.
     * Returning false from the callback stops processing early.
     *
     *   Model::query('emails')->chunk(200, function(array $batch) {
     *       foreach ($batch as $email) {
     *           Mail::dispatch(new NewsletterEmail($email));
     *       }
     *   });
     */
    public function chunk(int $size, callable $callback): void
    {
        $offset = 0;
        while (true) {
            $q = clone $this;
            $batch = $q->limit($size, $offset)->get();
            if (empty($batch)) break;
            if ($callback($batch) === false) break;
            if (count($batch) < $size) break;
            $offset += $size;
        }
    }

    // -------------------------------------------------------------------------
    // Bulk operations
    // -------------------------------------------------------------------------

    /**
     * Bulk-update all rows matching the current WHERE conditions.
     * Returns the number of affected rows.
     *
     *   Model::query('posts')->where('user_id', 5)->update(['status' => 'draft']);
     */
    public function update(array $data): void
    {
        if (empty($data)) return;
        $this->applyGlobalScopes();

        $db    = LazyMePHP::DB_CONNECTION();
        $set   = implode(', ', array_map(fn($k) => "\"$k\" = ?", array_keys($data)));
        $where = $this->conditions ? 'WHERE ' . implode('', $this->conditions) : '';
        $params = array_merge(array_values($data), $this->bindings);

        $db->query("UPDATE \"{$this->tableName}\" SET {$set} {$where}", $params);
        self::invalidateTable($this->tableName);
    }

    /**
     * Atomically increment a column by $amount for all rows matching the current WHERE.
     * Optional $extra sets additional columns in the same UPDATE statement.
     *
     *   Model::query('posts')->where('id', $id)->increment('views');
     *   Model::query('products')->where('id', $id)->increment('stock', 5, ['updated_at' => date('Y-m-d H:i:s')]);
     */
    public function increment(string $column, int|float $amount = 1, array $extra = []): void
    {
        $this->applyGlobalScopes();
        $db     = LazyMePHP::DB_CONNECTION();
        $sets   = ["\"$column\" = \"$column\" + ?"];
        $params = [$amount];
        foreach ($extra as $k => $v) {
            $sets[]   = "\"$k\" = ?";
            $params[] = $v;
        }
        $where  = $this->conditions ? 'WHERE ' . implode('', $this->conditions) : '';
        $params = array_merge($params, $this->bindings);
        $db->query("UPDATE \"{$this->tableName}\" SET " . implode(', ', $sets) . " $where", $params);
        self::invalidateTable($this->tableName);
    }

    /**
     * Atomically decrement a column by $amount for all rows matching the current WHERE.
     *
     *   Model::query('products')->where('id', $id)->decrement('stock');
     */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): void
    {
        $this->increment($column, -$amount, $extra);
    }

    /**
     * Hard-delete all rows matching the current WHERE conditions.
     * (Use Model::Delete() for single soft-delete-aware deletion.)
     *
     *   Model::query('sessions')->where('expires_at', date('Y-m-d'), '<')->bulkDelete();
     */
    public function bulkDelete(): void
    {
        $this->applyGlobalScopes();
        $db    = LazyMePHP::DB_CONNECTION();
        $where = $this->conditions ? 'WHERE ' . implode('', $this->conditions) : '';
        $db->query("DELETE FROM \"{$this->tableName}\" {$where}", $this->bindings);
        self::invalidateTable($this->tableName);
    }

    // -------------------------------------------------------------------------
    // Internal caching helpers
    // -------------------------------------------------------------------------

    private function resolvedCacheKey(): string
    {
        if ($this->cacheKey !== null) return $this->cacheKey;
        $parts = [
            $this->tableName,
            self::tableVersion($this->tableName), // invalidated on every write
            implode('', $this->conditions),
            implode(',', $this->bindings),
            implode(',', $this->selectColumns),
            implode(' ', $this->joins),
            $this->groupClause,
            implode('', $this->havingClauses),
            $this->orderClause,
            $this->limitCount . ':' . $this->limitOffset,
        ];
        return 'mq_' . md5(implode('|', $parts));
    }

    private function fromCache(): ?array
    {
        if ($this->cacheTtl <= 0) return null;
        return \Core\Cache\Cache::get($this->resolvedCacheKey());
    }

    private function toCache(array $rows): void
    {
        if ($this->cacheTtl <= 0) return;
        $data = array_map(fn($m) => $m->toArray(), $rows);
        \Core\Cache\Cache::set($this->resolvedCacheKey(), $data, $this->cacheTtl);
    }

    /**
     * Read the monotonic version counter for a table.
     * The key lives in cache; if absent (first use or after flush) it returns 0.
     */
    private static function tableVersion(string $table): int
    {
        return (int) \Core\Cache\Cache::get("tv:{$table}");
    }

    /**
     * Bump the version counter for a table, busting all cached queries for it.
     * Called automatically by update() and bulkDelete(); also accessible statically
     * so Model::Save() and Model::Delete() can invalidate from outside this class.
     */
    public static function invalidateTable(string $table): void
    {
        $key     = "tv:{$table}";
        $current = (int) \Core\Cache\Cache::get($key);
        \Core\Cache\Cache::set($key, $current + 1, 86400);
    }

    /** Flush the entire query cache (delegates to the configured cache driver). */
    public static function clearMemCache(): void
    {
        \Core\Cache\Cache::flush();
    }

    /** @internal Reset table version counters — for use between tests. */
    public static function resetTableVersions(): void
    {
        \Core\Cache\Cache::flush();
    }
}
