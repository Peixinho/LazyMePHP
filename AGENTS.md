# LazyMePHP — AI Agent Reference

Compact reference for AI assistants working in this repo. Full human-readable docs: `docs/docs/*.md` (Docusaurus source) and `README.md` (self-contained superset). When in doubt, grep the source — this file is a map, not a spec.

## What this is

PHP 8.1+ framework built on **"the database schema is the application."** Point it at a DB and every table gets a CRUD web UI, GraphQL API, and OpenAPI spec — **zero code generation**. This is a rewrite of an earlier (2021-era) codegen-based version of the same project name; if you see references to `BuildControllers.php`/`BuildViews.php`/`make:api`/`make:route`, those are gone (removed `27f5954` and earlier) — do not recreate that pattern.

## Directory / namespace map

- `App/Core/` — framework internals, namespace `Core\*`. Autoloaded PSR-4 from `composer.json` (`Core\`, `Models\`, `Controllers\`, `Messages\`, `Routes\`, `Tools\`, `Views\` all rooted under `App/`).
- `App/Core/Model.php` + `ModelQuery.php` — the runtime ORM (`Core\Model`). Schema introspected live via `PRAGMA` (SQLite) / `INFORMATION_SCHEMA` (MySQL/MSSQL), no generated model classes required.
- `App/Core/CrudController.php` — base class every generic/custom table controller extends (or falls back to `GenericCrudController`).
- `App/Core/AutoRouter.php` — registers the 6 standard CRUD routes per table.
- `App/Core/Http/Kernel.php` — web front controller (`public/index.php`).
- `App/Controllers/`, `App/Views/`, `App/Routes/`, `App/Api/`, `App/Models/`, `App/Observers/`, `App/Console/Commands/` — **all hand-written/scaffolded, not generated**. Must be tracked in git (see Quirks below — `.gitignore` used to blanket-ignore these).
- `LazyMePHP` (root script) — CLI entry point, `php LazyMePHP <command>`.
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

`make:*` CLI commands scaffold these files but **refuse to overwrite an existing one** — additive only, never regenerative. Full page: `docs/docs/extending.md`.

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

Two front controllers, both `require_once App/bootstrap.php` first (composer autoload, `.env`, baseline error handler, debug toolbar, observer auto-discovery):
- `public/index.php` → `Core\Http\Kernel::handle()` — web UI, Blade layout, `App/Routes/*.php`.
- `public/api/index.php` → JSON API — own CORS handling, `require_once`s every `App/Api/*.php` (still a live, if rarely used, extension point for hand-written REST endpoints), GraphQL is the real "auto REST API" (`POST /graphql`).

**Gotcha:** `Kernel::handle()` installs a *second* `set_error_handler()` on top of the one `App/bootstrap.php` already installed (`Core\Helpers\ErrorUtil`). PHP only keeps the latest registration, so on web/API requests `ErrorUtil`'s handler — including its **email-alert-on-error feature** — never actually runs; `Core\ErrorHandler` takes over instead (renders HTML/JSON, no email). Email alerts on error only fire in contexts that never load a Kernel (CLI, `schedule:run`, queue workers). Full page: `docs/docs/error-handling.md`.

## CLI (`php LazyMePHP <command>`)

`serve`, `batman`, `auth:hash`, `migrate[:rollback|:reset|:fresh|:status]`, `make:{migration,model,controller,view,router,all,seeder,factory,observer,resource,job,request,mail,test,command,middleware,kernel}`, `schema:cache|clear`, `db:seed`, `queue:work|size|failed|retry|flush`, `schedule:run`, `route:list`, `optimize`, `down|up`. Unknown commands fall through to user-defined `App/Console/Commands/*.php`. Full reference: `docs/docs/cli.md`.

## Config

Everything lives in `.env` (`Dotenv`). Read via `Core\Config::get('app.env')` (dot→`APP_*` env mapping, `Config::set()` for runtime/test overrides) rather than `$_ENV` directly. Full table: `docs/docs/configuration.md`. Key gotchas: `APP_ENCRYPTION` must be ≥32 chars (also the JWT signing secret); `APP_CORS_ORIGIN` empty = block all cross-origin (no wildcard support); Batman dashboard auth (`BATMAN_USERNAME`/`BATMAN_SECRET`) is fully independent of JWT/`AUTH_TABLE`.

## Testing

Pest 4 on PHPUnit. Run: `vendor/bin/pest` (composer.json has **no `scripts` section** — there is no `composer test`). ~900 tests / 1600+ assertions as of 2026-07. In-memory SQLite via `Core\LazyMePHP::setDBConnection()` or `DB_FILE_PATH=':memory:'`; `Core\Testing\RefreshDatabase`/`MakesHttpRequests` traits for feature tests.

## Other quirks worth knowing before you touch things

- `App/Api/` is an empty (placeholder-only) extension point for hand-written REST endpoints — `App/ApiFieldMask.php` is the tracked/tested piece actually in use. Stray `TestUsers.php`/`TestProducts.php`/`sqlite_sequence.php` files generated by the old per-table REST generator were removed (2026-07); if they reappear locally, delete them rather than treating them as the current pattern.
- Multiple validation entry points coexist: `Model::$rules` (model-level), `Core\Validations\*` (older, broader), `Core\Validator` (standalone), `Core\Http\FormRequest` (request-level). Pick per context — there isn't one unified validator to route everything through.
- `.gitignore` was fixed (commit `05d929d`) to stop blanket-ignoring `App/Controllers`, `App/Views`, `App/Routes`, `App/Models`, `App/Api` — these hold permanent hand-written source, not generated output. If a fresh `make:*` file isn't showing up in `git status`, check this hasn't regressed.
- Schema changes are picked up automatically at runtime (no build step) — but if `schema:cache` was run for production (OPcache-friendly `App/Cache/schema/*.php`), you must re-run `schema:cache`/`optimize` after a migration or the cached schema goes stale.
- GraphQL introspection and verbose error output are both gated on `APP_DEBUG_MODE`/`APP_ENV=development` — never leave debug mode on in production.
