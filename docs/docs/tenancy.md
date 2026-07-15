---
id: tenancy
title: Multi-Tenancy
sidebar_position: 11
---

# Multi-Tenancy

LazyMePHP supports multi-tenant applications where each tenant is a row in a `tenants` table. The current tenant is resolved from the incoming request using one of four strategies.

## Configuration

Create a `tenants` table with at least an `id` and a `slug` (or whatever identifier column you choose), then:

```env
TENANT_TABLE=tenants
TENANT_COLUMN=slug         # column used to look up the tenant
TENANT_RESOLVE=subdomain   # subdomain | header | path | jwt
TENANT_REQUIRE=true        # return 400/404 if no tenant is found
```

## Wiring up

Add `TenantMiddleware` to all routes that require a tenant:

```php
use Core\Tenancy\TenantMiddleware;

$router->group(['middleware' => TenantMiddleware::class], function () {
    // All routes inside here resolve and require a valid tenant
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/posts',     [PostController::class, 'index']);
});
```

## Accessing the current tenant

```php
use Core\Tenancy\Tenant;

Tenant::id();           // e.g. 1
Tenant::slug();         // e.g. 'acme'
Tenant::get('name');    // any column from the tenants table
Tenant::isResolved();   // true after middleware has run
```

## Scoping models

Mix in `HasTenant` to automatically scope every query to the current tenant:

```php
use Core\Model;
use Core\Tenancy\HasTenant;

class Post extends Model {
    use HasTenant;
    protected static string $table = 'posts';
    // posts table must have a tenant_id column
}

// Call once at boot / service-provider time:
Post::initializeTenantScope();

// Every query now automatically adds WHERE tenant_id = <current>:
Post::query()->get();

// New records automatically get tenant_id set on Save():
$post = new Post();
$post->title = 'Hello';
$post->Save();  // tenant_id is set automatically
```

## Resolution strategies

| Strategy | How the tenant is identified |
|---|---|
| `subdomain` | `acme.app.example.com` → identifier `acme` (first subdomain segment) |
| `header` | `X-Tenant-ID: acme` request header |
| `path` | `/acme/posts` → identifier `acme` (first URL segment) |
| `jwt` | `tenant` claim in the Bearer JWT |

The identified slug is looked up against `TENANT_COLUMN` in `TENANT_TABLE`. If no matching row is found and `TENANT_REQUIRE=true`, the middleware returns a `404` response before the route handler runs.
