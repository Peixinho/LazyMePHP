---
id: batman
title: Batman Dashboard
sidebar_position: 14
---

# Batman Dashboard

Batman is an internal developer dashboard, run standalone with `php LazyMePHP batman` (its own dev server, on its own port — never the same process as `php LazyMePHP serve`). It shows activity logs, error logs, performance metrics, and per-record change history with before/after diffs.

Batman is **not** reachable through the app's own dev server (`App/Tools/Webserver` only ever resolves requests against `public/` — nothing under `batman/`, or anywhere else in the project, is servable through it). Run the dashboard, use it, then stop it; it isn't meant to run continuously alongside the app.

## Setup

Generate a bcrypt password hash:

```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT), PHP_EOL;"
```

Add to `.env`:

```env
BATMAN_USERNAME=admin
BATMAN_SECRET=$2y$12$...   # paste the hash above
```

Batman authenticates against `BATMAN_SECRET` using `password_verify()` — it does not use database credentials or the `AUTH_TABLE` user table.

## What it shows

- **Activity log** — every INSERT, UPDATE, DELETE with timestamp and user
- **Error log** — application errors with severity and stack context
- **Performance metrics** — slow operations flagged by the monitoring layer
- **Record diffs** — before/after field values for every change, sensitive columns excluded

## GraphQL Explorer

The data API is a single `POST /graphql` endpoint whose schema is built at runtime from the DB schema (`Core\GraphQL\SchemaBuilder`) — there's no route file to grep for it, so `api-client.php` doesn't try to; it's GraphQL-only (an older REST-route regex scanner was removed once the app finished migrating to GraphQL).

- **Discover Schema** calls `discover-graphql.php`, which boots the app the same way `LazyMePHP::boot()` does and introspects the built schema in-process (not over HTTP) to list every query and mutation currently exposed, each as a clickable card that fills in a ready-to-run sample query and variables payload.
- **Log In** and **Send Query** go through `proxy.php` — Batman's own server-side PHP proxy — rather than the browser calling `{baseUrl}/auth/login` or `{baseUrl}/graphql` directly.

### Why a server-side proxy, not a direct browser fetch

Batman runs on its own dev server/port, always a different origin than the app it's testing. A direct browser `fetch()` from Batman's page to a different origin is subject to CORS — a restriction browsers place on *JavaScript*, not on servers. `proxy.php` makes Batman behave like Postman or curl instead: the browser only ever calls this same-origin endpoint; `proxy.php` itself (via cURL, server-to-server) makes the actual request to `{baseUrl}/auth/login` or `{baseUrl}/graphql`. CORS never enters into a server-to-server HTTP call, so this works against any reachable instance with zero configuration on the target app's side — no `APP_CORS_ORIGIN` needed for Batman's own use.

`Core\Http\CorsMiddleware` (`APP_CORS_ORIGIN` in `.env`) still exists and matters for a *real* browser-based frontend (an actual SPA) calling `/graphql` or `/auth/*` directly — that's a legitimate case where the calling JS truly runs in a browser at some fixed, known origin worth allowlisting. It just isn't what Batman needs.

## Security

- Batman login is separate from JWT auth — a different username/password
- The password is never stored in plain text — only the bcrypt hash goes in `.env`
- Batman routes are not exposed through the auto-router and are not listed in the OpenAPI spec
