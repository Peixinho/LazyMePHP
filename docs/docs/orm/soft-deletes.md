---
id: soft-deletes
title: Soft Deletes
sidebar_position: 6
---

# Soft Deletes

Add a `deleted_at DATETIME NULL` column to a table, then mix in the `SoftDeletes` trait. The model keeps the row in the database but marks it as deleted, and all queries automatically exclude soft-deleted rows.

## Setup

```php
use Core\Model;
use Core\SoftDeletes;

class Post extends Model {
    use SoftDeletes;
    protected static string $table = 'posts';
}
```

## Operations

```php
$post = new Post(1);

$post->Delete();       // sets deleted_at to now — row stays in DB
$post->restore();      // clears deleted_at
$post->isTrashed();    // true when deleted_at is not null
```

## Querying

```php
Post::query()->get();               // excludes soft-deleted rows (default)
Post::query()->withTrashed()->get();  // includes soft-deleted rows
Post::query()->onlyTrashed()->get();  // only soft-deleted rows
```

The `WHERE deleted_at IS NULL` filter is applied automatically. `withTrashed()` and `onlyTrashed()` override this behaviour per-query without affecting other queries.
