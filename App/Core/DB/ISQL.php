<?php
/**
 * LazyMePHP Database Interface
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\DB;

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

    public function __construct(string $dbName, string $dbUser, string $dbPassword, string $dbHost)
    {
        $this->dbName = $dbName;
        $this->dbUsername = $dbUser;
        $this->dbPassword = $dbPassword;
        $this->dbHost = $dbHost;
    }

    abstract protected function connect(): void;

    /** Create a driver-specific empty result object. */
    abstract protected function createResult(string $query): IDBObject;

    /** Label used for slow-query performance logging. */
    abstract protected function slowQueryLabel(): string;

    abstract public function getLastInsertedId(): int;

    abstract public function limit(int $end, int $start = 0): string;

    abstract public function close(): void;

    /**
     * Executes a parameterised query and returns a result object.
     *
     * @param string $query SQL query string
     * @param array<string|int, mixed> $params Positional (?) or named (:key) parameters
     * @return IDBObject
     * @throws DatabaseException
     */
    public function query(string $query, array $params = []): IDBObject
    {
        $this->connect();
        $result = $this->createResult($query);
        $startTime = microtime(true);

        try {
            $stmt = $this->connection->prepare($query);
            if ($stmt === false) {
                throw new DatabaseException("Failed to prepare query: $query");
            }

            $paramKeys = array_keys($params);
            $isPositional = !empty($paramKeys) && is_int($paramKeys[0]);

            if ($isPositional) {
                $placeholderCount = substr_count($query, '?');
                if (count($params) !== $placeholderCount) {
                    throw new DatabaseException(
                        "Parameter count mismatch: expected $placeholderCount positional placeholders (?), got " . count($params) . " parameters",
                        0, null, $query, $params
                    );
                }
            } else {
                preg_match_all('/:([a-zA-Z0-9_]+)/', $query, $matches);
                $placeholders = array_unique($matches[0]);
                $expectedCount = count($placeholders);
                $providedCount = count($params);
                if ($providedCount > 0 && $expectedCount === 0) {
                    throw new DatabaseException(
                        "No named placeholders found in query, but $providedCount parameters provided",
                        0, null, $query, $params
                    );
                }
                if ($providedCount !== $expectedCount) {
                    throw new DatabaseException(
                        "Parameter count mismatch: expected $expectedCount named placeholders, got $providedCount parameters",
                        0, null, $query, $params
                    );
                }
                foreach ($paramKeys as $key) {
                    if (!in_array($key, $placeholders, true)) {
                        throw new DatabaseException("Parameter $key not found in query", 0, null, $query, $params);
                    }
                }
            }

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

            $executionTime = microtime(true) - $startTime;
            if (class_exists('Core\Debug\DebugToolbar')) {
                \Core\Debug\DebugToolbar::getInstance()->addQuery($query, $executionTime, $params);
            }
            if (class_exists('Core\Helpers\PerformanceUtil') && $executionTime > 1.0) {
                \Core\Helpers\PerformanceUtil::logSlowOperation($this->slowQueryLabel(), [
                    'duration_ms' => $executionTime * 1000,
                    'memory_bytes' => memory_get_usage(true),
                    'memory_mb' => memory_get_usage(true) / 1024 / 1024,
                ]);
            }
        } catch (\PDOException $e) {
            $executionTime = microtime(true) - $startTime;
            if (class_exists('Core\Debug\DebugToolbar')) {
                \Core\Debug\DebugToolbar::getInstance()->addQuery($query, $executionTime, $params);
            }
            throw new DatabaseException("Query failed: {$e->getMessage()}", (int)$e->getCode(), $e, $query, $params);
        }

        return $result;
    }
}

/**
 * Abstract base class for database result objects.
 */
abstract class IDBObject
{
    protected string $queryString;
    protected mixed $dbResult = null;

    abstract public function setStatement(\PDOStatement $statement): void;
    abstract public function fetchObject(): ?object;
    abstract public function fetchArray(): ?array;
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
}
