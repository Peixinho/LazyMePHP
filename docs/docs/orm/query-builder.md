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

## firstOrCreate / updateOrCreate

Find the first matching record or create it when it doesn't exist:

```php
// Find by email, or insert with additional fields
$user = Model::query('users')->firstOrCreate(
    ['email' => 'alice@example.com'],          // lookup attributes
    ['name'  => 'Alice', 'active' => 1]        // only set on creation
);
```

Update the first matching record or create it:

```php
$user = Model::query('users')->updateOrCreate(
    ['email' => 'alice@example.com'],          // lookup attributes
    ['name'  => 'Alice Wonderland', 'age' => 31]  // always applied
);
```

Both methods return the `Model` instance (new or existing) with primary key set.

## chunk()

Process large result sets without loading all rows into memory:

```php
Model::query('subscribers')
    ->where('active', 1)
    ->chunk(200, function (array $batch) {
        foreach ($batch as $subscriber) {
            Mail::dispatch(new NewsletterEmail($subscriber));
        }
        // Return false to stop processing early
    });
```

`chunk($size, $callback)` runs one query per page. The callback receives an array of `Model` instances. Returning `false` from the callback aborts the loop.

## Atomic increment / decrement

Update a numeric column atomically without fetching the row first:

```php
// Via ModelQuery (fluent)
Model::query('products')->where('id', 42)->increment('stock', 10);
Model::query('orders')->where('id', $id)->decrement('quantity');

// Via a Model instance
$product = new Model('products', 42);
$product->increment('views');       // +1
$product->decrement('stock', 5);    // -5
$product->increment('score', 1, ['updated_by' => $adminId]);  // extra columns
```

`increment()` and `decrement()` issue a single `UPDATE SET col = col ± N` — safe under concurrent writes.

## touch()

Update a model's timestamp without changing any other fields:

```php
$post = new Model('posts', 1);
$post->touch();                 // sets updated_at (or first DATETIME column) to now
```

`touch()` auto-detects `updated_at` if the column exists, otherwise uses the first `DATETIME`/`TIMESTAMP` column in the schema.
