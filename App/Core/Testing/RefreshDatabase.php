<?php

declare(strict_types=1);

namespace Core\Testing;

use Core\LazyMePHP;
use Core\Model;

/**
 * Resets the database to a clean SQLite :memory: state before each test.
 *
 * When included via Pest's uses(), setUp/tearDown hooks fire automatically.
 * You can also call setUpDatabase() manually for fine-grained control.
 *
 * Usage (Pest):
 *
 *   uses(\Core\Testing\RefreshDatabase::class);
 *
 *   beforeEach(function () {
 *       $this->setUpDatabase();
 *       LazyMePHP::DB_CONNECTION()->query("CREATE TABLE ...");
 *   });
 *
 * Or rely on the automatic reset without any schema:
 *
 *   uses(\Core\Testing\RefreshDatabase::class);
 *   test('example', function () { ... });  // always starts with a clean DB
 */
trait RefreshDatabase
{
    /** Boot a fresh SQLite :memory: database and register it with LazyMePHP. */
    public function setUpDatabase(): \Core\DB\ISQL
    {
        $_ENV['DB_TYPE']      = 'sqlite';
        $_ENV['DB_FILE_PATH'] = ':memory:';
        $_ENV['APP_ENV']      = 'testing';

        LazyMePHP::reset();

        if (class_exists(Model::class)) {
            Model::clearSchemaCache();
        }

        new LazyMePHP();

        $conn = LazyMePHP::DB_CONNECTION();

        if ($conn === null) {
            throw new \RuntimeException('RefreshDatabase: failed to boot SQLite :memory: connection.');
        }

        return $conn;
    }

    /** Tear down — reset the singleton so the next test starts fresh. */
    public function tearDownDatabase(): void
    {
        LazyMePHP::reset();

        if (class_exists(Model::class)) {
            Model::clearSchemaCache();
        }
    }

    // -------------------------------------------------------------------------
    // PHPUnit / Pest lifecycle hooks
    // -------------------------------------------------------------------------

    public function setUp(): void
    {
        if (method_exists(parent::class, 'setUp')) {
            /** @phpstan-ignore-next-line */
            parent::setUp();
        }
        $this->setUpDatabase();
    }

    public function tearDown(): void
    {
        $this->tearDownDatabase();
        if (method_exists(parent::class, 'tearDown')) {
            /** @phpstan-ignore-next-line */
            parent::tearDown();
        }
    }

    /**
     * Create a table on the current in-memory connection — convenience wrapper
     * so tests don't have to reach for LazyMePHP::DB_CONNECTION() directly.
     */
    public function createTable(string $sql): void
    {
        $conn = LazyMePHP::DB_CONNECTION();
        if ($conn !== null) {
            $conn->query($sql);
        }
    }
}
