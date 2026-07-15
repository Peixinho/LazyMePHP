---
id: migrations
title: Migrations
sidebar_position: 1
---

# Migrations

Migrations are plain PHP files in `database/migrations/`. Each file returns an array with `up` and `down` callables. Migration state is tracked in `__migrations`.

## Creating a migration

```bash
php LazyMePHP make:migration create_posts
# creates database/migrations/2026_07_15_0001_create_posts.php
```

```php
// database/migrations/2026_07_15_0001_create_posts.php

return [
    'up' => function ($db): void {
        $db->query("CREATE TABLE posts (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            title   TEXT    NOT NULL,
            body    TEXT,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL
        )");
    },
    'down' => function ($db): void {
        $db->query("DROP TABLE IF EXISTS posts");
    },
];
```

The `$db` argument is the active `ISQL` connection — use `$db->query()` for any DDL.

## Running migrations

```bash
php LazyMePHP migrate                   # run all pending
php LazyMePHP migrate:rollback          # roll back the last batch
php LazyMePHP migrate:rollback --step=3 # roll back the last 3 batches
php LazyMePHP migrate:reset             # roll back everything
php LazyMePHP migrate:status            # show what has and hasn't run
```

The schema cache is cleared automatically after every run or rollback.

## Batches

Migrations run together in a batch. Rolling back undoes the entire last batch, not just the last file.

## Adding columns (non-destructive)

```php
return [
    'up' => function ($db): void {
        $db->query("ALTER TABLE users ADD COLUMN avatar TEXT NULL");
    },
    'down' => function ($db): void {
        // SQLite doesn't support DROP COLUMN on older versions —
        // recreate the table without the column if needed
        $db->query("ALTER TABLE users DROP COLUMN avatar");
    },
];
```

## Queue jobs table

The `database` queue driver requires a `__queue_jobs` table. It is created automatically when you run:

```bash
php LazyMePHP migrate
```
