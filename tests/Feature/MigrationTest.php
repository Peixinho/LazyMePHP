<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Migration\Runner;

// Point Runner at a temporary directory for the duration of the tests
function migrationDir(): string
{
    return sys_get_temp_dir() . '/lazyme_migrations_test_' . getmypid();
}

// Reach into the private migrationsDir via reflection so tests can override it
function setMigrationDir(string $dir): void
{
    $ref = new ReflectionMethod(Runner::class, 'migrationsDir');
    // We override via a real directory; just create the files there.
    // Runner::migrationsDir() is private — we stub it by writing files to the
    // path it would return. For tests we intercept by extending Runner inline.
}

// Helper: write a migration file into the temp dir
function writeMigration(string $dir, string $filename, string $up, string $down = ''): void
{
    is_dir($dir) || mkdir($dir, 0755, true);
    $downCode = $down ? "function(\$db): void { \$db->query(\"$down\"); }" : 'null';
    file_put_contents("$dir/$filename", "<?php return ['up' => function(\$db): void { \$db->query(\"$up\"); }, 'down' => $downCode];");
}

// Testable subclass that overrides the migrations directory
class TestRunner extends Runner
{
    private static string $dir = '';

    public static function setDir(string $dir): void { self::$dir = $dir; }

    protected static function migrationsDir(): string { return self::$dir; }
}

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    $dir = migrationDir();
    if (is_dir($dir)) {
        array_map('unlink', glob("$dir/*.php") ?: []);
        rmdir($dir);
    }
    mkdir($dir, 0755, true);
    TestRunner::setDir($dir);
});

afterEach(function () {
    $dir = migrationDir();
    if (is_dir($dir)) {
        array_map('unlink', glob("$dir/*.php") ?: []);
        rmdir($dir);
    }
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('Migration Runner', function () {
    it('reports nothing to migrate when directory is empty', function () {
        ob_start();
        TestRunner::run();
        $output = ob_get_clean();
        expect($output)->toContain('Nothing to migrate');
    });

    it('runs a pending migration', function () {
        writeMigration(
            migrationDir(),
            '2026_01_01_0001_create_posts.php',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL)'
        );

        ob_start();
        TestRunner::run();
        ob_get_clean();

        // Table should now exist
        $db = LazyMePHP::DB_CONNECTION();
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
        expect($result->fetchArray()['name'])->toBe('posts');
    });

    it('records the migration in __migrations', function () {
        writeMigration(
            migrationDir(),
            '2026_01_01_0001_create_posts.php',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL)'
        );

        ob_start();
        TestRunner::run();
        ob_get_clean();

        $db     = LazyMePHP::DB_CONNECTION();
        $result = $db->query('SELECT migration, batch FROM "__migrations" WHERE migration = ?', ['2026_01_01_0001_create_posts.php']);
        $row    = $result->fetchArray();
        expect($row['migration'])->toBe('2026_01_01_0001_create_posts.php');
        expect((int)$row['batch'])->toBe(1);
    });

    it('does not re-run already ran migrations', function () {
        writeMigration(
            migrationDir(),
            '2026_01_01_0001_create_posts.php',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL)'
        );

        ob_start();
        TestRunner::run();
        TestRunner::run(); // second run should be a no-op
        $output = ob_get_clean();

        expect($output)->toContain('Nothing to migrate');
    });

    it('increments the batch number for each run', function () {
        writeMigration(migrationDir(), '2026_01_01_0001_a.php', 'CREATE TABLE aa (id INTEGER PRIMARY KEY)');
        ob_start(); TestRunner::run(); ob_get_clean();

        writeMigration(migrationDir(), '2026_01_01_0002_b.php', 'CREATE TABLE bb (id INTEGER PRIMARY KEY)');
        ob_start(); TestRunner::run(); ob_get_clean();

        $db = LazyMePHP::DB_CONNECTION();
        $r  = $db->query('SELECT migration, batch FROM "__migrations" ORDER BY batch, migration');
        $rows = [];
        while ($row = $r->fetchArray()) { $rows[] = $row; }

        expect((int)$rows[0]['batch'])->toBe(1);
        expect((int)$rows[1]['batch'])->toBe(2);
    });

    it('rolls back the last batch', function () {
        writeMigration(
            migrationDir(),
            '2026_01_01_0001_create_posts.php',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL)',
            'DROP TABLE IF EXISTS posts'
        );

        ob_start(); TestRunner::run(); ob_get_clean();
        ob_start(); TestRunner::rollback(); ob_get_clean();

        $db     = LazyMePHP::DB_CONNECTION();
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
        expect($result->fetchArray())->toBeNull();
    });

    it('shows pending and ran status', function () {
        writeMigration(migrationDir(), '2026_01_01_0001_a.php', 'CREATE TABLE aa (id INTEGER PRIMARY KEY)');
        writeMigration(migrationDir(), '2026_01_01_0002_b.php', 'CREATE TABLE bb (id INTEGER PRIMARY KEY)');

        ob_start(); TestRunner::run(); ob_get_clean(); // runs both

        writeMigration(migrationDir(), '2026_01_01_0003_c.php', 'CREATE TABLE cc (id INTEGER PRIMARY KEY)');

        ob_start();
        TestRunner::status();
        $output = ob_get_clean();

        expect($output)->toContain('[ran]');
        expect($output)->toContain('[pending]');
        expect($output)->toContain('2026_01_01_0003_c.php');
    });

    it('resets all migrations', function () {
        writeMigration(migrationDir(), '2026_01_01_0001_a.php', 'CREATE TABLE aa (id INTEGER PRIMARY KEY)', 'DROP TABLE IF EXISTS aa');
        writeMigration(migrationDir(), '2026_01_01_0002_b.php', 'CREATE TABLE bb (id INTEGER PRIMARY KEY)', 'DROP TABLE IF EXISTS bb');

        ob_start(); TestRunner::run(); ob_get_clean();
        ob_start(); TestRunner::reset(); ob_get_clean();

        $db = LazyMePHP::DB_CONNECTION();
        $r  = $db->query('SELECT COUNT(*) AS c FROM "__migrations"');
        expect((int)$r->fetchArray()['c'])->toBe(0);
    });

    it('scaffolds a new migration file', function () {
        $dir  = migrationDir();
        TestRunner::setDir($dir);

        $path = TestRunner::scaffold('create users table');
        expect(file_exists($dir . '/' . basename($path)))->toBeTrue();

        $content = file_get_contents($dir . '/' . basename($path));
        expect($content)->toContain("'up'");
        expect($content)->toContain("'down'");
    });
});
