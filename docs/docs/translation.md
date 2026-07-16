---
id: translation
title: Translation (i18n)
sidebar_position: 19
---

# Translation (i18n)

`Core\Translation\Translator` is a minimal i18n layer: plain PHP arrays as translation files, dot-notation keys, `:placeholder` substitution, and a locale fallback — no compiled catalogs, no external dependency.

## Translation files

Each locale/group pair is one file returning an array, under `lang/{locale}/{group}.php`:

```php
// lang/en/messages.php
return [
    'welcome' => 'Welcome, :name!',
    'user' => [
        'greeting' => 'Hello, :name!',
    ],
];
```

The framework ships `lang/en/auth.php`, `lang/en/messages.php`, and `lang/en/validation.php` out of the box (used by JWT auth failures and model/`FormRequest` validation messages) — add more groups or locales by creating more files under `lang/`.

## Usage

The global `__()` helper (see [Helpers](./helpers.md)) is the everyday entry point:

```php
__('auth.failed')                            // "These credentials do not match."
__('messages.welcome', ['name' => 'Alice'])  // "Welcome, Alice!"
__('messages.user.greeting', ['name' => 'Alice']) // nested keys — "Hello, Alice!"
```

`__()` is a thin wrapper around `Translator::trans()` — reach for the class directly when you need to force a specific locale or check existence:

```php
use Core\Translation\Translator;

Translator::setLocale('pt');
Translator::trans('auth.failed');             // looks up lang/pt/auth.php
Translator::trans('auth.failed', [], 'en');   // force English for this one call

Translator::has('messages.welcome');          // true/false, current locale
```

## Fallback and missing keys

If a key isn't found in the active locale, `Translator` retries against the fallback locale (`en` by default, `Translator::setFallback('fr')` to change it). If it's still not found, `trans()` returns the **key itself** rather than throwing — so a missing translation shows up as `messages.welcome` in the UI instead of a fatal error, which is easy to spot during development without breaking the page in production.

## Placeholders

`:name` placeholders are replaced case-sensitively in three forms, so the same translation can be reused in a sentence regardless of capitalization:

```php
// lang/en/messages.php: 'greeting' => 'Hi :name, :Name said :NAME'
__('messages.greeting', ['name' => 'alice'])
// "Hi alice, Alice said ALICE"
```

## Testing

`Translator::flush()` clears the in-memory cache of loaded translation files — call it in test teardown if a test swaps `lang/` files or locales and later tests shouldn't see stale, previously-loaded translations.
