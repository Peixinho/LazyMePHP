---
id: cli
title: CLI Reference
sidebar_position: 15
---

# CLI Reference

All commands are run via `php LazyMePHP <command>`.

## Development server

```bash
php LazyMePHP serve
# Starts the PHP built-in server on port 8080
```

## Migrations

```bash
php LazyMePHP migrate                       # run all pending migrations
php LazyMePHP migrate:rollback              # roll back the last batch
php LazyMePHP migrate:rollback --step=3     # roll back the last 3 batches
php LazyMePHP migrate:reset                 # roll back all migrations
php LazyMePHP migrate:fresh                 # drop all tables and re-run every migration
php LazyMePHP migrate:status               # show migration run history
```

## Code scaffolding

```bash
php LazyMePHP make:migration <name>              # new migration file (name-inferred stubs)
php LazyMePHP make:model <Name>                  # Model subclass stub
php LazyMePHP make:model <Name> --table=users    # Model stub with schema-introspected $fillable
php LazyMePHP make:controller <table>            # App/Controllers/{Table}.php extending CrudController
php LazyMePHP make:controller <table> --hidden   # ...and exclude it from auto-routing + GraphQL
php LazyMePHP make:view <table>                  # App/Views/{table}/index.blade.php + edit.blade.php
php LazyMePHP make:router <table>                # App/Routes/{table}.php — fully replaces its 6 standard routes
php LazyMePHP make:all <table>                   # make:view + make:controller for a table
php LazyMePHP make:seeder <Name>                 # Seeder stub in App/Seeders/
php LazyMePHP make:factory <Name>                # Factory stub in App/Factories/
php LazyMePHP make:observer <Name>               # ModelEvents observer stub (auto-registered on boot)
php LazyMePHP make:resource <Name>               # ApiResource subclass stub
php LazyMePHP make:job <Name>                    # Queue Job stub in App/Jobs/
php LazyMePHP make:request <Name>                # FormRequest stub in App/Requests/
php LazyMePHP make:mail <Name>                   # Mailable stub in App/Mail/
php LazyMePHP make:test <Name>                   # Pest feature test stub in tests/Feature/
```

The GraphQL API needs no scaffolding of its own — it's generated at runtime (`Core\GraphQL\SchemaBuilder`) from whichever controller `make:controller`/`make:all` creates. Override `foreignKeys()`, `exposedFields()`, or set `public static bool $hidden = true` on that controller to customise or opt a table out entirely — see [CRUD Web UI](./crud-ui) for the full list of hooks.

Routes are generated at runtime too (`Core\AutoRouter`) and don't need scaffolding for the standard 6 CRUD actions — but if a table needs different routes entirely, `make:router` gives you a real, editable route file that fully replaces the standard set for that table. See [Routing](./routing#overriding-the-auto-wired-routes).

### Migration name conventions

`make:migration` infers the correct stub from the name:

| Name pattern | Generated stub |
|---|---|
| `create_users_table` | `CREATE TABLE users (...)` |
| `add_email_to_users` | `ALTER TABLE users ADD COLUMN email TEXT` |
| `drop_orders_table` | `DROP TABLE IF EXISTS orders` |
| `rename_posts_to_articles` | `ALTER TABLE posts RENAME TO articles` |
| anything else | Generic commented stub |

### Observer auto-registration

Observers scaffolded with `make:observer` include a `protected static string $table` property. On every request, bootstrap scans `App/Observers/` and auto-registers any observer whose `$table` matches a model's table name — no manual `observe()` call needed.

## Seeders

```bash
php LazyMePHP db:seed                       # run all seeders in App/Seeders/
php LazyMePHP db:seed --class=UserSeeder    # run a specific seeder
```

## Authentication

```bash
php LazyMePHP auth:hash <password>          # print a bcrypt hash of <password>
```

## Schema cache

```bash
php LazyMePHP schema:cache                  # pre-warm cache for all tables
php LazyMePHP schema:cache <table>          # pre-warm for one table
php LazyMePHP schema:clear                  # remove all schema cache files
```

## Queue

```bash
php LazyMePHP queue:work                            # start the worker (default queue)
php LazyMePHP queue:work --queue=<name>             # work a specific named queue
php LazyMePHP queue:work --sleep=3                  # seconds to sleep when empty
php LazyMePHP queue:work --tries=3                  # max attempts per job
php LazyMePHP queue:work --stop-when-empty          # exit after draining

php LazyMePHP queue:size                            # pending job count
php LazyMePHP queue:size --queue=<name>             # pending count for a named queue
php LazyMePHP queue:failed                          # list permanently failed jobs
php LazyMePHP queue:failed --queue=<name>           # failed jobs for a specific queue
php LazyMePHP queue:retry <id>                      # re-queue a failed job by its ID
php LazyMePHP queue:flush                           # delete all failed jobs
php LazyMePHP queue:flush --queue=<name>            # delete failed jobs for one queue
```

## Route inspection

```bash
php LazyMePHP route:list
# Prints a table of all registered routes: method, path, handler
```

## Production optimization

```bash
php LazyMePHP optimize
# Warms the schema cache for all tables (skips DB introspection on every request)
# and runs composer dump-autoload --optimize for faster class loading
```
