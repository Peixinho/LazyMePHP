<?php
/**
 * LazyMePHP SQLite Database Handler
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\DB;

use PDO;
use PDOException;
use PDOStatement;

/**
 * SQLite database handler using PDO (Singleton).
 */
final class SQLite extends ISQL
{
    private static ?self $instance = null;
    private string $configHash;

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @param string $dbName Database file path or ':memory:'
     * @param string $dbUser Database username (ignored for SQLite)
     * @param string $dbPassword Database password (ignored for SQLite)
     * @param string $dbHost Database host (ignored for SQLite)
     */
    private function __construct(string $dbName, string $dbUser = '', string $dbPassword = '', string $dbHost = '')
    {
        parent::__construct($dbName, $dbUser, $dbPassword, $dbHost); // Pass empty strings to ISQL for unused parameters
        $this->configHash = md5($dbName);
    }

    /**
     * Retrieves or creates the singleton instance.
     *
     * @param string $dbName Database file path or ':memory:'
     * @return self Singleton instance
     * @throws DatabaseException If instance exists with different configuration
     */
    public static function getInstance(string $dbName): self
    {
        $configHash = md5($dbName);
        if (self::$instance !== null && self::$instance->configHash !== $configHash) {
            throw new DatabaseException('SQLite instance already initialized with different configuration.');
        }

        if (self::$instance === null) {
            self::$instance = new self($dbName);
        }

        return self::$instance;
    }

    /**
     * Creates a new independent (non-singleton) connection.
     * Use this when you need to open a second SQLite database simultaneously.
     *
     * @param string $dbName Database file path or ':memory:'
     * @return self A fresh connection instance
     */
    public static function create(string $dbName): self
    {
        return new self($dbName);
    }

    /**
     * Resets the singleton instance (for testing purposes).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getConnection(): PDO
    {
        $this->connect();
        return $this->connection;
    }

    protected function createResult(string $query): IDBObject
    {
        return new SQLiteResult($query);
    }

    protected function slowQueryLabel(): string
    {
        return 'db_query_sqlite';
    }

    protected function connect(): void
    {
        if ($this->isConnected) {
            return;
        }

        try {
            $dsn = $this->dbName === ':memory:' ? 'sqlite::memory:' : "sqlite:{$this->dbName}";
            $this->connection = new PDO(
                $dsn,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            // Enable foreign key support
            $this->connection->exec('PRAGMA foreign_keys = ON;');
            $this->dbSelected = $this->dbName;
            $this->isConnected = true;
        } catch (PDOException $e) {
            throw new DatabaseException("Connection failed: {$e->getMessage()}", (int)$e->getCode(), $e);
        }
    }

    /**
     * Returns the ID of the last inserted row.
     *
     * @return int Last inserted ID
     * @throws DatabaseException If retrieval fails
     */
    public function getLastInsertedId(): int
    {
        $this->connect();
        return (int)$this->connection->lastInsertId();
    }

    /**
     * Generates a LIMIT clause for SQLite.
     *
     * @param int $end Number of rows to return
     * @param int $start Starting offset
     * @return string LIMIT clause
     */
    public function limit(int $end, int $start = 0): string
    {
        return "LIMIT $end OFFSET $start";
    }

    /**
     * Closes the database connection.
     */
    public function close(): void
    {
        if ($this->isConnected) {
            $this->connection = null;
            $this->isConnected = false;
            $this->dbSelected = null;
        }
    }
}

/**
 * SQLite query result handler.
 */
final class SQLiteResult extends IDBObject
{
    /**
     * Constructor.
     *
     * @param string $queryString SQL query string
     */
    public function __construct(string $queryString)
    {
        $this->queryString = $queryString;
    }

    /**
     * Sets the PDO statement for the result.
     *
     * @param PDOStatement $statement PDO statement
     */
    public function setStatement(PDOStatement $statement): void
    {
        $this->dbResult = $statement;
    }

    /**
     * Returns the query string.
     *
     * @return string Query string
     */
    public function getQueryString(): string
    {
        return $this->queryString;
    }

    /**
     * Returns the PDO statement.
     *
     * @return PDOStatement|null PDO statement
     */
    public function getStatement(): ?PDOStatement
    {
        return $this->dbResult;
    }

    /**
     * Fetches the next row as an object.
     *
     * @return object|null Row as stdClass or null if no more rows
     */
    public function fetchObject(): ?object
    {
        if (!$this->dbResult) {
            return null;
        }
        $result = $this->dbResult->fetch(PDO::FETCH_OBJ);
        return $result === false ? null : $result;
    }

    /**
     * Fetches the next row as an associative array.
     *
     * @return array|null Row as array or null if no more rows
     */
    public function fetchArray(): ?array
    {
        if (!$this->dbResult) {
            return null;
        }
        $result = $this->dbResult->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /**
     * Fetches all rows as an array of associative arrays.
     *
     * @return array Rows as arrays
     */
    public function fetchAll(): array
    {
        return $this->dbResult ? $this->dbResult->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Returns the number of rows affected or selected.
     *
     * @return int Row count
     */
    public function getRowCount(): int
    {
        return $this->dbResult ? $this->dbResult->rowCount() : 0;
    }

    /**
     * Returns the number of rows affected or selected (alias for getRowCount).
     *
     * @return int Row count
     */
    public function GetCount(): int
    {
        return $this->getRowCount();
    }
}
