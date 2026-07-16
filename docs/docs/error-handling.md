---
id: error-handling
title: Error Handling
sidebar_position: 20
---

# Error Handling

LazyMePHP installs error handling in two layers: a baseline handler that runs everywhere (CLI included), and a web/API-specific handler that takes over once a request actually reaches the router. Understanding the split matters if you're debugging why an error looks different than you expected.

## Layer 1 — the baseline handler (`Core\Helpers\ErrorUtil`)

`App/bootstrap.php` — the one file required by the CLI, the web front controller, and the API front controller alike — installs `Core\Helpers\ErrorUtil::ErrorHandler()` as the global `set_error_handler()`, plus a matching `set_exception_handler()` and a `register_shutdown_function()` for fatal errors that occur after the handler stack unwinds. For every error it:

- Assigns a UUID `error_id` you can hand to a user or find in a log.
- Writes a row to `__LOG_ERRORS` (severity, HTTP status, file/line, request context) when `APP_ACTIVITY_LOG=true` — see [Audit Logging](./audit-logging.md).
- Appends a line to `logs/errors.log` regardless of the activity-log setting.
- Renders `Core\Helpers\ErrorPage` for fatal errors and calls `die()`.
- **Emails `APP_SUPPORT_EMAIL`** for anything at `E_ERROR`/`E_PARSE`/`E_CORE_ERROR`/`E_COMPILE_ERROR`/`E_USER_ERROR`/`E_RECOVERABLE_ERROR` severity, with session/POST/GET dumped into the message body — sensitive-looking keys (`password`, `token`, `secret`, `key`, `auth`, `session_id`, `csrf`, `bearer`, `credential`, `private`, `signature`) are redacted first via `sanitizeData()`.

This layer runs for CLI scripts, cron jobs, and anything that only ever requires `App/bootstrap.php` directly.

## Layer 2 — the web/API handler (`Core\ErrorHandler`)

`Core\Http\Kernel::handle()` (the web front controller behind `public/index.php`) and `public/api/index.php` each install their **own** `set_error_handler()`/`SimpleRouter::error()` on top of the baseline one, backed by `Core\ErrorHandler::handleWebException()` / `::handleApiError()`. Because PHP's `set_error_handler()` only keeps the most recent registration, **this second handler fully replaces `ErrorUtil` for the rest of the request** — it renders the styled HTML error page (web) or a structured JSON error body (API) instead of `ErrorUtil`'s plain log-and-die, matching the response format the client actually expects.

The trade-off worth knowing: **`ErrorUtil`'s email-alert feature never fires on web or API requests**, only in contexts that never load a `Kernel` (CLI commands, `schedule:run`, queue workers). If you want email alerting for web-triggered errors too, hook it into `Core\ErrorHandler` directly rather than relying on `ErrorUtil`.

Both handlers still log to `__LOG_ERRORS` and `logs/errors.log` — only the *response shown to the client* and the *email alert* differ by layer.

## Debug mode

With `APP_DEBUG_MODE=true` (or `APP_ENV=development`):

- Full error reporting is enabled (`error_reporting(E_ALL)`, `display_errors=1`) instead of the production default (`E_ALL & ~E_DEPRECATED`, display off).
- `Core\Debug\DebugHelper`/`DebugToolbar` inject a debug toolbar into HTML responses via a shutdown function — it explicitly skips any response whose `Content-Type` is JSON, SSE, or plain text, so it never corrupts an API or streaming response.
- Error pages render with full file/line/trace detail instead of a generic message.

Never leave `APP_DEBUG_MODE=true` in production — besides leaking stack traces to end users, GraphQL introspection is also gated on the same flag (see [Security](./security.md)).

## Manual error reporting

`ErrorUtil::trigger_error($message, $type)` stashes a message in `$_SESSION['APP']['ERROR']['INTERNAL']` for display on the next request — a session-based equivalent to a flash message, predating the newer `Session::flash()` API (see [Session](./session.md)). Read it back with `ErrorUtil::GetErrors()` (echoes and clears it) or check `ErrorUtil::HasErrors()` first. Prefer `Session::flash()`/`old()`/`errors()` for anything new — this pair exists mainly for legacy call sites.

## Monitoring

`ErrorUtil::getErrorStats($dateFrom, $dateTo)` aggregates `__LOG_ERRORS` by error code with first/last occurrence timestamps — it backs the error panel on the [Batman dashboard](./batman.md), and can be called directly if you want the same data somewhere else (a scheduled digest email, a custom ops page).
