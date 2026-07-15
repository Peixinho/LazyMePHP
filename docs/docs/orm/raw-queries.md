---
id: raw-queries
title: Raw Queries & hydrate()
sidebar_position: 4
---

# Raw Queries & `Model::hydrate()`

`ModelQuery` covers most SQL patterns. For the cases it can't express — CTEs, `UNION`, window functions, subqueries in `FROM` — use the raw query interface and hydrate the results into models.

## Raw queries

```php
use Core\LazyMePHP;

$result = LazyMePHP::DB_CONNECTION()->query(
    'SELECT * FROM "users" WHERE "age" > ?',
    [25]
);

while ($row = $result->fetchArray()) {
    echo $row['name'];
}

// Or as objects:
while ($obj = $result->fetchObject()) {
    echo $obj->name;
}
```

`query()` always uses prepared statements — parameters are bound, never interpolated.

## `Model::hydrate()`

Turn a raw result into `Model` instances. Schema columns **and** computed aliases both come through as model properties.

```php
$result = LazyMePHP::DB_CONNECTION()->query(
    'WITH ranked AS (
        SELECT *, RANK() OVER (PARTITION BY dept_id ORDER BY salary DESC) AS rnk
        FROM "employees"
    )
    SELECT * FROM ranked WHERE rnk = 1',
    []
);

$rows = [];
while ($row = $result->fetchArray()) $rows[] = $row;

$models = Model::hydrate('employees', $rows);

echo $models[0]->name;   // schema column
echo $models[0]->rnk;    // computed alias
```

## UNION example

```php
$result = LazyMePHP::DB_CONNECTION()->query('
    SELECT id, name, "user" AS type FROM "users"
    UNION ALL
    SELECT id, name, "admin" AS type FROM "admins"
    ORDER BY name
', []);

$rows = [];
while ($row = $result->fetchArray()) $rows[] = $row;

$all = Model::hydrate('users', $rows);
echo $all[0]->type;  // 'user' or 'admin'
```

## Subquery in FROM

```php
$result = LazyMePHP::DB_CONNECTION()->query('
    SELECT sub.dept_id, sub.avg_age
    FROM (
        SELECT dept_id, AVG(age) AS avg_age
        FROM "users"
        GROUP BY dept_id
    ) AS sub
    WHERE sub.avg_age > 30
', []);

$rows = [];
while ($row = $result->fetchArray()) $rows[] = $row;

$models = Model::hydrate('users', $rows);
foreach ($models as $m) {
    echo "{$m->dept_id}: {$m->avg_age}\n";
}
```

## When to use raw vs ModelQuery

| Situation | Recommended |
|---|---|
| Simple WHERE / ORDER / LIMIT | `Model::query()` |
| Joins and aggregates | `Model::query()` with `join()` / `groupBy()` / `having()` |
| CTEs (`WITH ...`) | Raw query + `hydrate()` |
| UNION / INTERSECT | Raw query + `hydrate()` |
| Window functions | Raw query + `hydrate()` |
| Subquery in FROM | Raw query + `hydrate()` |
