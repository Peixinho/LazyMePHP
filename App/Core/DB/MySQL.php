<?php
/**
 * LazyMePHP MySQL Database Handler
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\DB;

use PDO;
use PDOException;
use PDOStatement;

/**
 * MySQL database handler using PDO (Singleton).
 */
final class MySQL extends ISQL
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
            throw new DatabaseException('MySQL instance already initialized with different configuration.');
        }

        if (self::$instance === null) {
            self::$instance = new self($dbName, $dbUser, $dbPassword, $dbHost);
        }

        return self::$instance;
    }

    /**
     * Resets the singleton instance (for testing purposes).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Gets the PDO connection.
     *
     * @return PDO PDO instance
     * @throws DatabaseException If connection fails
     */
    public function getConnection(): PDO
    {
        $this->connect();
        return $this->connection;
    }

    /**
     * Establishes a database connection.
     *
     * @throws DatabaseException If connection fails
     */
    protected function connect(): void
    {
        if ($this->isConnected) {
            return;
        }

        try {
            $this->connection = new PDO(
                "mysql:host={$this->dbHost};dbname={$this->dbName};charset=utf8mb4",
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
     * Executes a database query and returns a result object.
     *
     * @param string $query SQL query string
     * @param array<string|int, mixed> $params Query parameters (positional or named)
     * @return IDBObject Query result object
     * @throws DatabaseException If query execution fails or parameter count mismatches
     */
    public function query(string $query, array $params = []): IDBObject
    {
        $this->connect();
        $result = new MySQLResult($query);

        // Start timing for debug toolbar
        $startTime = microtime(true);

        try {
            $stmt = $this->connection->prepare($query);
            if ($stmt === false) {
                throw new DatabaseException("Failed to prepare query: $query");
            }

            // Validate parameter count
            $paramKeys = array_keys($params);
            $isPositional = !empty($paramKeys) && is_int($paramKeys[0]);
            if ($isPositional) {
                $placeholderCount = substr_count($query, '?');
                if (count($params) !== $placeholderCount) {
                    throw new DatabaseException(
                        "Parameter count mismatch: expected $placeholderCount positional placeholders (?), got " . count($params) . " parameters",
                        0,
                        null,
                        $query,
                        $params
                    );
                }
            } else {
                // Extract named placeholders (e.g., :description)
                preg_match_all('/:([a-zA-Z0-9_]+)/', $query, $matches);
                $placeholders = array_unique($matches[0]);
                $expectedCount = count($placeholders);
                $providedCount = count($params);
                if ($providedCount > 0 && $expectedCount === 0) {
                    throw new DatabaseException(
                        "No named placeholders found in query, but $providedCount parameters provided",
                        0,
                        null,
                        $query,
                        $params
                    );
                }
                if ($providedCount !== $expectedCount) {
                    throw new DatabaseException(
                        "Parameter count mismatch: expected $expectedCount named placeholders, got $providedCount parameters",
                        0,
                        null,
                        $query,
                        $params
                    );
                }
                // Verify each parameter exists in the query
                foreach ($paramKeys as $key) {
                    if (!in_array($key, $placeholders, true)) {
                        throw new DatabaseException(
                            "Parameter $key not found in query",
                            0,
                            null,
                            $query,
                            $params
                        );
                    }
                }
            }

            // Bind parameters
            foreach ($params as $key => $value) {
                $param = $isPositional ? ($key + 1) : $key;
                $type = \PDO::PARAM_STR;
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                    $type = \PDO::PARAM_INT;
                } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
                    $value = (int)$value;
                    $type = \PDO::PARAM_INT;
                } elseif (is_null($value)) {
                    $type = \PDO::PARAM_NULL;
                }
                $stmt->bindValue($param, $value, $type);
            }

            $stmt->execute();
            $result->setStatement($stmt);
            
            // Log query to debug toolbar (development only)
            $executionTime = microtime(true) - $startTime;
            if (class_exists('Core\Debug\DebugToolbar')) {
                \Core\Debug\DebugToolbar::getInstance()->addQuery($query, $executionTime, $params);
            }
            
        } catch (PDOException $e) {
            // Log query error to debug toolbar (development only)
            $executionTime = microtime(true) - $startTime;
            if (class_exists('Core\Debug\DebugToolbar')) {
                \Core\Debug\DebugToolbar::getInstance()->addError("Query failed: {$e->getMessage()}", __FILE__, __LINE__);
                \Core\Debug\DebugToolbar::getInstance()->addQuery($query, $executionTime, $params);
            }
            
            throw new DatabaseException(
                "Query failed: {$e->getMessage()}",
                (int)$e->getCode(),
                $e,
                $query,
                $params
            );
        }

        return $result;
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
     * Generates a LIMIT clause for MySQL.
     *
     * @param int $end Number of rows to return
     * @param int $start Starting offset
     * @return string LIMIT clause
     */
    public function limit(int $end, int $start = 0): string
    {
        return "LIMIT {$start}, {$end}";
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
 * MySQL query result handler.
 */
final class MySQLResult extends IDBObject
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
