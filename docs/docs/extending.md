---
id: extending
title: Extending & Customizing
sidebar_position: 4
---

# Extending & Customizing

LazyMePHP's core idea is **the database schema is the application** — every table gets a working CRUD UI, GraphQL API, and OpenAPI spec with zero files created. That only works long-term if customizing one table's behavior never risks your changes being overwritten later, and if the framework never has to be "rebuilt" after a schema change.

There is no build/regenerate step. Instead, every extension point in LazyMePHP follows the same rule:

> **The framework checks, at runtime, whether a conventionally-named file or class exists. If it does, your code runs instead of the default. If it doesn't, the generic runtime behavior applies.**

Scaffolding commands (`make:controller`, `make:view`, ...) are a convenience for creating that file with the right shape — they are not a code generator you re-run after a schema change, and they refuse to overwrite a file that already exists. Once a file is there, it's yours; nothing in the framework will ever regenerate or touch it again.

This page is the map of every place that pattern shows up. Each row links to the page with full details.

| You want to... | Convention | Resolved by |
|---|---|---|
| Customize one table's CRUD behavior | `App\Controllers\{Table}` extends `Core\CrudController` | `CrudController::forTable()` — [CRUD Web UI](./crud-ui) |
| Override a table's list/edit page | `App/Views/{table}/index.blade.php`, `edit.blade.php` | `CrudController::viewName()` — [CRUD Web UI](./crud-ui) |
| Replace a table's auto-wired routes | `App/Routes/{table}.php` | `Core\AutoRouter::register()` — [Routing](./routing#overriding-the-auto-wired-routes) |
| Restrict a table's GraphQL access to specific roles | `requiredRoles(): array` on the table's controller | `Core\GraphQL\SchemaBuilder::authorize()` — [Security](./security#graphql-authorization) |
| React to model create/update/delete | `App/Observers/{Name}.php` with `protected static string $table` | Auto-discovered in `App/bootstrap.php` — [Model Events](./orm/events) |
| Add a custom CLI command | `App/Console/Commands/{Name}.php` extends `Core\Console\Command` | `LazyMeCLI::runUserCommand()` — [CLI Reference](./cli) |
| Add cross-cutting request logic | `App/Http/Middleware/{Name}.php` implements `IMiddleware` | Attached per-route/group — [Middleware](./middleware) |
| Add a typed model class (optional) | `App/Models/{Name}.php` extends `Core\Model` | Only needed for `$fillable`/factories — `make:model` |

## Why file/class existence instead of a build step

The earlier version of this framework generated controller, view, and route files from the schema and expected you to hand-edit the output — which meant re-running the generator after a schema change would either skip files it had already written (leaving them stale) or silently clobber your edits. Runtime resolution removes that trade-off entirely:

- Add a column to a table → the CRUD UI, GraphQL schema, and OpenAPI spec pick it up on the next request. No command to run.
- Add a new table → it's immediately live at `/{table}` and in `POST /graphql`. Nothing to scaffold unless you want custom behavior.
- Customize one table → create the one file that matches its name. Every other table is untouched and keeps using the generic behavior.
- Never need custom behavior for a table → never create the file. There is no "empty stub" sitting in your codebase for tables you don't customize.

## The `--hidden` / `$hidden` escape hatch

Some tables shouldn't be exposed at all — internal audit tables, join tables, framework-owned tables like `__migrations`. Setting `public static bool $hidden = true;` on a table's controller (or scaffolding with `make:controller <table> --hidden`) removes it from the auto-wired routes, GraphQL schema, and OpenAPI spec, checked via `CrudController::isHidden()`. It's the same runtime-check pattern — the framework isn't special-casing anything, it's just reading a static property on the class you already control.

## Version control

Because none of `App/Controllers`, `App/Views`, `App/Routes`, or `App/Models` are generated output, they must be tracked in git like any other source — a fresh clone or deploy needs them to exist. If files you create under these directories aren't showing up in `git status`, check `.gitignore` hasn't reverted to blanket-ignoring them.
