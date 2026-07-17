
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Peixinho/LazyMePHP/main/public/img/logo-dark.png">
  <img src="https://raw.githubusercontent.com/Peixinho/LazyMePHP/main/public/img/logo.png" alt="LazyMePHP">
</picture>

LazyMePHP is a PHP 8+ rapid-development framework built around a single idea: **the database schema is the application**. Point it at a database and you get a full CRUD web UI, a GraphQL API, JWT-authenticated REST endpoints, and a developer dashboard — with zero code generation.

- MySQL, SQLite, and MSSQL support
- Runtime ORM — no generated model files
- Generic CRUD web UI driven by the live schema
- GraphQL API auto-built from the schema (`POST /graphql`)
- JWT authentication with refresh tokens for SPA / API consumers
- Role-based access control (RBAC)
- Database migration system
- Seeder and factory system for test data
- Audit log for all data mutations
- Batman developer dashboard with secure login
- Schema file cache for OPcache-friendly production deployments
- OpenAPI 3.0 spec auto-generated from live schema (`GET /openapi.json`)
- Health check endpoint (`GET /health`)
- Request ID tracing on every response (`X-Request-ID`)
- Pluggable cache layer: Redis, APCu, or in-process array
- General-purpose rate limiting middleware
- Background queue system: sync, database, or Redis drivers
- Standalone `FormRequest` validation (controller-level, no model required)
- File storage abstraction with local disk driver
- Multi-tenancy support (subdomain, header, path, or JWT resolution)

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
| `AUTH_REFRESH_TTL` | Refresh token lifetime in seconds (default `2592000` = 30 days) |
| `BATMAN_USERNAME` | Batman dashboard login username (default `admin`) |
| `BATMAN_SECRET` | Batman dashboard password as a bcrypt hash |
| `OPENAPI_ENABLED` | Set to `false` to disable the `/openapi.json` endpoint |
| `CACHE_DRIVER` | Cache backend: `array` (default), `apcu`, `redis` |
| `REDIS_HOST` | Redis host (default `127.0.0.1`) |
| `REDIS_PORT` | Redis port (default `6379`) |
| `REDIS_PASSWORD` | Redis password (empty = no auth) |
| `REDIS_DB` | Redis database index (default `0`) |
| `QUEUE_DRIVER` | Queue backend: `sync` (default), `database`, `redis` |
| `STORAGE_DRIVER` | Storage driver: `local` (default) |
| `STORAGE_PATH` | Root directory for local storage (default `storage/app`) |
| `STORAGE_URL` | Public URL prefix for stored files (default `/storage`) |
| `TENANT_TABLE` | Tenants table name (enables multi-tenancy when set) |
| `TENANT_COLUMN` | Column used to look up the tenant (default `slug`) |
| `TENANT_RESOLVE` | How to identify the tenant: `subdomain` \| `header` \| `path` \| `jwt` |
| `TENANT_REQUIRE` | Return 400/404 if no tenant found (default `true`) |

---

## How it works

On every request `LazyMePHP::boot($blade)` (called from `App/Routes/Routes.php`):

1. Reads the list of tables from the schema cache, or queries the DB directly.
2. Emits a `X-Request-ID` header for tracing.
3. Registers 6 CRUD web routes per table via `Core\AutoRouter`.
4. Registers `POST /graphql` via `Core\GraphQL\Endpoint`.
5. Registers `POST /auth/login`, `POST /auth/logout`, `POST /auth/refresh`, `GET /auth/me` when `AUTH_TABLE` is set.
6. Registers `GET /health` (health check) and `GET /openapi.json` (OpenAPI spec).

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

Other `where` variants:

```php
->whereLike('name', '%alice%')
->whereNull('deleted_at')
->whereNotNull('verified_at')
->whereIn('status', ['active', 'trial'])
->whereRaw('"score" > ? OR "admin" = 1', [50])   // raw SQL, AND by default
->whereRaw('"role" = ?', ['editor'], 'OR')         // change connector
```

### Joins

```php
$rows = Model::query('orders')
    ->join('customers', 'orders.customer_id', 'customers.id')
    ->leftJoin('coupons', 'orders.coupon_id', 'coupons.id')
    ->select('orders.*', 'customers.name AS customer_name', 'coupons.code AS coupon')
    ->where('orders.status', 'open')
    ->orderBy('orders.created_at', 'DESC')
    ->get();

// Columns from joined tables and aliases come through as model properties:
echo $rows[0]->customer_name;
echo $rows[0]->coupon;       // null when left-join partner is missing
```

Available join methods: `join()` (INNER), `leftJoin()`, `rightJoin()`.

### Column selection and aggregates

```php
// Restrict columns
Model::query('users')->select('id', 'name', 'email')->get();

// Aggregate expressions
$rows = Model::query('orders')
    ->select('customer_id', 'SUM(total) AS revenue', 'COUNT(*) AS cnt')
    ->groupBy('customer_id')
    ->having('revenue', 1000, '>=')
    ->orderBy('revenue', 'DESC')
    ->get();

echo $rows[0]->revenue;
echo $rows[0]->cnt;
```

`having(column, value, operator)` defaults to `=`. Runs after `GROUP BY`.

### Raw queries and `Model::hydrate()`

For SQL that `ModelQuery` cannot express — CTEs, `UNION`, window functions, subqueries in `FROM`:

```php
$result = LazyMePHP::DB_CONNECTION()->query('
    WITH ranked AS (
        SELECT *, RANK() OVER (PARTITION BY dept_id ORDER BY salary DESC) AS rnk
        FROM "employees"
    )
    SELECT * FROM ranked WHERE rnk = 1
', []);

$rows = [];
while ($row = $result->fetchArray()) $rows[] = $row;

$models = Model::hydrate('employees', $rows);
// schema columns + computed aliases (rnk) all accessible as properties
echo $models[0]->name;
echo $models[0]->rnk;
```

### Pagination

```php
$result = Model::query('users')
    ->where('active', 1)
    ->paginate(perPage: 15, page: 2);

// $result = [
//   'data'         => Model[],
//   'total'        => 120,
//   'per_page'     => 15,
//   'current_page' => 2,
//   'last_page'    => 8,
//   'from'         => 16,
//   'to'           => 30,
// ]
```

### Bulk operations

```php
// Bulk update every row matching the query
Model::query('users')
    ->where('trial', 1)
    ->update(['active' => 0, 'trial' => 0]);

// Bulk delete matching rows
Model::query('users')->where('deleted_at', null, '!=')->bulkDelete();

// Bulk insert (returns number of rows inserted)
Model::insertMany('tags', [
    ['name' => 'php'],
    ['name' => 'framework'],
]);
```

### Transactions

```php
use Core\Model;

Model::transaction(function () {
    $order = new Model('orders');
    $order->user_id = 1;
    $order->Save();

    $item = new Model('order_items');
    $item->order_id = $order->getPrimaryKey();
    $item->product_id = 42;
    $item->Save();
});
// Automatically rolled back on exception.
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

## Model relationships

```php
class Post extends Model {
    protected static string $table = 'posts';

    public function author(): ?Model {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): array {
        return $this->hasMany(Comment::class, 'post_id');
    }
}

// Eager loading (prevents N+1)
$posts = Post::query()->with('author', 'comments')->get();
```

Supported: `belongsTo`, `hasMany`, `hasOne`, `belongsToMany`.

---

## Soft deletes

Add `deleted_at DATETIME NULL` to a table, then use the trait:

```php
use Core\Model;
use Core\SoftDeletes;

class Post extends Model {
    use SoftDeletes;
    protected static string $table = 'posts';
}

$post->Delete();          // sets deleted_at, row stays in DB
$post->restore();         // clears deleted_at
$post->isTrashed();       // true if deleted_at is set

// Queries automatically exclude soft-deleted rows:
Post::query()->get();                    // only non-deleted
Post::query()->withTrashed()->get();     // include deleted
Post::query()->onlyTrashed()->get();     // only deleted
```

---

## Model validation

```php
class User extends Model {
    protected static string $table = 'users';

    protected static array $rules = [
        'name'  => 'required|min:2|max:100',
        'email' => 'required|email',
        'age'   => 'integer|min:0',
        'role'  => 'in:admin,editor,viewer',
        'site'  => 'url',
    ];
}

$user->name  = '';
$user->email = 'not-an-email';

if (!$user->passes()) {
    print_r($user->errors());
    // ['name' => ['The name field is required.'], 'email' => ['...must be a valid email']]
}

// Or get all errors at once:
$errors = $user->validate();
```

Available rules: `required`, `email`, `integer`, `numeric`, `min:N`, `max:N`, `in:a,b,c`, `url`, `boolean`.

---

## Model events

```php
use Core\Events\ModelEvents;

// Listen for any save on 'orders'
ModelEvents::listen('orders', 'created', function (Model $order) {
    // send confirmation email
});

// Cancel a delete by returning false
ModelEvents::listen('orders', 'deleting', function (Model $order) {
    if ($order->status === 'completed') return false;
});

// Observer class
class OrderObserver {
    public function creating(Model $m): void { /* set defaults */ }
    public function updated(Model $m): void  { /* clear cache */ }
}

ModelEvents::registerObserver('orders', new OrderObserver());
// or on the model class:
Order::observe('orders', new OrderObserver());
```

Events fired: `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`, `saving`, `saved`.  
Returning `false` from `creating`, `updating`, or `deleting` cancels the operation.

---

## Global scopes

Apply automatic query constraints to every query on a model:

```php
class ActiveUser extends Model {
    protected static string $table = 'users';
    protected static array $globalScopes = [];
}

// Register once (e.g. in a service provider or boot):
ActiveUser::addGlobalScope('active', fn($q) => $q->where('active', 1));

ActiveUser::query()->get();                   // WHERE active = 1 always applied
ActiveUser::withoutGlobalScopes()->get();     // bypass all scopes
ActiveUser::removeGlobalScope('active');      // remove permanently
```

---

## Local scopes

Define reusable query constraints on the model class:

```php
class Product extends Model {
    protected static string $table = 'products';

    public function scopeActive(\Core\ModelQuery $q): void {
        $q->where('active', 1);
    }

    public function scopePricedBelow(\Core\ModelQuery $q, float $max): void {
        $q->where('price', $max, '<');
    }
}

Product::query()->active()->pricedBelow(50)->get();
// or: Product::query()->scope('active')->scope('pricedBelow', 50)->get();
```

---

## Query caching

```php
// Cache for 60 seconds (uses APCu when available, in-process array otherwise)
$users = Model::query('users')
    ->where('active', 1)
    ->remember(60)
    ->get();

// With explicit cache key
$users = Model::query('users')
    ->remember(300, 'active-users-list')
    ->get();

// Clear in-process cache (useful in tests)
Model::query('users')->clearMemCache();
```

---

## JWT Authentication

Add these to `.env` to enable auth endpoints:

```env
AUTH_TABLE=users
AUTH_USERNAME_COLUMN=email
AUTH_PASSWORD_COLUMN=password
AUTH_TOKEN_TTL=3600
AUTH_REFRESH_TTL=2592000
```

Hash a password for storage:

```bash
php LazyMePHP auth:hash mypassword
```

### Endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/auth/login` | Returns `{access_token, token_type, expires_in, refresh_token, refresh_expires_in}` |
| `POST` | `/auth/refresh` | Rotates the refresh token and issues a new access token |
| `POST` | `/auth/logout` | Revokes the provided refresh token |
| `GET` | `/auth/me` | Returns the authenticated user (requires Bearer token) |

### Refresh tokens

Refresh tokens are opaque 64-character hex strings. The raw token is returned to the client once; only its SHA-256 hash is stored in the database. On every `/auth/refresh` call the old token is immediately revoked and a new pair is issued (rotation).

```json
POST /auth/login
→ {
    "access_token": "<jwt>",
    "token_type": "Bearer",
    "expires_in": 3600,
    "refresh_token": "<64-hex>",
    "refresh_expires_in": 2592000
  }

POST /auth/refresh  { "refresh_token": "<64-hex>" }
→ { "access_token": "<new-jwt>", "refresh_token": "<new-64-hex>", ... }
```

The refresh endpoint is rate-limited to 20 requests per 5 minutes per IP.

### Protecting routes

```php
use Core\Auth\JwtMiddleware;

$router->post('/orders', [OrderController::class, 'store'])
       ->addMiddleware(JwtMiddleware::class);
```

### Using `Auth` in code

```php
use Core\Auth\Auth;

// In a protected context (Bearer token already validated by middleware)
$user = Auth::user();   // array without password column
$id   = Auth::id();
$ok   = Auth::check();  // true when a valid Bearer token is present
```

---

## Role-Based Access Control (RBAC)

Run `php LazyMePHP migrate` to create the RBAC tables (`__AUTH_ROLES`, `__AUTH_ROLE_PERMISSIONS`, `__AUTH_USER_ROLES`).

```php
use Core\Auth\RBAC;

// Setup
RBAC::createRole('admin');
RBAC::createRole('editor');
RBAC::grantPermission('editor', 'posts.create');
RBAC::grantPermission('editor', 'posts.update');
RBAC::assignRole($userId, 'editor');

// Checks
RBAC::can($userId, 'posts.create');  // true
RBAC::is($userId, 'editor');         // true
RBAC::is($userId, 'admin');          // false

RBAC::rolesFor($userId);             // ['editor']
RBAC::permissionsFor($userId);       // ['posts.create', 'posts.update']
```

### RBAC middleware

```php
use Core\Auth\RequiresPermission;
use Core\Auth\RequiresRole;

// Require a specific permission
$router->post('/posts', [PostController::class, 'store'])
       ->addMiddleware(new RequiresPermission('posts.create'));

// Require a role (any of the listed roles)
$router->get('/admin', [AdminController::class, 'index'])
       ->addMiddleware(new RequiresRole('admin', 'superuser'));

// Require ALL listed roles
$router->delete('/nuke', [AdminController::class, 'nuke'])
       ->addMiddleware((new RequiresRole('admin', 'superuser'))->all());
```

Both middleware return `401` if the request is unauthenticated, `403` if the role/permission check fails.

---

## API Resources

Shape model output for APIs:

```php
use Core\Http\ApiResource;

class UserResource extends ApiResource {
    public function toArray(): array {
        return [
            'id'    => $this->model->id,
            'name'  => $this->model->name,
            'email' => $this->model->email,
            // password is omitted
        ];
    }
}

// Single resource
UserResource::make($user)->respond();        // sets header + outputs JSON
$json = UserResource::make($user)->toJson();

// Collection
UserResource::collection($users)->respond();

// With metadata
UserResource::collection($users)
    ->withMeta(['total' => 120, 'page' => 2])
    ->respond();
```

Response shape:

```json
{ "data": { "id": 1, "name": "Alice", "email": "alice@example.com" } }

{ "data": [...], "meta": { "total": 120, "page": 2 } }
```

---

## Seeder system

Seeders populate the database with initial or test data.

```bash
php LazyMePHP make:seeder UserSeeder    # scaffold App/Seeders/UserSeeder.php
php LazyMePHP db:seed                   # run all seeders
php LazyMePHP db:seed --class=UserSeeder
```

```php
// App/Seeders/UserSeeder.php
use Core\Seeder\Seeder;

class UserSeeder extends Seeder {
    public function run(): void {
        $this->insert('users', ['name' => 'Admin', 'email' => 'admin@example.com']);
    }
}
```

---

## Model factories

Factories generate model instances for tests and seeding.

```bash
php LazyMePHP make:factory PostFactory   # scaffold App/Factories/PostFactory.php
```

```php
// App/Factories/PostFactory.php
use Core\Factory\Factory;

class PostFactory extends Factory {
    protected string $table = 'posts';

    public function definition(): array {
        static $n = 0; $n++;
        return [
            'title'   => "Post {$n}",
            'body'    => 'Lorem ipsum',
            'user_id' => 1,
        ];
    }
}

// Usage
$post  = PostFactory::new()->make();              // unsaved Model
$post  = PostFactory::new()->create();            // saved to DB
$posts = PostFactory::new()->count(10)->create(); // 10 saved models
$post  = PostFactory::new()->state(['user_id' => 5])->create();
```

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

These 6 routes are fixed — to add, drop, or reshape routes for a table entirely (not just what happens inside them), create `App/Routes/{table}.php` or scaffold one with `php LazyMePHP make:router <table>`. Its presence fully replaces the standard 6 for that table.

Views are rendered by generic Blade templates in `App/Views/_Crud/`. To override a table's view, create `App/Views/{TableName}/index.blade.php` or `edit.blade.php` — the controller resolves to the table-specific file first, then falls back to the generic template.

---

## Customising behaviour — `Core\CrudController`

Create `App/Controllers/{TableName}.php` to override behaviour for a specific table — or scaffold it with `php LazyMePHP make:controller <table>` (add `--hidden` to exclude the table from auto-routing and GraphQL). The GraphQL API needs no separate scaffolding: it's generated at runtime from this same controller.

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

    // Restricts this table's GraphQL queries/mutations to callers with the
    // given role(s) — checked via Core\Auth\RBAC::is(). Empty (the default)
    // means no restriction beyond authentication.
    public function requiredRoles(): array {
        return ['admin'];
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
| Authentication | `JwtMiddleware` rejects requests with no valid Bearer token when `AUTH_TABLE` is configured |
| Authorization | Per-table, via `CrudController::requiredRoles()` — see below |

A JWT Bearer token is all `JwtMiddleware` can check — it runs before the query is even parsed, and one GraphQL request can touch several tables at once, so there's no single route to attach a per-table role check to. Per-table restriction is opt-in on the table's controller instead:

```php
class Users extends CrudController {
    public function requiredRoles(): array {
        return ['admin']; // empty (default) = no restriction beyond authentication
    }
}
```

Every query/mutation for that table then checks `Core\Auth\RBAC::is($role)` and throws a `GraphQL\Error\UserError` if the caller has none of the required roles.

---

## OpenAPI spec

A full OpenAPI 3.0 specification is auto-generated from the live schema:

```
GET /openapi.json
```

The spec includes CRUD paths for every non-system table (tables without `__` prefix), plus auth endpoints when `AUTH_TABLE` is configured. Disable with `OPENAPI_ENABLED=false` in `.env`.

---

## Health check

```
GET /health
```

Returns `200 OK` when the database is reachable, `503 Service Unavailable` otherwise:

```json
{
    "status": "ok",
    "db": { "status": "ok", "type": "sqlite" },
    "php": "8.3.0",
    "memory": { "used": "4.2 MB", "peak": "5.1 MB", "limit": "128M" }
}
```

---

## Request ID tracing

Every response includes an `X-Request-ID` header. If the incoming request already has a valid `X-Request-ID` (alphanumeric + hyphens, max 36 chars), it is echoed back; otherwise a new UUID-shaped value is generated.

```php
use Core\Http\RequestId;

$id = RequestId::current(); // access the current request's ID anywhere
```

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

## Cache

Configure via `.env`:

```env
CACHE_DRIVER=redis    # array (default) | apcu | redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
```

```php
use Core\Cache\Cache;

Cache::set('key', $value, 3600);
$value = Cache::get('key');
Cache::delete('key');
Cache::has('key');
Cache::increment('rate:hits', 1, 60);

// Get or compute:
$users = Cache::remember('all-users', 300, fn() => Model::query('users')->get());
```

The same driver backs `ModelQuery::remember()` — switching `CACHE_DRIVER=redis` makes all query caching persistent across workers automatically.

---

## Rate limiting

```php
use Core\Http\RateLimit;

// 60 requests per minute per IP (default key)
$router->post('/api/contact', [ContactController::class, 'send'])
       ->addMiddleware(new RateLimit(60, 60));

// 5 requests per minute per authenticated user
$router->post('/api/ai', [AiController::class, 'complete'])
       ->addMiddleware(new RateLimit(5, 60, fn() => Auth::id() ?? 'anon'));
```

Exceeded requests get `429 Too Many Requests` with `Retry-After` and `X-RateLimit-*` headers.

---

## Background jobs (queue)

Configure the driver:

```env
QUEUE_DRIVER=database    # sync (default) | database | redis
```

Define a job:

```bash
php LazyMePHP make:job SendWelcomeEmail
```

```php
// App/Jobs/SendWelcomeEmail.php
class SendWelcomeEmail extends \Core\Queue\Job {
    public int    $tries  = 3;
    public int    $userId = 0;

    public function handle(): void {
        $user = new Model('users', $this->userId);
        mail($user->email, 'Welcome!', '...');
    }

    public function failed(\Throwable $e): void {
        // log or alert on permanent failure
    }
}
```

Dispatch from anywhere:

```php
use Core\Queue\Queue;

Queue::dispatch(new SendWelcomeEmail(['userId' => $user->getPrimaryKey()]));
Queue::dispatch(new SendWelcomeEmail(['userId' => 1]), 'high'); // named queue
```

Run the worker:

```bash
php LazyMePHP queue:work                            # default queue
php LazyMePHP queue:work --queue=high --sleep=1
php LazyMePHP queue:work --stop-when-empty          # exit after draining
php LazyMePHP queue:size                            # pending job count
```

The `database` driver stores jobs in `__queue_jobs` (created by `php LazyMePHP migrate`). The `redis` driver uses Redis lists.

---

## FormRequest validation

Validate controller input independently of any model:

```bash
php LazyMePHP make:request CreatePostRequest
```

```php
// App/Requests/CreatePostRequest.php
class CreatePostRequest extends \Core\Http\FormRequest {
    public function rules(): array {
        return [
            'title' => 'required|min:3|max:255',
            'body'  => 'required',
            'email' => 'required|email',
            'role'  => 'in:admin,editor',
            'site'  => 'url',
            'age'   => 'integer|min:0|max:120',
        ];
    }

    public function authorize(): bool {
        return Auth::check();
    }
}

// In a controller or route handler:
$req = new CreatePostRequest();   // reads from $_POST / JSON body automatically
if ($req->fails()) {
    return json_encode(['errors' => $req->errors()]);
}
$data = $req->validated();   // only rule-listed fields
```

Available rules: `required`, `email`, `url`, `integer`, `numeric`, `boolean`, `min:N`, `max:N`, `in:a,b,c`, `regex:/pattern/`.

---

## File storage

```env
STORAGE_DRIVER=local
STORAGE_PATH=storage/app
STORAGE_URL=/storage
```

```php
use Core\Storage\Storage;
use Core\Storage\UploadedFile;

// Store arbitrary content
Storage::disk()->put('reports/2026-07.csv', $csv);
$contents = Storage::disk()->get('reports/2026-07.csv');
$url      = Storage::disk()->url('reports/2026-07.csv');  // /storage/reports/2026-07.csv

// Handle a file upload
$file = UploadedFile::fromInput('avatar');
if ($file && $file->isValid()) {
    $path = $file->store('avatars');   // 'avatars/<random-hex>.jpg'
    $url  = Storage::disk()->url($path);
}

// Multiple uploads
$files = UploadedFile::fromInputMultiple('documents');
foreach ($files as $f) {
    if ($f->isValid()) $f->store('docs');
}
```

---

## Multi-tenancy

Add a `tenants` table, then configure:

```env
TENANT_TABLE=tenants
TENANT_COLUMN=slug
TENANT_RESOLVE=subdomain    # subdomain | header | path | jwt
TENANT_REQUIRE=true
```

Wire up the middleware on all tenant routes:

```php
use Core\Tenancy\TenantMiddleware;

$router->group(['middleware' => TenantMiddleware::class], function () {
    // All routes here resolve and require a valid tenant
});
```

Access the current tenant anywhere:

```php
use Core\Tenancy\Tenant;

Tenant::id();       // e.g. 1
Tenant::slug();     // e.g. 'acme'
Tenant::get('name'); // any column from the tenants table
Tenant::isResolved(); // true after middleware ran
```

Scope models to the current tenant automatically using `HasTenant`:

```php
use Core\Model;
use Core\Tenancy\HasTenant;

class Post extends Model {
    use HasTenant;
    protected static string $table = 'posts';
    // posts must have a tenant_id column
}

// In boot() or a service provider:
Post::initializeTenantScope();

// Now every query is automatically scoped:
Post::query()->get();    // WHERE tenant_id = <current> always applied
// New records get tenant_id set automatically on Save()
```

Resolution strategies:
- **subdomain**: `acme.app.example.com` → identifier `acme`
- **header**: `X-Tenant-ID: acme` request header
- **path**: `/acme/posts` → identifier `acme` (first URL segment)
- **jwt**: `tenant` claim in the Bearer JWT

---

## CLI reference

```
php LazyMePHP serve                      Start the PHP development server (port 8080)

php LazyMePHP migrate                    Run all pending migrations
php LazyMePHP migrate:rollback           Roll back the last migration batch
php LazyMePHP migrate:rollback --step=N
php LazyMePHP migrate:reset              Roll back all migrations
php LazyMePHP migrate:status             Show migration run history

php LazyMePHP make:migration <name>      Scaffold a new migration file
php LazyMePHP make:model <Name>          Scaffold a Model subclass
php LazyMePHP make:controller <table>    Scaffold App/Controllers/{Table}.php extending CrudController
php LazyMePHP make:controller <table> --hidden   ...and exclude it from auto-routing + GraphQL
php LazyMePHP make:view <table>          Scaffold App/Views/{table}/index.blade.php + edit.blade.php
php LazyMePHP make:router <table>        Scaffold App/Routes/{table}.php — fully replaces its 6 standard routes
php LazyMePHP make:all <table>           Scaffold both the controller and the views for a table
php LazyMePHP make:seeder <Name>         Scaffold a Seeder class in App/Seeders/
php LazyMePHP make:factory <Name>        Scaffold a Factory class in App/Factories/
php LazyMePHP make:observer <Name>       Scaffold a model observer class
php LazyMePHP make:resource <Name>       Scaffold an ApiResource subclass
php LazyMePHP make:job <Name>            Scaffold a queue Job class in App/Jobs/
php LazyMePHP make:request <Name>        Scaffold a FormRequest in App/Requests/
php LazyMePHP make:mail <Name>           Scaffold a Mailable class in App/Mail/
php LazyMePHP make:test <Name>           Scaffold a Pest test
php LazyMePHP make:command <Name>        Scaffold a console command
php LazyMePHP make:middleware <Name>     Scaffold a middleware class

php LazyMePHP db:seed                    Run all seeders in App/Seeders/
php LazyMePHP db:seed --class=<Name>     Run a specific seeder class

php LazyMePHP auth:hash <password>       Print a bcrypt hash of <password>

php LazyMePHP schema:cache               Pre-warm schema cache for all tables
php LazyMePHP schema:cache <table>       Pre-warm schema cache for one table
php LazyMePHP schema:clear               Remove all schema cache files

php LazyMePHP queue:work                 Start the queue worker (default queue)
php LazyMePHP queue:work --queue=<name>  Work a specific named queue
php LazyMePHP queue:work --sleep=3       Seconds to sleep when queue is empty
php LazyMePHP queue:work --tries=3       Max attempts per job before failing
php LazyMePHP queue:work --stop-when-empty   Exit after draining the queue
php LazyMePHP queue:size                 Show pending job count
php LazyMePHP queue:size --queue=<name>  Show pending count for a named queue
```

---

## Security overview

| Area | Measure |
|---|---|
| Sessions | `httponly`, `samesite=Strict`, `secure` in production |
| CSRF | Token-per-session with rotation; all web form posts validated |
| CORS | Exact-origin allowlist via `APP_CORS_ORIGIN`; wildcard blocked |
| JWT | HS256, signed with `APP_ENCRYPTION` (≥ 32 chars enforced) |
| Refresh tokens | Opaque 64-char hex; SHA-256 hash stored in DB; rotation on every use |
| RBAC | Role + permission middleware; 401 when unauthenticated, 403 when unauthorised |
| Batman login | bcrypt `password_verify()` against `BATMAN_SECRET` |
| Redirects | Path-only redirects; host stripping prevents open redirect |
| SQL injection | All queries use prepared statement placeholders |
| Column injection | Filter and sort columns validated against live schema |
| CSP | `default-src 'self'`; no `unsafe-inline` |
| GraphQL | Depth 7, complexity 200, introspection off in production; per-table role authorization via `requiredRoles()` |
| Audit log | Sensitive columns auto-stripped; passwords never logged |
| Rate limiting | Refresh token endpoint: 20 requests per 5 minutes per IP |

---

## Requirements

- PHP 8.1+
- Composer
- MySQL, MSSQL, or SQLite

---

## License

MIT
