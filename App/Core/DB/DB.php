<?php
/**
 * LazyMePHP Multi-Database Connection Manager
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\DB;

use Core\LazyMePHP;

/**
 * Named connection registry and factory.
 *
 * Usage:
 *
 *   // Register a secondary connection (e.g. in bootstrap.php)
 *   DB::addConnection('analytics', [
 *       'driver'   => 'mysql',
 *       'database' => 'analytics_db',
 *       'username' => 'user',
 *       'password' => 'secret',
 *       'host'     => 'analytics-host',
 *   ]);
 *
 *   // Get the default connection (same as LazyMePHP::DB_CONNECTION())
 *   $conn = DB::connection();
 *
 *   // Get a named connection
 *   $conn = DB::connection('analytics');
 *
 *   // One-off connection without registering it
 *   $conn = DB::connect(['driver' => 'sqlite', 'database' => '/tmp/temp.sqlite']);
 */
class DB
{
    /** @var array<string, ISQL> */
    private static array $connections = [];

    /**
     * Register a named connection.
     *
     * Config keys:
     *   - driver   (string)  'mysql' | 'mssql' | 'sqlite'  (default: 'mysql')
     *   - database (string)  Database name or SQLite file path / ':memory:'
     *   - username (string)  DB username (not used for SQLite)
     *   - password (string)  DB password (not used for SQLite)
     *   - host     (string)  DB host     (not used for SQLite, default: 'localhost')
     *
     * @param string               $name   Logical name for this connection
     * @param array<string, mixed> $config Driver configuration
     */
    public static function addConnection(string $name, array $config): void
    {
        self::$connections[$name] = self::makeConnection($config);
    }

    /**
     * Retrieve a named connection.
     *
     * Passing 'default' (or no argument) returns the primary connection
     * managed by LazyMePHP::DB_CONNECTION() — fully backwards compatible.
     *
     * @param string $name Connection name (default: 'default')
     * @return ISQL
     * @throws DatabaseException If no connection is registered under $name
     */
    public static function connection(string $name = 'default'): ISQL
    {
        if ($name === 'default') {
            return LazyMePHP::DB_CONNECTION();
        }

        if (!isset(self::$connections[$name])) {
            throw new DatabaseException("No database connection registered with name '{$name}'.");
        }

        return self::$connections[$name];
    }

    /**
     * Create a one-off connection without adding it to the registry.
     *
     * @param array<string, mixed> $config Driver configuration (same keys as addConnection)
     * @return ISQL
     */
    public static function connect(array $config): ISQL
    {
        return self::makeConnection($config);
    }

    /**
     * Check whether a named connection has been registered.
     */
    public static function has(string $name): bool
    {
        return $name === 'default' || isset(self::$connections[$name]);
    }

    /**
     * Remove a named connection from the registry and close it.
     */
    public static function remove(string $name): void
    {
        if (isset(self::$connections[$name])) {
            self::$connections[$name]->close();
            unset(self::$connections[$name]);
        }
    }

    /**
     * Reset all registered connections (primarily for testing).
     */
    public static function reset(): void
    {
        foreach (self::$connections as $conn) {
            $conn->close();
        }
        self::$connections = [];
    }

    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $config */
    private static function makeConnection(array $config): ISQL
    {
        $driver   = strtolower((string)($config['driver']   ?? 'mysql'));
        $database = (string)($config['database'] ?? '');
        $username = (string)($config['username'] ?? '');
        $password = (string)($config['password'] ?? '');
        $host     = (string)($config['host']     ?? 'localhost');

        return match ($driver) {
            'sqlite' => SQLite::create($database),
            'mysql'  => MySQL::create($database, $username, $password, $host),
            'mssql'  => MSSQL::create($database, $username, $password, $host),
            default  => throw new DatabaseException("Unsupported database driver: '{$driver}'. Use 'sqlite', 'mysql', or 'mssql'."),
        };
    }
}
