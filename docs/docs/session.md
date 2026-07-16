---
id: session
title: Session
sidebar_position: 17
---

# Session

`Core\Session\Session` wraps PHP's native session handling behind a small static facade, so you're not calling `$_SESSION` directly and forgetting to configure secure cookie flags.

## Why not just use `$_SESSION`?

You can — `Session` is a thin wrapper, not a replacement session engine. The reason to go through it:

- The session is started lazily, with secure defaults already applied (`httponly`, `samesite=Strict`, and `secure` whenever `APP_ENV` isn't `development`) — you never need to remember to call `session_start()` or set cookie params yourself.
- It adds **flash storage** (a value that survives exactly one redirect) on top of plain `$_SESSION`, which is what `old()` and `errors()` (see [Helpers](./helpers)) are built on.
- Static calls (`Session::get(...)`) proxy to a singleton instance via `__callStatic`, so there's one session per request regardless of how many places touch it.

## Basic usage

```php
use Core\Session\Session;

Session::put('user_id', $user->id);
$id = Session::get('user_id');
$id = Session::get('user_id', null); // with a default

Session::has('user_id');       // bool
Session::forget('user_id');    // remove one key
Session::pull('user_id');      // get + remove in one call
Session::all();                // everything in $_SESSION
Session::flush();              // clear the whole session
```

## Flash messages

A flash value is written now and readable exactly once — on the very next request — after which it's gone. This is the standard pattern for "show a message after a redirect":

```php
// Set it, then redirect (e.g. after a successful form POST)
Session::flash('success', 'Profile updated.');
back();

// On the page the redirect lands on:
$message = Session::getFlash('success'); // "Profile updated." — and now cleared
Session::hasFlash('success');            // false, already consumed
```

`old()` and `errors()` (documented in [Helpers](./helpers.md#old)) are convenience wrappers around `Session::flash('__old', ...)` / `Session::flash('__errors', ...)` — use those directly in Blade views instead of reaching for `Session::getFlash()` on form input/validation data.

## Login / session fixation

Call `regenerate()` right after a successful login to issue a fresh session ID — this prevents session fixation attacks where an attacker sets a known session ID before the victim authenticates:

```php
Session::regenerate();       // new ID, old session destroyed
Session::regenerate(false);  // new ID, keep the old session data file around
Session::getId();            // current session ID
```
