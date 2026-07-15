---
id: audit-logging
title: Audit Logging
sidebar_position: 13
---

# Audit Logging

Enable the audit log to track every data mutation — who changed what, when, and what it looked like before and after.

## Setup

```env
APP_ACTIVITY_LOG=true
APP_ACTIVITY_AUTH=system    # fallback identifier when no JWT user is present
```

Run `php LazyMePHP migrate` to create the log tables.

## Log tables

| Table | Contents |
|---|---|
| `__LOG_ACTIVITY` | One row per mutating request (INSERT / UPDATE / DELETE) |
| `__LOG_DATA` | Per-field before/after values for every change |
| `__LOG_ERRORS` | Application errors with severity and context |
| `__LOG_PERFORMANCE` | Slow-operation metrics |

**Only mutating requests are logged.** Plain reads produce no audit entry.

## Sensitive column stripping

The following column names are automatically stripped from `__LOG_DATA` — their values are never written to the log:

- `password`
- `token`
- `secret`
- `api_key`
- `api_secret`
- The value of `AUTH_PASSWORD_COLUMN`

## User tracking

When a JWT Bearer token is present on the request, the authenticated user's ID is written to `__LOG_ACTIVITY.user`. When no token is present, `APP_ACTIVITY_AUTH` is used as the identifier (defaults to `system`).

## Batman dashboard

The audit log feeds directly into the [Batman dashboard](batman) at `/batman/`. It shows per-record change history with before/after diffs and lets you drill into any mutation that ever happened in the application.
