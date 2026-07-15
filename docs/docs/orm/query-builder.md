---
id: query-builder
title: Query Builder
sidebar_position: 2
---

# Query Builder

`Model::query()` returns a `ModelQuery` instance — a fluent, chainable query builder. All queries use prepared statement placeholders; no SQL injection is possible through the builder.

## Basic filtering

```php
use Core\Model;

$users = Model::query('users')
    ->where('active', 1)
    ->where('age', 18, '>=')
    ->orderBy('name')
    ->limit(20)
    ->get();  // returns Model[]

$count = Model::query('users')->where('active', 1)->count();

$user = Model::query('users')->where('email', $email)->first(); // Model|null
```

## All `where` variants

```php
->where('active', 1)                          // column = value
->where('age', 18, '>=')                      // with operator
->where('name', 'alice', '=', 'OR')           // OR connector
->whereLike('name', '%alice%')                // LIKE
->whereNull('deleted_at')                     // IS NULL
->whereNotNull('verified_at')                 // IS NOT NULL
->whereIn('status', ['active', 'trial'])      // IN (...)
->whereRaw('"score" > ? OR "admin" = 1', [50])   // raw SQL, AND
->whereRaw('"role" = ?', ['editor'], 'OR')        // raw SQL, OR
```

## Ordering, limiting, pagination

```php
->orderBy('created_at', 'DESC')
->limit(10)
->limit(10, 20)   // 10 rows, skip 20

$page = Model::query('users')
    ->where('active', 1)
    ->paginate(perPage: 15, page: 2);

// $page = [
//   'data'         => Model[],
//   'total'        => 120,
//   'per_page'     => 15,
//   'current_page' => 2,
//   'last_page'    => 8,
//   'from'         => 16,
//   'to'           => 30,
// ]
```

## Bulk operations

```php
// Update every matching row
Model::query('users')
    ->where('trial', 1)
    ->update(['active' => 0, 'trial' => 0]);

// Delete every matching row
Model::query('users')
    ->where('deleted_at', null, '!=')
    ->bulkDelete();
```

## Eager loading (relationships)

```php
$posts = Post::query()->with('author', 'comments')->get();
// One batch query per relation — no N+1.
```

## Subclasses

When calling from a model subclass, the table name is inferred:

```php
class User extends Model {
    protected static string $table = 'users';
}

User::query()->where('active', 1)->get();
// No table name argument needed.
```
