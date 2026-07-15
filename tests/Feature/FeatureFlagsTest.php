<?php

declare(strict_types=1);

use Core\Features\Feature;

beforeEach(fn() => Feature::reset());
afterEach(fn() => Feature::reset());

// ---------------------------------------------------------------------------
// Programmatic definitions
// ---------------------------------------------------------------------------

test('define and enabled', function () {
    Feature::define('dark-mode', true);
    expect(Feature::enabled('dark-mode'))->toBeTrue();
    expect(Feature::disabled('dark-mode'))->toBeFalse();
});

test('define false disables feature', function () {
    Feature::define('beta', false);
    expect(Feature::enabled('beta'))->toBeFalse();
});

test('define with callable', function () {
    Feature::define('premium', fn() => true);
    expect(Feature::enabled('premium'))->toBeTrue();
});

test('define with callable returning false', function () {
    Feature::define('early-access', fn() => false);
    expect(Feature::disabled('early-access'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Env-based flags
// ---------------------------------------------------------------------------

test('enabled reads APP_FEATURE_{NAME} env', function () {
    $_ENV['APP_FEATURE_CHAT'] = 'true';
    expect(Feature::enabled('chat'))->toBeTrue();
    unset($_ENV['APP_FEATURE_CHAT']);
});

test('disabled when env is false', function () {
    $_ENV['APP_FEATURE_NEWUI'] = 'false';
    expect(Feature::disabled('newui'))->toBeTrue();
    unset($_ENV['APP_FEATURE_NEWUI']);
});

test('env key normalises hyphens to underscores', function () {
    $_ENV['APP_FEATURE_DARK_MODE'] = '1';
    expect(Feature::enabled('dark-mode'))->toBeTrue();
    unset($_ENV['APP_FEATURE_DARK_MODE']);
});

// ---------------------------------------------------------------------------
// when / unless
// ---------------------------------------------------------------------------

test('when executes callback when enabled', function () {
    Feature::define('foo', true);
    $called = false;
    Feature::when('foo', function () use (&$called) { $called = true; });
    expect($called)->toBeTrue();
});

test('when does not execute when disabled', function () {
    Feature::define('foo', false);
    $called = false;
    Feature::when('foo', function () use (&$called) { $called = true; });
    expect($called)->toBeFalse();
});

test('unless executes callback when disabled', function () {
    Feature::define('bar', false);
    $called = false;
    Feature::unless('bar', function () use (&$called) { $called = true; });
    expect($called)->toBeTrue();
});

test('unless does not execute when enabled', function () {
    Feature::define('bar', true);
    $called = false;
    Feature::unless('bar', function () use (&$called) { $called = true; });
    expect($called)->toBeFalse();
});

// ---------------------------------------------------------------------------
// forget / all
// ---------------------------------------------------------------------------

test('forget removes a definition', function () {
    Feature::define('temp', true);
    Feature::forget('temp');
    expect(Feature::all())->not->toHaveKey('temp');
});

test('all returns defined features', function () {
    Feature::define('a', true);
    Feature::define('b', false);
    expect(Feature::all())->toHaveKey('a');
    expect(Feature::all())->toHaveKey('b');
});

// ---------------------------------------------------------------------------
// Undefined feature
// ---------------------------------------------------------------------------

test('undefined feature defaults to disabled', function () {
    expect(Feature::enabled('nonexistent'))->toBeFalse();
});
