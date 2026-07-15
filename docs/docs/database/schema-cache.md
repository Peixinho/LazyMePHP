---
id: schema-cache
title: Schema Cache
sidebar_position: 2
---

# Schema Cache

In production, pre-warm the schema cache so no database introspection happens at request time. Cache files are plain PHP arrays that OPcache compiles to bytecode.

## Pre-warming

```bash
php LazyMePHP schema:cache            # cache all tables
php LazyMePHP schema:cache users      # cache one table
php LazyMePHP schema:clear            # remove all cache files
```

Cache files are written to `App/Cache/schema/{table}.php`.

## How it works

1. On first request (or if the cache file is missing), LazyMePHP queries the database for the table's column definitions.
2. The schema is written to `App/Cache/schema/{table}.php` as a `return [...]` PHP array.
3. On subsequent requests, `include` loads the file — OPcache serves it as compiled bytecode, with zero DB round-trips.

## When to re-cache

Re-run `schema:cache` after:
- Adding or removing columns
- Running migrations
- Changing column types or nullability

The migration commands (`migrate`, `migrate:rollback`, `migrate:reset`) clear the schema cache automatically.

## In-memory caching

Regardless of the file cache, the schema for each table is held in a static property after the first load within a process. This means even without file caching, introspection only happens once per table per worker.

Clear it in tests with:

```php
Model::clearSchemaCache();
```
