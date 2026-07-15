<?php

declare(strict_types=1);

use Core\Config;

beforeEach(function () {
    Config::flush();
});

afterEach(function () {
    Config::flush();
});

test('get() reads from $_ENV using dot-notation', function () {
    $_ENV['APP_ENV'] = 'testing';
    expect(Config::get('app.env'))->toBe('testing');
});

test('get() returns default when key is missing', function () {
    unset($_ENV['APP_MISSING_KEY']);
    expect(Config::get('app.missing.key', 'fallback'))->toBe('fallback');
});

test('get() returns null default when no default given', function () {
    unset($_ENV['TOTALLY_ABSENT']);
    expect(Config::get('totally.absent'))->toBeNull();
});

test('set() overrides ENV value', function () {
    $_ENV['APP_ENV'] = 'production';
    Config::set('app.env', 'staging');
    expect(Config::get('app.env'))->toBe('staging');
});

test('has() returns true when key is in ENV', function () {
    $_ENV['APP_NAME'] = 'LazyMePHP';
    expect(Config::has('app.name'))->toBeTrue();
});

test('has() returns false when key is absent', function () {
    unset($_ENV['NO_SUCH_KEY']);
    expect(Config::has('no.such.key'))->toBeFalse();
});

test('has() returns true for runtime overrides', function () {
    Config::set('runtime.only', 'value');
    expect(Config::has('runtime.only'))->toBeTrue();
});

test('bool() converts string true to true', function () {
    $_ENV['APP_DEBUG'] = 'true';
    expect(Config::bool('app.debug'))->toBeTrue();
});

test('bool() converts string false to false', function () {
    $_ENV['APP_DEBUG'] = 'false';
    expect(Config::bool('app.debug'))->toBeFalse();
});

test('bool() returns default when key is absent', function () {
    unset($_ENV['NO_KEY']);
    expect(Config::bool('no.key', true))->toBeTrue();
});

test('int() coerces string to integer', function () {
    $_ENV['MAIL_PORT'] = '587';
    expect(Config::int('mail.port'))->toBe(587);
});

test('int() returns default when key is absent', function () {
    unset($_ENV['ABSENT_INT']);
    expect(Config::int('absent.int', 42))->toBe(42);
});

test('float() coerces string to float', function () {
    $_ENV['SOME_FLOAT'] = '3.14';
    expect(Config::float('some.float'))->toBe(3.14);
});

test('flush() clears runtime overrides', function () {
    Config::set('temp.key', 'value');
    Config::flush();
    $_ENV['TEMP_KEY'] = 'env-value';
    expect(Config::get('temp.key'))->toBe('env-value');
});

test('all() returns ENV merged with overrides', function () {
    $_ENV['TEST_MERGE'] = 'env';
    Config::set('test.merge', 'override');
    $all = Config::all();
    expect($all['TEST_MERGE'])->toBe('env');
    expect($all['test.merge'])->toBe('override');
});
