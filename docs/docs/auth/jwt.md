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

## Password reset

Run `php LazyMePHP migrate` to create the `__AUTH_PASSWORD_RESETS` table, then:

```php
use Core\Auth\Auth;

// 1. Generate a reset token and email it to the user
$token = Auth::createPasswordResetToken($user['id']);
// send $token in a link: https://yourapp.com/reset-password?token={$token}

// 2. Validate on the reset page (does NOT consume the token)
$userId = Auth::validatePasswordResetToken($token); // null if invalid/expired

// 3. Change the password and consume the token (single-use)
$ok = Auth::consumePasswordResetToken($token, $newPassword);
```

Or use the built-in endpoints:

```
POST /auth/forgot-password   {"email": "alice@example.com"}
POST /auth/reset-password    {"token": "...", "password": "new_password"}
```

`forgot-password` always returns 200 to prevent user enumeration. It fires a `password.reset.requested` model event so you can hook in your mailer:

```php
use Core\Events\ModelEvents;

ModelEvents::listen('users', 'password.reset.requested', function (array $payload): void {
    ['user' => $user, 'token' => $token] = $payload;
    Mail::to($user['email'])->subject('Reset your password')
        ->text("Use this link: https://yourapp.com/reset-password?token={$token}")
        ->send();
});
```

TTL is controlled by `AUTH_PASSWORD_RESET_TTL` (env, default 3600 seconds).

## Email verification

Run `php LazyMePHP migrate` to create `__AUTH_EMAIL_VERIFICATIONS`, then:

```php
// 1. Issue a token after registration
$token = Auth::createEmailVerificationToken($user['id']);

// 2. Verify and mark the user's email (single-use)
$userId = Auth::verifyEmail($token); // null if invalid/expired
```

Built-in endpoint:

```
POST /auth/verify-email   {"token": "..."}
```

Set `AUTH_EMAIL_VERIFIED_COLUMN` (env, default `email_verified_at`) to the column that stores the verification timestamp. If the column exists in the users table it is set to `now()` automatically.

TTL is controlled by `AUTH_EMAIL_VERIFY_TTL` (env, default 86400 seconds = 24 hours).
