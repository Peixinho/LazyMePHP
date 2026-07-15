<?php

declare(strict_types=1);

use Core\Container\Container;

// ---------------------------------------------------------------------------
// config() / config_set()
// ---------------------------------------------------------------------------

test('config() reads env values', function () {
    $_ENV['TEST_KEY'] = 'hello';
    expect(config('TEST_KEY'))->toBe('hello');
});

test('config() returns default when key missing', function () {
    unset($_ENV['MISSING_KEY_XYZ']);
    expect(config('MISSING_KEY_XYZ', 'fallback'))->toBe('fallback');
});

// ---------------------------------------------------------------------------
// app()
// ---------------------------------------------------------------------------

test('app() returns the Container when called without args', function () {
    $container = app();
    expect($container)->toBeInstanceOf(Container::class);
});

test('app() resolves a class from the container', function () {
    $container = Container::getInstance();
    $container->bind('TestService', fn() => new stdClass());

    $service = app('TestService');
    expect($service)->toBeInstanceOf(stdClass::class);
});

// ---------------------------------------------------------------------------
// abort()
// ---------------------------------------------------------------------------

test('abort() throws HttpException', function () {
    expect(fn() => abort(404))->toThrow(\Core\Http\HttpException::class);
});

test('abort() passes the status code', function () {
    try {
        abort(403, 'Forbidden');
    } catch (\Core\Http\HttpException $e) {
        expect($e->getCode())->toBe(403);
        expect($e->getMessage())->toContain('403');
        expect(true)->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// now()
// ---------------------------------------------------------------------------

test('now() returns a DateTimeImmutable', function () {
    $dt = now();
    expect($dt)->toBeInstanceOf(DateTimeImmutable::class);
});

test('now() with timezone', function () {
    $dt = now('UTC');
    expect($dt->getTimezone()->getName())->toBe('UTC');
});

// ---------------------------------------------------------------------------
// url()
// ---------------------------------------------------------------------------

test('url() returns the base URL', function () {
    $_SERVER['HTTP_HOST']  = 'example.com';
    $_SERVER['HTTPS']      = 'off';
    expect(url())->toBe('http://example.com');
});

test('url() appends a path', function () {
    $_SERVER['HTTP_HOST']  = 'example.com';
    $_SERVER['HTTPS']      = 'off';
    expect(url('/users'))->toBe('http://example.com/users');
    expect(url('users'))->toBe('http://example.com/users');
});

// ---------------------------------------------------------------------------
// old()
// ---------------------------------------------------------------------------

test('old() returns null when session data absent', function () {
    if (session_status() === PHP_SESSION_NONE) session_start();
    unset($_SESSION['__old']);
    expect(old('name'))->toBeNull();
    expect(old('name', 'Alice'))->toBe('Alice');
});

test('old() returns flashed value from session', function () {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['__old'] = ['email' => 'user@example.com'];
    expect(old('email'))->toBe('user@example.com');
    expect(old('missing', 'default'))->toBe('default');
    unset($_SESSION['__old']);
});

// ---------------------------------------------------------------------------
// errors()
// ---------------------------------------------------------------------------

test('errors() returns empty array when no errors', function () {
    if (session_status() === PHP_SESSION_NONE) session_start();
    unset($_SESSION['__errors']);
    expect(errors())->toBe([]);
    expect(errors('email'))->toBeNull();
});

test('errors() returns first error message for a field', function () {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['__errors'] = ['email' => ['Email is required.', 'Must be valid.']];
    expect(errors('email'))->toBe('Email is required.');
    expect(errors())->toHaveKey('email');
    unset($_SESSION['__errors']);
});

// ---------------------------------------------------------------------------
// str() proxy
// ---------------------------------------------------------------------------

test('str() returns a StrProxy', function () {
    expect(str('hello world')->slug())->toBe('hello-world');
    expect(str('helloWorld')->snake())->toBe('hello_world');
});

test('str() without arg can call static-like methods', function () {
    $uuid = str()->uuid();
    expect(\Core\Str::isUuid($uuid))->toBeTrue();
});

// ---------------------------------------------------------------------------
// arr() proxy
// ---------------------------------------------------------------------------

test('arr() returns an ArrProxy', function () {
    $result = arr(['a.b' => 1, 'a.c' => 2])->undot()->all();
    expect($result)->toBe(['a' => ['b' => 1, 'c' => 2]]);
});

test('arr() chains multiple transformations', function () {
    $result = arr([1, 2, 3, 4, 5])
        ->where(fn($v) => $v > 2)
        ->values()
        ->all();
    expect($result)->toBe([3, 4, 5]);
});
