<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Migration\Runner;

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('Runner::fresh', function () {
    it('drops all user tables and re-runs migrations', function () {
        $db = LazyMePHP::DB_CONNECTION();

        // Create some tables directly
        $db->query("CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT)");
        $db->query("INSERT INTO products (name) VALUES ('Widget')");

        // Confirm table exists — use fetchAll() so the cursor is closed before fresh()
        $rows = $db->query("SELECT count(*) as cnt FROM products")->fetchAll();
        expect((int)($rows[0]['cnt'] ?? 0))->toBe(1);

        Runner::fresh();

        // products table should be gone after fresh
        $exists = false;
        try {
            $db->query("SELECT 1 FROM products LIMIT 1");
            $exists = true;
        } catch (\Throwable) {
            $exists = false;
        }
        expect($exists)->toBeFalse();
    });

    it('re-runs pending migrations after dropping', function () {
        // Use a temp migrations dir with one migration
        $tmpDir = sys_get_temp_dir() . '/lazyme_test_migrations_' . uniqid();
        mkdir($tmpDir);

        file_put_contents($tmpDir . '/2000_01_01_0001_create_fruit_table.php', <<<'PHP'
<?php
return [
    'up'   => fn($db) => $db->query("CREATE TABLE fruit (id INTEGER PRIMARY KEY, name TEXT)"),
    'down' => fn($db) => $db->query("DROP TABLE IF EXISTS fruit"),
];
PHP);

        // Point Runner at the temp dir
        Runner::setMigrationsDir($tmpDir);

        Runner::fresh();

        $db = LazyMePHP::DB_CONNECTION();
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='fruit'");
        expect($result->fetchArray()['name'] ?? null)->toBe('fruit');

        Runner::setMigrationsDir(null); // reset
        array_map('unlink', glob($tmpDir . '/*.php') ?: []);
        rmdir($tmpDir);
    });
});

describe('Runner::inferStub (scaffold naming conventions)', function () {
    it('generates CREATE TABLE stub for create_X_table names', function () {
        $stub = Runner::scaffoldStub('create_orders_table');
        expect($stub)->toContain('CREATE TABLE orders');
        expect($stub)->toContain("DROP TABLE IF EXISTS orders");
    });

    it('generates ALTER TABLE ADD COLUMN stub for add_X_to_Y names', function () {
        $stub = Runner::scaffoldStub('add_email_to_users');
        expect($stub)->toContain('ALTER TABLE users ADD COLUMN email');
    });

    it('generates DROP TABLE stub for drop_X_table names', function () {
        $stub = Runner::scaffoldStub('drop_sessions_table');
        expect($stub)->toContain("DROP TABLE IF EXISTS sessions");
    });

    it('generates RENAME stub for rename_X_to_Y names', function () {
        $stub = Runner::scaffoldStub('rename_posts_to_articles');
        expect($stub)->toContain('ALTER TABLE posts RENAME TO articles');
        expect($stub)->toContain('ALTER TABLE articles RENAME TO posts');
    });

    it('falls back to generic stub for unrecognised names', function () {
        $stub = Runner::scaffoldStub('my_custom_migration');
        expect($stub)->toContain('Write your SQL here');
    });
});
