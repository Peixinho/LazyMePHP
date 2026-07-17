---
id: security
title: Security
sidebar_position: 16
---

# Security

## Overview

| Area | Measure |
|---|---|
| Sessions | `httponly`, `samesite=Strict`, `secure` in production |
| CSRF | Token-per-session with rotation; all web form POSTs validated |
| CORS | Exact-origin allowlist via `APP_CORS_ORIGIN`; wildcard blocked |
| JWT | HS256, signed with `APP_ENCRYPTION` (≥ 32 chars enforced at boot) |
| Refresh tokens | Opaque 64-char hex; SHA-256 hash stored in DB; rotation on every use |
| RBAC | Role + permission middleware; 401 when unauthenticated, 403 when unauthorised |
| Batman login | bcrypt `password_verify()` against `BATMAN_SECRET` |
| Redirects | Path-only redirects; host stripping prevents open redirect |
| SQL injection | All queries use prepared statement placeholders |
| Column injection | Filter and sort columns validated against live schema |
| CSP | `default-src 'self'`; no `unsafe-inline` |
| GraphQL | Depth 7, complexity 200, introspection off in production; per-table role authorization via `requiredRoles()` |
| Audit log | Sensitive columns auto-stripped; passwords never logged |
| Rate limiting | Refresh endpoint: 20 req / 5 min / IP; configurable per route |

## SQL injection

Every query in the ORM and raw query interface uses PDO prepared statements. Bindings are never interpolated into SQL strings:

```php
// Safe — value is a bound parameter, never interpolated
Model::query('users')->where('email', $email)->get();

// Also safe
LazyMePHP::DB_CONNECTION()->query('SELECT * FROM users WHERE email = ?', [$email]);
```

## CSRF

CSRF tokens are generated per-session and rotated on every POST. The token is validated before any write operation on a web form route. API routes (those returning JSON) are exempt — they rely on JWT instead.

## Refresh token security

1. A 64-character random hex string is generated (`random_bytes(32)` → `bin2hex`).
2. The raw token is returned to the client **once** in the login response — it is never stored server-side in plain text.
3. Only the SHA-256 hash of the raw token is persisted in `__auth_refresh_tokens`.
4. On every `/auth/refresh` call, the provided token is hashed and compared. The old token is immediately revoked whether or not the hash matches — stolen tokens cannot be replayed.

## GraphQL authorization

`JwtMiddleware` only answers "is there a valid Bearer token" — GraphQL deliberately lets one request touch several tables at once (`{ usersList { id } roomsList { id } }`), so there's no single URL/route to attach a per-table role check to the way there is for a web route. Per-table authorization is enforced instead in `Core\GraphQL\SchemaBuilder`, at the resolver level, via `Core\CrudController::requiredRoles()`:

```php
class Users extends CrudController {
    public function requiredRoles(): array {
        return ['Gestor'];
    }
}
```

Empty (the default) means no restriction beyond authentication — every table keeps working exactly as before unless you opt in. When non-empty, every query and mutation for that table checks `Core\Auth\RBAC::is($role)` — which resolves via `Core\Auth\Auth::id()`, the same JWT identity `JwtMiddleware` already validated — and throws a `GraphQL\Error\UserError` ("Forbidden: ...") if the caller has none of the required roles.

This declaration only governs GraphQL. If your app also restricts the same table's web routes via its own middleware (path-prefix rules, typically), that's a separate declaration today — nothing stops you from having your middleware call the same table's `requiredRoles()` too, so the rule lives in one place, but the framework doesn't do that wiring for you. See [Extending & Customizing](./extending).

## Content Security Policy

All web responses include:

```
Content-Security-Policy: default-src 'self'
```

Inline scripts and styles are blocked by default. The Batman dashboard and Blade templates load all assets from the same origin.

## Production checklist

- [ ] Set `APP_ENV=production` (disables GraphQL introspection and stack traces)
- [ ] Set `APP_ENCRYPTION` to a random string of ≥ 32 characters
- [ ] Set `BATMAN_SECRET` to a bcrypt hash (`password_hash()`)
- [ ] Set `APP_CORS_ORIGIN` to your exact front-end origin
- [ ] Run `php LazyMePHP schema:cache` after every schema change
- [ ] Point your web server so only `public/` is web-accessible
- [ ] Use HTTPS in production (required for `secure` session cookie flag)
