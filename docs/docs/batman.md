---
id: batman
title: Batman Dashboard
sidebar_position: 14
---

# Batman Dashboard

Batman is an internal developer dashboard available at `/batman/`. It shows activity logs, error logs, performance metrics, and per-record change history with before/after diffs.

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

## API Explorer

The data API is a single `POST /graphql` endpoint whose schema is built at runtime from the DB schema (`Core\GraphQL\SchemaBuilder`) — there's no route file to grep for it. `api-client.php` has a **GraphQL API** panel that:

- Calls `discover-graphql.php`, which boots the app the same way `LazyMePHP::boot()` does and introspects the built schema in-process (not over HTTP) to list every query and mutation currently exposed, each with a ready-to-run sample query and variables payload.
- Sends `POST {baseUrl}/graphql` with `{ query, variables }`, and an optional Bearer token field for instances with `AUTH_TABLE` configured (the endpoint is behind `JwtMiddleware` in that case).

The **Legacy Route Discovery** section below it only regex-scans a directory (default `App/Routes`) for `SimpleRouter::get()` / `@route` style definitions — it predates the GraphQL migration and won't find anything under the old `App/Api` path since that directory no longer exists.

## Security

- Batman login is separate from JWT auth — a different username/password
- The password is never stored in plain text — only the bcrypt hash goes in `.env`
- Batman routes are not exposed through the auto-router and are not listed in the OpenAPI spec
