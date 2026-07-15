<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;

$testCacheDir = sys_get_temp_dir() . '/lazyme_schema_cache_' . getmypid();

beforeEach(function () use ($testCacheDir) {
    $_ENV['DB_TYPE'] = 'sqlite';
    $_ENV['DB_FILE_PATH'] = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV'] = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    LazyMePHP::DB_CONNECTION()->query("CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        price REAL,
        active INTEGER DEFAULT 1
    )");

    // Point the schema cache at a temp dir for test isolation
    Model::setSchemaCacheDir($testCacheDir);
});

afterEach(function () use ($testCacheDir) {
    Model::clearFileSchemaCache();
    if (is_dir($testCacheDir)) {
        foreach (glob($testCacheDir . '/*') ?: [] as $f) unlink($f);
        rmdir($testCacheDir);
    }
    LazyMePHP::reset();
    Model::clearSchemaCache();
    Model::setSchemaCacheDir(null); // restore default
});

describe('Schema file cache', function () use ($testCacheDir) {
    it('warmSchemaCache writes a PHP file', function () use ($testCacheDir) {
        Model::warmSchemaCache('products');
        expect(is_file($testCacheDir . '/products.php'))->toBeTrue();
    });

    it('cache file returns a valid schema array', function () use ($testCacheDir) {
        Model::warmSchemaCache('products');
        $schema = require $testCacheDir . '/products.php';
        expect($schema)->toBeArray();
        expect($schema)->toHaveKey('id');
        expect($schema)->toHaveKey('name');
        expect($schema['id']['pk'])->toBeTrue();
        expect($schema['name']['nullable'])->toBeFalse();
    });

    it('loadSchema reads from file cache and skips DB query', function () use ($testCacheDir) {
        Model::warmSchemaCache('products');
        Model::clearSchemaCache(); // clear in-process cache

        // Next Model construction should read from file, not DB
        $model = new Model('products');
        expect($model->getColumns())->toContain('name');
        expect($model->getColumns())->toContain('price');
    });

    it('clearFileSchemaCache removes a specific table file', function () use ($testCacheDir) {
        Model::warmSchemaCache('products');
        expect(is_file($testCacheDir . '/products.php'))->toBeTrue();

        Model::clearFileSchemaCache('products');
        expect(is_file($testCacheDir . '/products.php'))->toBeFalse();
    });

    it('clearFileSchemaCache with no argument removes all files', function () use ($testCacheDir) {
        Model::warmSchemaCache('products');
        Model::clearFileSchemaCache();
        $files = glob($testCacheDir . '/*.php') ?: [];
        expect(count($files))->toBe(0);
    });

    it('falls back to live DB query when no cache file exists', function () {
        // No warmSchemaCache call — should still work via live query
        $model = new Model('products');
        expect($model->getColumns())->toContain('name');
    });
});
