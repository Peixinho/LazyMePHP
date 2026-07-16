---
id: routing
title: Routing
sidebar_position: 3
---

# Routing

LazyMePHP uses [Pecee Simple Router](https://github.com/skipperbent/simple-php-router) for URL routing. Every top-level `.php` file directly in `App/Routes/` is loaded automatically at boot (`public/index.php`) — `App/Routes/Routes.php` is where custom routes and `LazyMePHP::boot()` normally live, but any file you add there is picked up the same way.

## Basic Routes

```php
use Pecee\SimpleRouter\SimpleRouter;

SimpleRouter::get('/', function () {
    echo view('home.index');
});

SimpleRouter::post('/contact', [ContactController::class, 'send']);
```

Supported methods: `get`, `post`, `put`, `patch`, `delete`, `options`, `any`.

## Route Parameters

```php
// Required parameter
SimpleRouter::get('/users/{id}', function (int $id) {
    $user = new \Core\Model('users', $id);
});

// Optional parameter
SimpleRouter::get('/search/{query?}', function (?string $query = null) {
    // $query is null if omitted
});

// Multiple parameters
SimpleRouter::get('/posts/{year}/{slug}', function (int $year, string $slug) {
    // ...
});
```

## Route Groups

Group routes under a shared prefix or middleware:

```php
SimpleRouter::group(['prefix' => '/admin'], function () {
    SimpleRouter::get('/dashboard', [DashboardController::class, 'index']);
    SimpleRouter::get('/users',     [UserController::class, 'index']);
});
```

## Middleware

Middleware implements `\Pecee\SimpleRouter\Handlers\IMiddleware`. Apply it per route or to a group:

```php
namespace Middleware;

use Pecee\Http\Request;

class AuthMiddleware implements \Pecee\SimpleRouter\Handlers\IMiddleware
{
    public function handle(Request $request): void
    {
        if (empty($_SESSION['user_id'])) {
            \Pecee\SimpleRouter\SimpleRouter::response()->redirect('/login');
        }
    }
}
```

```php
// Apply to a group
SimpleRouter::group(['middleware' => AuthMiddleware::class], function () {
    SimpleRouter::get('/account', [AccountController::class, 'show']);
    SimpleRouter::post('/account', [AccountController::class, 'update']);
});
```

CSRF protection is applied automatically to all non-GET, non-`/api/` routes by `CsrfMiddleware` — you don't need to add it manually.

## Controllers

A controller is any class with public methods:

```php
namespace Controllers;

class UserController
{
    public function index(): void
    {
        $users = \Core\Model::query('users')->paginate(20, (int)($_GET['page'] ?? 1));
        // render view ...
    }

    public function show(int $id): void
    {
        $user = new \Core\Model('users', $id);
        // render view ...
    }
}
```

Register it in a route file:

```php
SimpleRouter::get('/users',      [UserController::class, 'index']);
SimpleRouter::get('/users/{id}', [UserController::class, 'show']);
```

## Auto-Wired CRUD Routes

`LazyMePHP::boot($blade)` registers full CRUD routes for every table in the database:

```php
// App/Routes/Routes.php
LazyMePHP::boot($blade);
```

This generates the following routes for each table:

| Method | Path | Action |
|---|---|---|
| GET | `/{table}` | Paginated list with filters |
| GET | `/{table}/new` | Create form |
| GET | `/{table}/{id}/edit` | Edit form |
| POST | `/{table}` | Store |
| POST | `/{table}/{id}` | Update |
| POST | `/{table}/{id}/delete` | Destroy |

Custom routes defined **before** `boot()` take priority. Routes defined after are registered as fallbacks.

To exclude a table from auto-wiring:

```php
namespace Controllers;

class SecretAuditLog extends \Core\CrudController
{
    public static bool $hidden = true;
}
```

See [CRUD Web UI](./crud-ui) for full customisation options.

## Overriding the Auto-Wired Routes

The 6 standard routes above cover the common case, but they're fixed — you can't add an extra endpoint or drop one of them through `CrudController` hooks alone, since those only change what happens *inside* a route, not the route set itself.

If a table needs different routes entirely (extra endpoints, a different URL shape, fewer than 6 actions), create `App/Routes/{table}.php`. Its presence completely replaces the standard 6-route registration for that table — `AutoRouter` requires this file instead of registering its own routes when it exists.

```bash
php LazyMePHP make:router products
```

This scaffolds `App/Routes/products.php` pre-filled with the current 6 standard routes as an editable copy — delete what you don't need, add what you do:

```php
<?php
// App/Routes/products.php
use Core\CrudController;
use Pecee\SimpleRouter\SimpleRouter;

$table = 'products';

SimpleRouter::get("/$table", function () use ($table) { /* ... */ });
// ...

// Add your own routes:
SimpleRouter::get("/$table/featured", function () {
    $featured = \Core\Model::query('products')->where('featured', 1)->get();
    // render view ...
});
```

Since every top-level file in `App/Routes/` is loaded at boot regardless (see above), `AutoRouter` uses `require_once` when picking up an override — no risk of routes being registered twice.
