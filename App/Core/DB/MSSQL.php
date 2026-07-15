<?php
/**
 * LazyMePHP MSSQL Database Handler
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\DB;

use PDO;
use PDOException;
use PDOStatement;

/**
 * MSSQL database handler using PDO (Singleton).
 */
final class MSSQL extends ISQL
{
    private static ?self $instance = null;
    private string $configHash;

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @param string $dbName Database name
     * @param string $dbUser Database username
     * @param string $dbPassword Database password
     * @param string $dbHost Database host
     */
    private function __construct(string $dbName, string $dbUser, string $dbPassword, string $dbHost)
    {
        parent::__construct($dbName, $dbUser, $dbPassword, $dbHost);
        $this->configHash = md5("{$dbName}:{$dbUser}:{$dbPassword}:{$dbHost}");
    }

    /**
     * Retrieves or creates the singleton instance.
     *
     * @param string $dbName Database name
     * @param string $dbUser Database username
     * @param string $dbPassword Database password
     * @param string $dbHost Database host
     * @return self Singleton instance
     * @throws DatabaseException If instance exists with different configuration
     */
    public static function getInstance(
        string $dbName,
        string $dbUser,
        string $dbPassword,
        string $dbHost
    ): self {
        $configHash = md5("{$dbName}:{$dbUser}:{$dbPassword}:{$dbHost}");
        if (self::$instance !== null && self::$instance->configHash !== $configHash) {
            throw new DatabaseException('MSSQL instance already initialized with different configuration.');
        }

        if (self::$instance === null) {
            self::$instance = new self($dbName, $dbUser, $dbPassword, $dbHost);
        }

        return self::$instance;
    }

    /**
     * Creates a new independent (non-singleton) connection.
     * Use this when you need to connect to a second MSSQL database simultaneously.
     *
     * @param string $dbName Database name
     * @param string $dbUser Database username
     * @param string $dbPassword Database password
     * @param string $dbHost Database host
     * @return self A fresh connection instance
     */
    public static function create(string $dbName, string $dbUser, string $dbPassword, string $dbHost): self
    {
        return new self($dbName, $dbUser, $dbPassword, $dbHost);
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
        return new MSSQLResult($query);
    }

    protected function slowQueryLabel(): string
    {
        return 'db_query_mssql';
    }

    protected function connect(): void
    {
        if ($this->isConnected) {
            return;
        }

        try {
            $this->connection = new PDO(
                "sqlsrv:Server={$this->dbHost};Database={$this->dbName}",
                $this->dbUsername,
                $this->dbPassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
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
        $stmt = $this->connection->query("SELECT SCOPE_IDENTITY() AS id");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['id'] ?? 0);
    }

    /**
     * Generates a LIMIT clause for MSSQL.
     *
     * @param int $end Number of rows to return
     * @param int $start Starting offset
     * @return string LIMIT clause
     */
    public function limit(int $end, int $start = 0): string
    {
        if ($start === 0) {
            return "TOP $end";
        }
        return "OFFSET $start ROWS FETCH NEXT $end ROWS ONLY";
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
 * MSSQL query result handler.
 */
final class MSSQLResult extends IDBObject
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
