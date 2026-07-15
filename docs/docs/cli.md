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
php LazyMePHP migrate:status               # show migration run history
```

## Code scaffolding

```bash
php LazyMePHP make:migration <name>         # new migration file
php LazyMePHP make:model <Name>             # Model subclass stub
php LazyMePHP make:controller               # how to subclass CrudController
php LazyMePHP make:seeder <Name>            # Seeder stub in App/Seeders/
php LazyMePHP make:factory <Name>           # Factory stub in App/Factories/
php LazyMePHP make:observer <Name>          # ModelEvents observer stub
php LazyMePHP make:resource <Name>          # ApiResource subclass stub
php LazyMePHP make:job <Name>               # Queue Job stub in App/Jobs/
php LazyMePHP make:request <Name>           # FormRequest stub in App/Requests/
php LazyMePHP make:view                     # explains how to override Blade views
php LazyMePHP make:route                    # explains how AutoRouter works
php LazyMePHP make:api                      # explains how to restrict GraphQL fields
```

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
```

## Build tool

```bash
php LazyMePHP build
# Interactive tool for creating logging tables and viewing table descriptors.
# Models, forms, and API controllers are driven by Core\Model — no generation needed.
```
