---
id: query-caching
title: Query Caching
sidebar_position: 10
---

# Query Caching

Cache query results directly on the builder with `remember()`. The cache key is automatically derived from the full query (table, conditions, ORDER BY, LIMIT, joins, HAVING) so different queries never collide.

## Basic usage

```php
// Cache for 60 seconds
$users = Model::query('users')
    ->where('active', 1)
    ->remember(60)
    ->get();

// With an explicit cache key
$users = Model::query('users')
    ->remember(300, 'active-users-list')
    ->get();
```

## Cache driver

The same driver configured by `CACHE_DRIVER` backs query caching. Switch to Redis and all query caches become persistent across workers:

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
```

## Clearing cached results

```php
// Flush everything in the cache (useful in tests or after bulk mutations)
Model::clearMemCache();
```

## How the cache key is built

When no explicit key is passed, the key is an MD5 hash of:

- table name
- WHERE conditions and bindings
- SELECT columns
- JOIN clauses
- GROUP BY clause
- HAVING clauses
- ORDER BY clause
- LIMIT / OFFSET

Two queries that differ only in ORDER BY produce different keys and never return stale data.

## Cache in context

Query caching pairs well with [global scopes](scopes) — a tenant-scoped model's cache keys include the tenant filter, so different tenants never see each other's cached results.
