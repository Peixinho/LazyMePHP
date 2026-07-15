---
id: jwt
title: JWT Authentication
sidebar_position: 1
---

# JWT Authentication

LazyMePHP ships with a full JWT auth system — login, logout, refresh-token rotation, and route protection middleware — enabled by four env variables.

## Setup

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
# → $2y$12$...  (copy into your users table)
```

Run `php LazyMePHP migrate` to create the refresh-token table (`__auth_refresh_tokens`).

## Endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/auth/login` | Returns `access_token` + `refresh_token` |
| `POST` | `/auth/refresh` | Rotates the refresh token, issues a new access token |
| `POST` | `/auth/logout` | Revokes the provided refresh token |
| `GET` | `/auth/me` | Returns the authenticated user (requires Bearer token) |

### Login

```http
POST /auth/login
Content-Type: application/json

{ "email": "alice@example.com", "password": "secret" }
```

```json
{
    "access_token": "<jwt>",
    "token_type": "Bearer",
    "expires_in": 3600,
    "refresh_token": "<64-hex>",
    "refresh_expires_in": 2592000
}
```

### Refresh

```http
POST /auth/refresh
Content-Type: application/json

{ "refresh_token": "<64-hex>" }
```

```json
{
    "access_token": "<new-jwt>",
    "token_type": "Bearer",
    "expires_in": 3600,
    "refresh_token": "<new-64-hex>",
    "refresh_expires_in": 2592000
}
```

The old token is revoked immediately on use — if an attacker replays the same token after rotation, it will be rejected.

The refresh endpoint is rate-limited to **20 requests per 5 minutes per IP**.

## Protecting routes

```php
use Core\Auth\JwtMiddleware;

$router->post('/orders', [OrderController::class, 'store'])
       ->addMiddleware(JwtMiddleware::class);
```

An invalid or expired token returns `401 Unauthorized`.

## Using `Auth` in code

```php
use Core\Auth\Auth;

$user = Auth::user();   // array of the authenticated user's columns (password excluded)
$id   = Auth::id();     // primary key of the authenticated user
$ok   = Auth::check();  // true when a valid Bearer token is present
```

## Token internals

- Algorithm: HS256
- Secret: `APP_ENCRYPTION` (minimum 32 characters, enforced at boot)
- Refresh tokens: opaque 64-character hex strings. Only the SHA-256 hash is stored in the database. The raw token is returned to the client once and never stored again.
