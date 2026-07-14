
![LazyMePHP](https://raw.githubusercontent.com/Peixinho/LazyMePHP/main/public/img/logo.png)

LazyMePHP is a PHP 8+ rapid-development framework built around a single idea: **the database schema is the application**. Point it at a database and you get a full CRUD web UI, a GraphQL API, JWT-authenticated REST endpoints, and a developer dashboard — with zero code generation.

- MySQL, SQLite, and MSSQL support
- Runtime ORM — no generated model files
- Generic CRUD web UI driven by the live schema
- GraphQL API auto-built from the schema (`POST /graphql`)
- JWT authentication for SPA / API consumers
- Database migration system
- Audit log for all data mutations
- Batman developer dashboard with secure login
- Schema file cache for OPcache-friendly production deployments

> Only `public/` should be web-accessible.

---

## Quick start

```bash
git clone https://github.com/Peixinho/LazyMePHP myProject
cd myProject && rm -rf .git
composer install
cp .env.example .env   # edit DB_* and APP_* values
php LazyMePHP migrate  # create framework tables
php LazyMePHP serve
```

Navigate to `http://localhost:8080`. Every table in the database is immediately accessible at `/{table}` with list, create, edit, and delete pages, and via the GraphQL endpoint at `POST /graphql`.

---

## Configuration

All settings live in `.env`:

| Variable | Description |
|---|---|
| `DB_TYPE` | `mysql`, `mssql`, or `sqlite` |
| `DB_HOST` | Database host (MySQL / MSSQL) |
| `DB_NAME` | Database name (MySQL / MSSQL) |
| `DB_USER` | Database username (MySQL / MSSQL) |
| `DB_PASSWORD` | Database password (MySQL / MSSQL) |
| `DB_FILE_PATH` | Path to SQLite file |
| `APP_NAME` | Application name |
| `APP_TITLE` | HTML page title |
| `APP_TIMEZONE` | PHP timezone string (e.g. `Europe/Lisbon`) |
| `APP_NRESULTS` | Default page size for paginated lists |
| `APP_ENCRYPTION` | Secret key (≥ 32 chars) — used for JWT signing |
| `APP_ENV` | `development` enables GraphQL introspection and debug traces |
| `APP_CORS_ORIGIN` | Exact origin allowed for cross-origin requests (empty = block all) |
| `APP_ACTIVITY_LOG` | `true` to enable change audit logging |
| `APP_ACTIVITY_AUTH` | Fallback identifier written to the audit log when no JWT user is present |
| `AUTH_TABLE` | Table used for JWT login (enables `POST /auth/login`) |
| `AUTH_USERNAME_COLUMN` | Column checked as the login credential |
| `AUTH_PASSWORD_COLUMN` | Column holding the bcrypt-hashed password |
| `AUTH_TOKEN_TTL` | JWT lifetime in seconds (default `3600`) |
| `BATMAN_USERNAME` | Batman dashboard login username (default `admin`) |
| `BATMAN_SECRET` | Batman dashboard password as a bcrypt hash |

---

## How it works

On every request `LazyMePHP::boot($blade)` (called from `App/Routes/Routes.php`):

1. Reads the list of tables from the schema cache, or queries the DB directly.
2. Registers 6 CRUD web routes per table via `Core\AutoRouter`.
3. Registers `POST /graphql` via `Core\GraphQL\Endpoint`.
4. Registers `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` when `AUTH_TABLE` is set.

No files are generated. Schema introspection happens once per table per process (cached in memory), and optionally pre-warmed to disk for production.

---

## ORM — `Core\Model`

`Model` introspects the DB schema at runtime and provides full CRUD.

```php
use Core\Model;

// Create
$user = new Model('users');
$user->name  = 'Alice';
$user->email = 'alice@example.com';
$user->Save();

// Load by primary key
$user = new Model('users', 1);
echo $user->name; // Alice

// Update
$user->name = 'Alice Smith';
$user->Save();

// Delete
$user->Delete();
```

### Query builder

```php
$active = Model::query('users')
    ->where('active', 1)
    ->where('age', 18, '>=')
    ->orderBy('name')
    ->limit(20)
    ->get();  // returns Model[]

$count = Model::query('users')->where('active', 1)->count();

$row = Model::query('users')->where('email', $email)->first();
```

### Subclassing (optional)

```php
namespace Models;
use Core\Model;

class User extends Model {
    protected static string $table = 'users';
}

$user  = new User(1);
$users = User::query()->where('active', 1)->get();
```

---

## CRUD web UI

Every table gets these routes automatically:

| Method | Path | Action |
|---|---|---|
| GET | `/{table}` | List with pagination and filters |
| GET | `/{table}/new` | New record form |
| GET | `/{table}/{id}/edit` | Edit form |
| POST | `/{table}` | Create |
| POST | `/{table}/{id}` | Update |
| POST | `/{table}/{id}/delete` | Delete |

Views are rendered by generic Blade templates in `App/Views/_Crud/`. To override a table's view, create `App/Views/{TableName}/index.blade.php` or `edit.blade.php` — the controller resolves to the table-specific file first, then falls back to the generic template.

---

## Customising behaviour — `Core\CrudController`

Create `App/Controllers/{TableName}.php` to override behaviour for a specific table:

```php
namespace Controllers;
use Core\CrudController;
use Core\Model;

class Users extends CrudController {
    protected static string $table = 'users';

    protected function foreignKeys(): array {
        return ['role_id' => 'roles'];
    }

    protected function extraValidationRules(): array {
        return [
            'username' => ['validations' => [\Core\Validations\ValidationsMethod::STRING], 'required' => true],
        ];
    }

    protected function beforeSave(Model $obj, array &$data, bool $isUpdate): void {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    protected function afterSave(Model $obj, bool $isUpdate): void {}
    protected function beforeDelete(Model $obj): void {}

    public function exposedFields(): array {
        return ['id', 'name', 'email', 'role_id', 'created_at'];
    }

    // public static bool $hidden = true; // exclude from auto-wiring
}
```

---

## GraphQL API

```
POST /graphql
Content-Type: application/json
```

### Queries

```graphql
{ usersList(page: 1, limit: 20) { id name email } }
{ users(id: 1) { id name email } }
```

### Mutations

```graphql
mutation { createUsers(input: { name: "Alice", email: "alice@example.com" }) { id } }
mutation { updateUsers(id: 1, input: { name: "Alice Smith" }) { id name } }
mutation { deleteUsers(id: 1) }
```

### Security defaults

| Measure | Value |
|---|---|
| Query depth limit | 7 |
| Query complexity limit | 200 |
| Introspection | Disabled outside `APP_ENV=development` |
| Stack traces | Stripped outside `APP_ENV=development` |

---

## JWT Authentication

Add these to `.env` to enable auth endpoints:

```env
AUTH_TABLE=users
AUTH_USERNAME_COLUMN=email
AUTH_PASSWORD_COLUMN=password
AUTH_TOKEN_TTL=3600
```

Hash a password for storage:

```bash
php LazyMePHP auth:hash mypassword
```

### Endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/auth/login` | Returns a JWT `{token, token_type, expires_in}` |
| `POST` | `/auth/logout` | Stateless confirmation |
| `GET` | `/auth/me` | Returns the authenticated user (requires token) |

### Protecting routes

```php
use Core\Auth\JwtMiddleware;

$router->post('/orders', [OrderController::class, 'store'])
       ->addMiddleware(JwtMiddleware::class);
```

### Using `Auth` in code

```php
use Core\Auth\Auth;

// Verify credentials and get a token
$token = Auth::attempt('alice@example.com', 'secret');

// In a protected context (Bearer token already validated)
$user = Auth::user();   // array without password column
$id   = Auth::id();
$ok   = Auth::check();  // true when a valid Bearer token is present
```

Passwords must be stored as bcrypt hashes (`password_hash($plain, PASSWORD_BCRYPT)`). The `auth:hash` CLI command does this for you.

---

## Database migrations

Migrations live in `database/migrations/` as plain PHP files:

```php
// database/migrations/2026_07_14_0001_create_posts.php
return [
    'up'   => function ($db): void {
        $db->query("CREATE TABLE posts (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            title   TEXT NOT NULL,
            body    TEXT,
            user_id INTEGER NOT NULL
        )");
    },
    'down' => function ($db): void {
        $db->query("DROP TABLE IF EXISTS posts");
    },
];
```

Scaffold a new file:

```bash
php LazyMePHP make:migration create_posts
```

Run and manage migrations:

```bash
php LazyMePHP migrate                  # run all pending migrations
php LazyMePHP migrate:rollback         # roll back the last batch
php LazyMePHP migrate:rollback --step=3
php LazyMePHP migrate:reset            # roll back all migrations
php LazyMePHP migrate:status           # show which migrations have run
```

Migration state is tracked in `__migrations`. The schema cache is cleared automatically after every run or rollback.

---

## Audit logging

Set `APP_ACTIVITY_LOG=true`. The following tables are created automatically:

| Table | Contents |
|---|---|
| `__LOG_ACTIVITY` | One row per mutating request (INSERT / UPDATE / DELETE) |
| `__LOG_DATA` | Per-field before/after values for every change |
| `__LOG_ERRORS` | Application errors with severity and context |
| `__LOG_PERFORMANCE` | Slow-operation metrics when monitoring is enabled |

**Only mutating requests are logged** — plain reads produce no audit entry.

Sensitive column names (`password`, `token`, `secret`, `api_key`, `api_secret`, and the value of `AUTH_PASSWORD_COLUMN`) are automatically stripped from `__LOG_DATA`. The authenticated JWT user id is written to `__LOG_ACTIVITY.user` when available.

---

## Batman dashboard

Batman is an internal developer dashboard available at `/batman/`. It shows activity logs, error logs, performance metrics, and per-record change history with before/after diffs.

### Setup

Generate a bcrypt password hash:

```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT), PHP_EOL;"
```

Add to `.env`:

```env
BATMAN_USERNAME=admin
BATMAN_SECRET=$2y$12$...   # paste the hash above
```

Batman authenticates against `BATMAN_SECRET` using `password_verify()` — it does not use database credentials.

---

## Schema cache

Pre-warm in production so no DB introspection happens at request time:

```bash
php LazyMePHP schema:cache           # cache all tables
php LazyMePHP schema:cache users     # cache one table
php LazyMePHP schema:clear           # remove all cache files
```

Cache files are written to `App/Cache/schema/{table}.php` as plain PHP arrays — OPcache serves them as compiled bytecode.

---

## CLI reference

```
php LazyMePHP serve                    Start the PHP development server (port 8080)

php LazyMePHP migrate                  Run all pending migrations
php LazyMePHP migrate:rollback         Roll back the last migration batch
php LazyMePHP migrate:rollback --step=N
php LazyMePHP migrate:reset            Roll back all migrations
php LazyMePHP migrate:status           Show migration run history
php LazyMePHP make:migration <name>    Scaffold a new migration file

php LazyMePHP auth:hash <password>     Print a bcrypt hash of <password>

php LazyMePHP schema:cache             Pre-warm schema cache for all tables
php LazyMePHP schema:cache <table>     Pre-warm schema cache for one table
php LazyMePHP schema:clear             Remove all schema cache files

php LazyMePHP make:controller          Show how to subclass CrudController
php LazyMePHP make:view                Show how to override a table's Blade views
php LazyMePHP make:route               Show how AutoRouter works
php LazyMePHP make:api                 Show how to restrict GraphQL field exposure
php LazyMePHP build                    Run the full build script
```

---

## Security overview

| Area | Measure |
|---|---|
| Sessions | `httponly`, `samesite=Strict`, `secure` in production |
| CSRF | Token-per-session with rotation; all web form posts validated |
| CORS | Exact-origin allowlist via `APP_CORS_ORIGIN`; wildcard blocked |
| JWT | HS256, signed with `APP_ENCRYPTION` (≥ 32 chars enforced) |
| Batman login | bcrypt `password_verify()` against `BATMAN_SECRET` |
| Redirects | Path-only redirects; host stripping prevents open redirect |
| SQL injection | All queries use prepared statement placeholders |
| Column injection | Filter and sort columns validated against live schema |
| CSP | `default-src 'self'`; no `unsafe-inline` |
| GraphQL | Depth 7, complexity 200, introspection off in production |
| Audit log | Sensitive columns auto-stripped; passwords never logged |

---

## Requirements

- PHP 8.1+
- Composer
- MySQL, MSSQL, or SQLite

---

## License

MIT
