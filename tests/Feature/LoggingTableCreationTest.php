<?php

use Core\LazyMePHP;

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

test('internal logging/rate-limit/RBAC migrations create the expected tables on sqlite', function () {
    $files = glob(__DIR__ . '/../../database/migrations/2026_07_16_*.php');
    expect($files)->not->toBeEmpty();
    sort($files);

    foreach ($files as $file) {
        $migration = require $file;
        ($migration['up'])(LazyMePHP::DB_CONNECTION());
    }

    $expected = [
        '__LOG_ACTIVITY',
        '__LOG_DATA',
        '__LOG_ERRORS',
        '__LOG_PERFORMANCE',
        '__RATE_LIMITS',
        '__AUTH_ROLES',
        '__AUTH_ROLE_PERMISSIONS',
        '__AUTH_USER_ROLES',
    ];

    foreach ($expected as $table) {
        expect(internalTableExists($table))->toBeTrue("Table $table should exist");
    }
});

test('down migrations drop the tables they create', function () {
    $files = glob(__DIR__ . '/../../database/migrations/2026_07_16_*.php');
    sort($files);

    foreach ($files as $file) {
        $migration = require $file;
        ($migration['up'])(LazyMePHP::DB_CONNECTION());
    }
    foreach (array_reverse($files) as $file) {
        $migration = require $file;
        ($migration['down'])(LazyMePHP::DB_CONNECTION());
    }

    expect(internalTableExists('__LOG_ACTIVITY'))->toBeFalse();
    expect(internalTableExists('__AUTH_ROLES'))->toBeFalse();
});
