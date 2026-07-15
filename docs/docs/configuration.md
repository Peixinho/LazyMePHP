---
id: configuration
title: Configuration
sidebar_position: 2
---

# Configuration

All settings live in `.env` in the project root. Copy `.env.example` to get started.

## Database

| Variable | Description |
|---|---|
| `DB_TYPE` | `mysql`, `mssql`, or `sqlite` |
| `DB_HOST` | Database host (MySQL / MSSQL) |
| `DB_NAME` | Database name (MySQL / MSSQL) |
| `DB_USER` | Username (MySQL / MSSQL) |
| `DB_PASSWORD` | Password (MySQL / MSSQL) |
| `DB_FILE_PATH` | Path to SQLite file (use `:memory:` for tests) |

## Application

| Variable | Description |
|---|---|
| `APP_NAME` | Application name |
| `APP_TITLE` | HTML page title |
| `APP_TIMEZONE` | PHP timezone string (e.g. `Europe/Lisbon`) |
| `APP_NRESULTS` | Default page size for paginated lists |
| `APP_ENCRYPTION` | Secret key (≥ 32 chars) — used for JWT signing |
| `APP_ENV` | `development` enables GraphQL introspection and debug traces |
| `APP_CORS_ORIGIN` | Exact origin allowed for cross-origin requests (empty = block all) |
| `APP_ACTIVITY_LOG` | `true` to enable change audit logging |
| `APP_ACTIVITY_AUTH` | Fallback identifier written to the audit log when no JWT user is present |

## Authentication

| Variable | Description |
|---|---|
| `AUTH_TABLE` | Table used for JWT login (enables `POST /auth/login`) |
| `AUTH_USERNAME_COLUMN` | Column checked as the login credential |
| `AUTH_PASSWORD_COLUMN` | Column holding the bcrypt-hashed password |
| `AUTH_TOKEN_TTL` | JWT lifetime in seconds (default `3600`) |
| `AUTH_REFRESH_TTL` | Refresh token lifetime in seconds (default `2592000` = 30 days) |

## Batman dashboard

| Variable | Description |
|---|---|
| `BATMAN_USERNAME` | Dashboard login username (default `admin`) |
| `BATMAN_SECRET` | Dashboard password as a bcrypt hash |

## Cache

| Variable | Description |
|---|---|
| `CACHE_DRIVER` | `array` (default), `apcu`, or `redis` |
| `REDIS_HOST` | Redis host (default `127.0.0.1`) |
| `REDIS_PORT` | Redis port (default `6379`) |
| `REDIS_PASSWORD` | Redis password (empty = no auth) |
| `REDIS_DB` | Redis database index (default `0`) |

## Queue

| Variable | Description |
|---|---|
| `QUEUE_DRIVER` | `sync` (default), `database`, or `redis` |

## File storage

| Variable | Description |
|---|---|
| `STORAGE_DRIVER` | `local` (default) |
| `STORAGE_PATH` | Root directory (default `storage/app`) |
| `STORAGE_URL` | Public URL prefix (default `/storage`) |

## Multi-tenancy

| Variable | Description |
|---|---|
| `TENANT_TABLE` | Tenants table name (enables multi-tenancy when set) |
| `TENANT_COLUMN` | Column used to look up the tenant (default `slug`) |
| `TENANT_RESOLVE` | `subdomain` \| `header` \| `path` \| `jwt` |
| `TENANT_REQUIRE` | Return 400/404 if no tenant found (default `true`) |

## Other

| Variable | Description |
|---|---|
| `OPENAPI_ENABLED` | Set to `false` to disable `/openapi.json` |

## Reading config in code

Use `Core\Config` instead of `$_ENV` directly. It maps dot-notation to env keys (`mail.host` → `MAIL_HOST`) and adds type coercion helpers:

```php
use Core\Config;

$env    = Config::get('app.env', 'production');  // APP_ENV
$port   = Config::int('mail.port', 587);          // MAIL_PORT as int
$debug  = Config::bool('app.debug', false);       // APP_DEBUG as bool
$exists = Config::has('redis.password');           // true/false

// Runtime overrides (useful in tests or bootstrap)
Config::set('app.env', 'testing');
Config::flush(); // clear overrides
```

`Config::get()` checks runtime overrides first, then falls back to `$_ENV`.
