<?php

declare(strict_types=1);

use Core\DB\DB;
use Core\DB\DatabaseException;
use Core\DB\SQLite;
use Core\LazyMePHP;

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    SQLite::resetInstance();
    DB::reset();
    new LazyMePHP();
});

afterEach(function () {
    DB::reset();
    LazyMePHP::reset();
    SQLite::resetInstance();
});

// ---------------------------------------------------------------------------

test('DB::connection() returns the default connection', function () {
    $conn = DB::connection();
    expect($conn)->toBeInstanceOf(\Core\DB\ISQL::class);
    expect($conn)->toBe(LazyMePHP::DB_CONNECTION());
});

test('DB::connection("default") is the same as DB::connection()', function () {
    expect(DB::connection('default'))->toBe(DB::connection());
});

test('DB::connect() creates an independent SQLite connection', function () {
    $conn = DB::connect([
        'driver'   => 'sqlite',
        'database' => ':memory:',
    ]);
    expect($conn)->toBeInstanceOf(SQLite::class);
    // Must be a different object from the singleton
    expect($conn)->not->toBe(LazyMePHP::DB_CONNECTION());
});

test('DB::connect() connection is usable', function () {
    $conn = DB::connect([
        'driver'   => 'sqlite',
        'database' => ':memory:',
    ]);
    $conn->query('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
    $conn->query('INSERT INTO t (v) VALUES (?)', ['hello']);
    $result = $conn->query('SELECT v FROM t');
    $row    = $result->fetchArray();
    expect($row['v'])->toBe('hello');
});

test('DB::addConnection() registers a named connection', function () {
    DB::addConnection('secondary', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
    ]);
    expect(DB::has('secondary'))->toBeTrue();
});

test('DB::connection("secondary") returns the registered connection', function () {
    DB::addConnection('secondary', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
    ]);
    $conn = DB::connection('secondary');
    expect($conn)->toBeInstanceOf(SQLite::class);
    expect($conn)->not->toBe(LazyMePHP::DB_CONNECTION());
});

test('secondary connection is independently usable', function () {
    DB::addConnection('secondary', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
    ]);

    $default   = DB::connection();
    $secondary = DB::connection('secondary');

    // Separate schemas
    $default->query('CREATE TABLE primary_data (id INTEGER PRIMARY KEY, val TEXT)');
    $secondary->query('CREATE TABLE secondary_data (id INTEGER PRIMARY KEY, val TEXT)');

    $default->query('INSERT INTO primary_data (val) VALUES (?)', ['from default']);
    $secondary->query('INSERT INTO secondary_data (val) VALUES (?)', ['from secondary']);

    $r1 = $default->query('SELECT val FROM primary_data')->fetchArray();
    $r2 = $secondary->query('SELECT val FROM secondary_data')->fetchArray();

    expect($r1['val'])->toBe('from default');
    expect($r2['val'])->toBe('from secondary');
});

test('DB::connection() throws for unknown connection names', function () {
    expect(fn() => DB::connection('nonexistent'))
        ->toThrow(DatabaseException::class, "No database connection registered with name 'nonexistent'.");
});

test('DB::has() returns false for unregistered names', function () {
    expect(DB::has('unknown'))->toBeFalse();
});

test('DB::has("default") is always true', function () {
    expect(DB::has('default'))->toBeTrue();
});

test('DB::remove() deregisters a named connection', function () {
    DB::addConnection('temp', ['driver' => 'sqlite', 'database' => ':memory:']);
    expect(DB::has('temp'))->toBeTrue();

    DB::remove('temp');
    expect(DB::has('temp'))->toBeFalse();
});

test('DB::connect() throws for an unsupported driver', function () {
    expect(fn() => DB::connect(['driver' => 'pgsql', 'database' => 'foo']))
        ->toThrow(DatabaseException::class, "Unsupported database driver: 'pgsql'.");
});

test('multiple SQLite::create() calls return independent instances', function () {
    $a = SQLite::create(':memory:');
    $b = SQLite::create(':memory:');

    expect($a)->not->toBe($b);

    $a->query('CREATE TABLE a_table (id INTEGER PRIMARY KEY)');
    // b_table must not exist on $a — expect a DatabaseException
    expect(fn() => $a->query('SELECT * FROM b_table'))->toThrow(DatabaseException::class);
});
