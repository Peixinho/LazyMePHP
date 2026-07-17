# LazyMePHP — AI Agent Reference

Compact reference for AI assistants working in this repo. Full human-readable docs: `docs/docs/*.md` (Docusaurus source) and `README.md` (self-contained superset). When in doubt, grep the source — this file is a map, not a spec.

## What this is

PHP 8.1+ framework built on **"the database schema is the application."** Point it at a DB and every table gets a CRUD web UI, GraphQL API, and OpenAPI spec — **zero code generation**. This is a rewrite of an earlier (2021-era) codegen-based version of the same project name; if you see references to `BuildControllers.php`/`BuildViews.php`/`make:api`/`make:route`, those are gone (removed `27f5954` and earlier) — do not recreate that pattern.

## Directory / namespace map

- `App/Core/` — framework internals, namespace `Core\*`. Autoloaded PSR-4 from `composer.json` (`Core\`, `Models\`, `Controllers\`, `Messages\`, `Routes\`, `Tools\`, `Views\` all rooted under `App/`).
- `App/Core/Model.php` + `ModelQuery.php` — the runtime ORM (`Core\Model`). Schema introspected live via `PRAGMA` (SQLite) / `INFORMATION_SCHEMA` (MySQL/MSSQL), no generated model classes required.
- `App/Core/CrudController.php` — base class every generic/custom table controller extends (or falls back to `GenericCrudController`).
- `App/Core/AutoRouter.php` — registers the 6 standard CRUD routes per table.
- `App/Core/GraphQL/SchemaBuilder.php` + `Endpoint.php` — the entire data API. `POST /graphql` is the *only* auto-generated API surface; there is no separate REST scaffold (`App/Api/` and `public/api/index.php` were removed — if you see either referenced anywhere, it's stale).
- `App/Core/Http/Kernel.php` — web front controller (`public/index.php`).
- `App/Controllers/`, `App/Views/`, `App/Routes/`, `App/Models/`, `App/Observers/`, `App/Console/Commands/` — **all hand-written/scaffolded, not generated**. Must be tracked in git (see Quirks below — `.gitignore` used to blanket-ignore these).
- `LazyMePHP` (root script) — CLI entry point, `php LazyMePHP <command>`.
- `batman/` — standalone dev dashboard (activity/error logs, GraphQL explorer), its own server (`php LazyMePHP batman`), own login (`BATMAN_USERNAME`/`BATMAN_SECRET`, unrelated to `AUTH_TABLE`/JWT). **Not reachable through the main app's dev server** — `App/Tools/Webserver` only ever resolves requests against `public/`, nothing outside it, by design (this used to be a real vulnerability: any file anywhere in the project tree was servable/executable through it). Batman's GraphQL Explorer proxies API calls through its own PHP backend (`batman/proxy.php`, server-to-server cURL) rather than the browser calling the target app directly — CORS is a browser-only restriction, so this sidesteps needing `APP_CORS_ORIGIN` configured at all for Batman's own use.
- `docs/` — Docusaurus site. `docs/docs/*.md` is markdown source; `docs/build/` is the **committed, pre-built static HTML** the running app serves at `/docs` via `Core\DocsServer`. Rebuild it with `cd docs && DOCS_BASE_URL=/docs/ npm run build` after editing `docs/docs/*.md` — the `DOCS_BASE_URL` override matters, plain `npm run build` targets public GitHub Pages hosting (`baseUrl=/LazyMePHP/`) instead and will break the in-app viewer if committed. The app itself never needs npm/Node, only doc authoring does. Full explanation: `docs/README.md`.

## The one pattern that explains most of the framework

Every extension point is a **runtime file/class-existence check**, not a build step:

| Want to override | Convention | Checked by |
|---|---|---|
| Table's CRUD behavior | `App\Controllers\{Table}` extends `CrudController` | `CrudController::forTable()` |
| Table's list/edit page | `App/Views/{table}/index.blade.php`, `edit.blade.php` | `CrudController::viewName()` |
| Table's routes entirely | `App/Routes/{table}.php` | `AutoRouter::register()` |
| Model lifecycle hooks | `App/Observers/{Name}.php` w/ `static $table` | auto-discovered in `App/bootstrap.php` |
| Custom CLI command | `App/Console/Commands/{Name}.php` extends `Core\Console\Command` | `LazyMeCLI::runUserCommand()` |
| Hide a table entirely | `public static bool $hidden = true;` on its controller | `CrudController::isHidden()` |
| GraphQL columns exposed for a table | `exposedFields(): array` on its controller | `SchemaBuilder::build()` |
| Role restriction, table-wide, **for GraphQL and the web CRUD routes at once** | `requiredRoles()`, or `requiredRolesForRead()`/`requiredRolesForWrite()` if reading and writing need to differ | `Core\Auth\Gate::checkRoles()`, called by both `SchemaBuilder` and `AutoRouter`, via `Core\Auth\RBAC::is()` |
| Restriction to a specific record ("edit your own, not anyone else's"), **for both surfaces** | `authorizeRecord(string $operation, Model $record): bool` | `Core\Auth\Gate::checkRecord()` — single-record query/edit-page/update/delete only (not list, not create) |

`make:*` CLI commands scaffold these files but **refuse to overwrite an existing one** — additive only, never regenerative. Full page: `docs/docs/extending.md`.

**Neither GraphQL nor the auto-wired web CRUD routes have a route of their own to attach role-restricting middleware to** — one `POST /graphql` request can touch several tables at once, and `AutoRouter::register()` wires all 6 CRUD routes generically for every table from one method; `JwtMiddleware` only ever answers "is there a valid Bearer token," nothing role-specific. The two role rows above default to no restriction beyond that — every table works exactly as before unless a controller opts in — and, critically, are a **single declaration that governs both surfaces**: `Core\Auth\Gate` is the one place both `SchemaBuilder` and `AutoRouter` call, so there's no separate "web roles" config to drift out of sync with the GraphQL one. `requiredRoles*()` is a table-level gate checked before anything else runs; `authorizeRecord()` runs after, with the actual target record already loaded, so it's the only one of the two that can express "not just this table, this *row*." There's no way to replace a resolver or route outright short of editing `SchemaBuilder.php`/`AutoRouter.php` themselves (or, per-table, `App/Routes/{table}.php`) — these hooks exist so you shouldn't need to.

**Gotcha:** `Core\Auth\RBAC::is()`/`can()` resolve "current user" via `RBAC::currentUserId()`, which tries a pluggable `RBAC::$identityResolver` first, then falls back to `Core\Auth\Auth::id()` (JWT). If an app authenticates its web UI a different way (a session-based login, e.g. the app-specific `Tools\Auth` in this repo), it must wire `RBAC::$identityResolver` once (see `App/Routes/Routes.php`) or `requiredRoles()` will work correctly via GraphQL but silently reject every session-authenticated web user, since `RBAC::is()` would only ever see a JWT identity. Same fallback-chain shape as `ActivityLogger::$userResolver` below — set it once, both mechanisms cover both transports.

## Core\Model / ModelQuery cheat sheet

```php
new Model('users');                 // new record
new Model('users', $id);            // load by PK
Model::find('users', $id);
class User extends Model { protected static string $table = 'users'; }

Model::query('users')->where(...)->orderBy(...)->limit(...)->get();  // Model[]
->with('relation')->withCount('relation')                            // eager load / count
->paginate($perPage, $page); ->chunk($size, fn($rows) => ...);
->firstOrCreate($attrs, $values); ->updateOrCreate($attrs, $values);
->remember($ttl)                    // query cache, invalidated by per-table write-version counter
```
Relationships: `hasMany()/hasOne()/belongsTo()/belongsToMany()` protected helpers on a `Model` subclass.
Scopes: local (`scopeXxx()` methods), global (per-class), universal (`Model::addUniversalScope()`, cross-model — used by tenancy).
Validation: `protected static array $rules` + `validate()/passes()/errors()`.
Events: `Core\Events\ModelEvents::listen('table','creating', fn($m)=>...)` — return `false` to veto; also observer-class style. Fires `saving→creating|updating→created|updated→saved` / `deleting→deleted`.

**Gotcha:** `ModelQuery` quotes identifiers with double quotes (ANSI/SQLite/MySQL style) — not MSSQL bracket syntax. MSSQL support in `Model`/`ModelQuery` is schema-introspection-complete but query-builder SQL portability to MSSQL hasn't been specifically verified.

## HTTP layer

One front controller: `public/index.php` → `Core\Http\Kernel::handle()` → `App/bootstrap.php` first (composer autoload, `.env`, baseline error handler, debug toolbar, observer auto-discovery), then `App/Routes/*.php` (web UI, Blade layout) and `POST /graphql` (registered by `LazyMePHP::boot()`, called from `Routes.php`). There used to be a second front controller (`public/api/index.php` + `App/Api/*.php` hand-written REST endpoints) — removed once GraphQL covered the same need; if you see either referenced anywhere (including in this repo's own git history / old comments), it's stale.

Kernel route group middleware, in order: `CorsMiddleware`, `SecurityHeadersMiddleware`, `CsrfMiddleware`, then the app's own `App\Middleware\AuthMiddleware` if that class exists (guarded by `class_exists()` — a no-op drop-in otherwise).

**Gotcha:** `Kernel::handle()` installs a *second* `set_error_handler()` on top of the one `App/bootstrap.php` already installed (`Core\Helpers\ErrorUtil`). PHP only keeps the latest registration, so on web/API requests `ErrorUtil`'s handler — including its **email-alert-on-error feature** — never actually runs; `Core\ErrorHandler` takes over instead (renders HTML/JSON, no email). Email alerts on error only fire in contexts that never load a Kernel (CLI, `schedule:run`, queue workers). Full page: `docs/docs/error-handling.md`.

**Gotcha:** Pecee's router only runs a route's middleware once it's matched *both* path and HTTP method — a route registered `SimpleRouter::post(...)` never sees an `OPTIONS` preflight, so `CorsMiddleware` never runs for it either, no matter how `APP_CORS_ORIGIN` is set. `/graphql` and every `/auth/*` route explicitly register a matching `OPTIONS` route too (see `LazyMePHP::boot()`, `AuthEndpoint::register()`) — copy that pattern for any other Bearer-token route meant to be called cross-origin.

**Gotcha:** `App/Tools/Webserver` (the `php LazyMePHP serve` dev server router) resolves every request strictly against `public/` — this used to resolve against the whole project root instead, meaning `batman/`, `.env`, `vendor/`, even the `LazyMePHP` CLI script were directly reachable/executable through it. Don't reintroduce a `getcwd()`-relative path check there.

## CLI (`php LazyMePHP <command>`)

`serve`, `batman`, `auth:hash`, `migrate[:rollback|:reset|:fresh|:status]`, `make:{migration,model,controller,view,router,all,seeder,factory,observer,resource,job,request,mail,test,command,middleware,kernel}`, `schema:cache|clear`, `db:seed`, `queue:work|size|failed|retry|flush`, `schedule:run`, `route:list`, `optimize`, `down|up`. Unknown commands fall through to user-defined `App/Console/Commands/*.php`. Full reference: `docs/docs/cli.md`.

## Config

Everything lives in `.env` (`Dotenv`). Read via `Core\Config::get('app.env')` (dot→`APP_*` env mapping, `Config::set()` for runtime/test overrides) rather than `$_ENV` directly. Full table: `docs/docs/configuration.md`. Key gotchas: `APP_ENCRYPTION` must be ≥32 chars (also the JWT signing secret); `APP_CORS_ORIGIN` empty = `Core\Http\CorsMiddleware` sends no CORS headers at all, block all cross-origin (exact-origin allowlist, no wildcard support — see the OPTIONS-preflight gotcha above); Batman dashboard auth (`BATMAN_USERNAME`/`BATMAN_SECRET`) is fully independent of JWT/`AUTH_TABLE`. Note there's a second, unrelated `Core\Http\Middleware\CorsMiddleware` (different namespace) that's an example for the standalone `Pipeline` utility and is never invoked by the real request flow — don't confuse the two.

## Testing

Pest 4 on PHPUnit. Run: `vendor/bin/pest` (composer.json has **no `scripts` section** — there is no `composer test`). ~940 tests / 1700+ assertions as of 2026-07. In-memory SQLite via `Core\LazyMePHP::setDBConnection()` or `DB_FILE_PATH=':memory:'`; `Core\Testing\RefreshDatabase`/`MakesHttpRequests` traits for feature tests.

**Gotcha:** `CrudController::forTable($table, ...)` resolves a custom controller by class name — `Controllers\{StudlyCase($table)}` — not by anything test-scoped. A test that creates a fixture table literally named `users` (or `rooms`, `checklist_tasks`, etc.) will silently exercise *this app's real* `Controllers\Users` and inherit whatever role/ownership restrictions it declares. `GraphQLTest.php`/`GraphQLEndpointTest.php` learned this the hard way — their fixture table is `demo_users`, not `users`. Any custom controller fixture defined inline in a test file must live in `namespace Controllers { ... }` (see `GraphQLAuthorizationTest.php`) or `forTable()` won't find it and silently falls back to `GenericCrudController`.

**Gotcha:** `AutoRouter`'s write routes can't be dispatched end-to-end in-process — `Core\Auth\Gate` legitimately calls `exit()` on denial, and a *successful* write ends in `Pecee\Http\Response::redirect()`, which also calls `exit()`; either kills the test runner. `WebAuthorizationTest.php` tests the same `Gate::checkRoles()`/`checkRecord()` calls the routes make (not the routes themselves) for the denied/redirecting cases, and only dispatches the real registered GET-list closure directly (no redirect on success) for an end-to-end proof of the wiring. If you need a true full-stack check, do what was done during development: dispatch through Pecee's real router in a one-shot child PHP process per scenario (`php script.php <scenario>`), never inside the test process.

## Other quirks worth knowing before you touch things

- `App/Api/` (the old per-table REST generator's home) is gone — deleted along with `public/api/index.php` once GraphQL covered the same need. `App/ApiFieldMask.php` is unrelated and still current (a field-masking utility, tracked/tested) — don't confuse the two or assume `App/Api/*` is still a thing to scaffold into.
- Multiple validation entry points coexist: `Model::$rules` (model-level), `Core\Validations\*` (older, broader), `Core\Validator` (standalone), `Core\Http\FormRequest` (request-level). Pick per context — there isn't one unified validator to route everything through.
- `.gitignore` was fixed (commit `05d929d`) to stop blanket-ignoring `App/Controllers`, `App/Views`, `App/Routes`, `App/Models` — these hold permanent hand-written source, not generated output. If a fresh `make:*` file isn't showing up in `git status`, check this hasn't regressed.
- Schema changes are picked up automatically at runtime (no build step) — but if `schema:cache` was run for production (OPcache-friendly `App/Cache/schema/*.php`), you must re-run `schema:cache`/`optimize` after a migration or the cached schema goes stale.
- GraphQL introspection and verbose error output are both gated on `APP_DEBUG_MODE`/`APP_ENV=development` — never leave debug mode on in production. Introspection being *off* is enforced by `new DisableIntrospection(DisableIntrospection::ENABLED)` in `Endpoint.php` — that class's constructor takes a required `int` arg; calling it with none (an easy mistake, this exact bug shipped once) throws an `ArgumentCountError` on *every* non-dev request, not just introspection attempts, since the middleware runs unconditionally.
- `Model::loadByPrimaryKey()` (used by `new Model($table, $id)`, `Model::find()`, and GraphQL's single-record query) goes through `ModelQuery` so registered global/universal scopes apply — it used to run a raw `SELECT`, so a scope meant to hide a row (soft-deleted, another tenant's, inactive) could still be read directly by id even though the list query correctly hid it.
- `Core\Helpers\ActivityLogger` writes two different things to two different tables from one call: `__LOG_ACTIVITY` is an access log (one row per request — who, method, URI, status — written for *every* request regardless of whether anything changed), `__LOG_DATA` is the audit trail (one row per changed field, only when there was a change). Who counts as "current user" is pluggable (`ActivityLogger::$userResolver`) and tries the app's own resolver first, then falls back to `Core\Auth\Auth` (JWT) — a session-based web app and a Bearer-token API call are identified through completely different mechanisms, and getting the fallback order backwards means one or the other always logs as blank/"system".
- `Core\Session\SessionStore`'s lazy `session_start()` is guarded by `headers_sent()` — a request with no real session (GraphQL/API calls authenticated via Bearer token) can still reach it via `ActivityLogger`'s resolver fallback above; without the guard, any response large enough to flush PHP's output buffer early (a GraphQL error with a full debug trace routinely is) turns that into a second, garbled error page appended to the real response.
