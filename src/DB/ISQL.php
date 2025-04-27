<?php
/**
 * LazyMePHP Database Interface
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace LazyMePHP\DB;

/**
 * Abstract base class for database drivers.
 */
abstract class ISQL
{
    protected bool $isConnected = false;
    protected mixed $connection = null;
    protected ?string $dbSelected = null;
    protected string $dbUsername;
    protected string $dbPassword;
    protected string $dbHost;
    protected string $dbName;

    /**
     * Constructor.
     *
     * @param string $dbName Database name
     * @param string $dbUser Database username
     * @param string $dbPassword Database password
     * @param string $dbHost Database host
     */
    public function __construct(string $dbName, string $dbUser, string $dbPassword, string $dbHost)
    {
        $this->dbName = $dbName;
        $this->dbUsername = $dbUser;
        $this->dbPassword = $dbPassword;
        $this->dbHost = $dbHost;
    }

    /**
     * Establishes a database connection.
     *
     * @throws DatabaseException If connection fails
     */
    abstract protected function connect(): void;

    /**
     * Executes a database query and returns a result object.
     *
     * @param string $query SQL query string
     * @param array<string|int, mixed> $params Query parameters (positional or named)
     * @return IDBObject Query result object
     * @throws DatabaseException If query execution fails
     */
    abstract public function query(string $query, array $params = []): IDBObject;

    /**
     * Returns the ID of the last inserted row.
     *
     * @return int Last inserted ID
     * @throws DatabaseException If retrieval fails
     */
    abstract public function getLastInsertedId(): int;

    /**
     * Generates a LIMIT clause for the database.
     *
     * @param int $end Number of rows to return
     * @param int $start Starting offset
     * @return string LIMIT clause
     */
    abstract public function limit(int $end, int $start = 0): string;

    /**
     * Closes the database connection.
     */
    abstract public function close(): void;
}

/**
 * Abstract base class for database result objects.
 */
abstract class IDBObject
{
    protected string $queryString;
    protected mixed $dbResult = null;

    /**
     * Fetches the next row as an object.
     *
     * @return object|null Row as stdClass or null if no more rows
     */
    abstract public function fetchObject(): ?object;

    /**
     * Fetches the next row as an associative array.
     *
     * @return array|null Row as array or null if no more rows
     */
    abstract public function fetchArray(): ?array;

    /**
     * Returns the number of rows affected or selected.
     *
     * @return int Row count
     */
    abstract public function getRowCount(): int;
}

/**
 * Custom exception for database errors.
 */
class DatabaseException extends \Exception
{
    private ?string $query;
    private ?array $params;

    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $query = null,
        ?array $params = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->query = $query;
        $this->params = $params;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function getParams(): ?array
    {
        return $this->params;
    }
}?>
