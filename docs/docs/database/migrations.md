---
id: migrations
title: Migrations
sidebar_position: 1
---

# Migrations

Migrations are plain PHP files in `database/migrations/`. Each file returns an array with `up` and `down` callables. Migration state is tracked in `__migrations`.

**Always use `Schema` / `Blueprint`** so the same migration runs on SQLite, MySQL, and MSSQL. Raw `$db->query("CREATE TABLE ...")` with SQLite-only syntax (`AUTOINCREMENT`, unquoted types, etc.) will fail when you deploy to another driver.

## Creating a migration

```bash
php LazyMePHP make:migration create_posts_table
# creates database/migrations/YYYY_MM_DD_NNNN_create_posts_table.php
```

```php
// database/migrations/2026_07_15_0001_create_posts_table.php

use Core\Migration\Blueprint;
use Core\Migration\Schema;

return [
    'up' => function (): void {
        Schema::create('posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->text('body')->nullable();
            $t->integer('user_id');
            $t->timestamps();
        });
    },
    'down' => function (): void {
        Schema::dropIfExists('posts');
    },
];
```

`Schema` emits the correct DDL for the active `DB_TYPE` at runtime. You can still pass `$db` into a closure and run raw SQL when you need driver-specific statements.

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

## Adding columns

```php
use Core\Migration\Blueprint;
use Core\Migration\Schema;

return [
    'up' => function (): void {
        Schema::table('users', function (Blueprint $t): void {
            $t->string('avatar')->nullable();
        });
    },
    'down' => function (): void {
        Schema::table('users', function (Blueprint $t): void {
            $t->dropColumn('avatar');
        });
    },
];
```

## Column helpers

| Method | SQLite | MySQL | MSSQL |
|--------|--------|-------|-------|
| `$t->id()` | `INTEGER PRIMARY KEY AUTOINCREMENT` | `INT AUTO_INCREMENT PRIMARY KEY` | `INT IDENTITY(1,1) PRIMARY KEY` |
| `$t->string('name')` | `TEXT` | `VARCHAR(255)` | `NVARCHAR(255)` |
| `$t->text('body')` | `TEXT` | `TEXT` | `NVARCHAR(MAX)` |
| `$t->integer('n')` | `INTEGER` | `INT` | `INT` |
| `$t->boolean('ok')` | `INTEGER` | `TINYINT(1)` | `BIT` |
| `$t->timestamps()` | nullable datetime pair | same | same |

Modifiers: `->nullable()`, `->unique()`, `->default($value)`.

## Queue jobs table

The `database` queue driver requires a `__queue_jobs` table. It is created automatically when you run:

```bash
php LazyMePHP migrate
```
