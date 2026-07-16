<?php

use Core\LazyMePHP;
use Core\Helpers\ActivityLogger;
use Core\Helpers\PerformanceUtil;
use Core\Security\RateLimiter;
use Core\Auth\RBAC;
use Core\ErrorHandler;

beforeEach(function () {
    $_ENV['DB_TYPE'] = 'sqlite';
    $_ENV['DB_FILE_PATH'] = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'true';
    $_ENV['APP_ENV'] = 'testing';

    LazyMePHP::reset();
    new LazyMePHP();
});

afterEach(function () {
    LazyMePHP::reset();
});

function internalTableExists(string $table): bool
{
    $result = LazyMePHP::DB_CONNECTION()->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
        [$table]
    );
    return (bool) $result->fetchArray();
}

test('ActivityLogger creates __LOG_ACTIVITY and __LOG_DATA on first use', function () {
    ActivityLogger::logData('users', ['name' => ['old', 'new']], '1', 'UPDATE');
    ActivityLogger::logActivity();

    expect(internalTableExists('__LOG_ACTIVITY'))->toBeTrue();
    expect(internalTableExists('__LOG_DATA'))->toBeTrue();
});

test('ErrorHandler::ensureErrorsTable creates __LOG_ERRORS on first use', function () {
    ErrorHandler::ensureErrorsTable(LazyMePHP::DB_CONNECTION());

    expect(internalTableExists('__LOG_ERRORS'))->toBeTrue();
});

test('PerformanceUtil creates __LOG_PERFORMANCE on first use', function () {
    PerformanceUtil::logSlowOperation('test_op', 123.4);

    expect(internalTableExists('__LOG_PERFORMANCE'))->toBeTrue();
});

test('Security\RateLimiter creates __RATE_LIMITS on first use', function () {
    RateLimiter::isAllowed('test_action', 'test_id');

    expect(internalTableExists('__RATE_LIMITS'))->toBeTrue();
});

test('RBAC creates its three tables on first use', function () {
    RBAC::createRole('test_role', 'desc');

    expect(internalTableExists('__AUTH_ROLES'))->toBeTrue();
    expect(internalTableExists('__AUTH_ROLE_PERMISSIONS'))->toBeTrue();
    expect(internalTableExists('__AUTH_USER_ROLES'))->toBeTrue();
});
