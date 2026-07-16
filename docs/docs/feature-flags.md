---
id: feature-flags
title: Feature Flags
sidebar_position: 18
---

# Feature Flags

`Core\Features\Feature` gates code behind a named flag, so you can ship code disabled, turn it on for specific conditions, and remove the flag later without an `if` tangled through the codebase.

## Why

Two situations come up constantly: shipping a half-finished feature to production behind a switch, and rolling a change out to a subset of users (beta testers, one tenant, a percentage) before committing to it everywhere. Both are just "is this flag on for this request?" — `Feature` gives you one small API for that instead of ad-hoc env checks scattered across controllers.

## Defining flags

A flag needs no upfront registration — checking an undefined flag simply resolves to `false`. Define one when you want either a fixed value or dynamic logic:

```php
use Core\Features\Feature;

// Static value
Feature::define('dark-mode', true);

// Dynamic — evaluated on every check
Feature::define('new-billing', fn() => $currentUser->isBetaTester());
```

## Checking flags

```php
if (Feature::enabled('dark-mode')) {
    // ...
}

if (Feature::disabled('new-billing')) {
    // ...
}

Feature::when('new-billing', fn() => redirect('/new-billing'));
Feature::unless('maintenance', fn() => $this->handleRequest());
```

## Resolution order

For `Feature::enabled('new-billing')`, the first of these that applies wins:

1. A programmatic `Feature::define('new-billing', ...)` call.
2. The environment variable `APP_FEATURE_NEW_BILLING=true` (name uppercased, `-`/`.` replaced with `_`).
3. Otherwise: disabled.

This means you can ship a flag with no `define()` call at all — just document the `APP_FEATURE_*` env var — or use `define()` in `App/bootstrap.php`/a controller when the condition needs to be computed rather than statically set in `.env`.

```env
APP_FEATURE_DARK_MODE=true
```

## Testing and cleanup

```php
Feature::forget('new-billing'); // remove one programmatic definition
Feature::reset();               // remove all — useful in test teardown
Feature::all();                 // ['dark-mode' => true, ...] for every *programmatically defined* flag
```

Note `Feature::all()` only lists flags that were `define()`-d in this process — a flag purely controlled by an `APP_FEATURE_*` env var that was never `define()`-d won't appear, even though `enabled()` still resolves it correctly.
