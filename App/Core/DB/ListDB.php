<?php
/**
 * LazyMePHP Query Builder
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace DB;

use \Core\LazyMePHP;

/**
 * Query builder for generating SQL SELECT queries.
 */
final class ListDB
{
    private string $table;
    private string $fields = '*';
    private array $filters = [];
    private array $filterParams = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $orders = [];

    /**
     * Constructor.
     *
     * @param string $table Table name
     * @throws \InvalidArgumentException If table name is invalid
     */
    public function __construct(string $table)
    {
        if (!$this->isValidIdentifier($table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }
        $this->table = $table;
    }

    /**
     * Sets the order of the results.
     *
     * @param string $field Field to order by
     * @param string $direction Order direction (ASC or DESC)
     * @return self
     * @throws \InvalidArgumentException If field or direction is invalid
     */
    public function setOrder(string $field, string $direction = 'ASC'): self
    {
        if (!$this->isValidIdentifier($field)) {
            throw new \InvalidArgumentException('Invalid order field');
        }
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException('Invalid order direction');
        }
        $this->orders[] = "$field $direction";
        return $this;
    }

    /**
     * Adds a filter condition.
     *
     * @param string $field Field to filter
     * @param mixed $value Filter value
     * @param string $operator Comparison operator
     * @param string|null $logical Logical operator (AND or OR)
     * @return self
     * @throws \InvalidArgumentException If field or operator is invalid
     */
    public function addFilter(string $field, $value, string $operator = '=', ?string $logical = null): self
    {
        if (!$this->isValidIdentifier($field)) {
            throw new \InvalidArgumentException('Invalid filter field');
        }
        $operator = strtoupper($operator);
        if (!in_array($operator, ['=', '<', '>', '<=', '>=', '<>', 'LIKE'], true)) {
            throw new \InvalidArgumentException('Invalid filter operator');
        }
        $logical = $logical ? strtoupper($logical) : null;
        if ($logical && !in_array($logical, ['AND', 'OR'], true)) {
            throw new \InvalidArgumentException('Invalid logical operator');
        }

        $param = ":filter_" . count($this->filterParams);
        $this->filters[] = [
            'field' => $field,
            'operator' => $operator,
            'param' => $param,
            'logical' => $logical,
        ];
        $this->filterParams[$param] = $value;
        return $this;
    }

    /**
     * Sets the fields to select.
     *
     * @param string $fields Comma-separated field names
     * @return self
     * @throws \InvalidArgumentException If fields are invalid
     */
    public function setFields(string $fields): self
    {
        $fields = trim($fields);
        if (empty($fields)) {
            throw new \InvalidArgumentException('Fields cannot be empty');
        }
        // Basic validation: split and check identifiers
        $fieldArray = array_map('trim', explode(',', $fields));
        foreach ($fieldArray as $field) {
            // Allow aliases and simple expressions
            $parts = array_map('trim', explode(' AS ', $field));
            $mainField = $parts[0];
            if (!$this->isValidFieldExpression($mainField)) {
                throw new \InvalidArgumentException("Invalid field: $mainField");
            }
        }
        $this->fields = $fields;
        return $this;
    }

    /**
     * Clears all filters, limits, and orders.
     *
     * @return self
     */
    public function clearFilter(): self
    {
        $this->filters = [];
        $this->filterParams = [];
        $this->limit = null;
        $this->offset = null;
        $this->orders = [];
        return $this;
    }

    /**
     * Sets the limit and offset for the results.
     *
     * @param int $limit Number of rows to return
     * @param int $offset Starting offset
     * @return self
     * @throws \InvalidArgumentException If limit or offset is negative
     */
    public function setLimit(int $limit, int $offset = 0): self
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('Limit and offset must be non-negative');
        }
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Gets the generated results.
     *
     * @return array Array of associative arrays representing rows
     * @throws DatabaseException If query execution fails
     */
    public function getList(): array
    {
        $db = LazyMePHP::DB_CONNECTION();
        $query = $this->buildQuery();
        $params = $this->filterParams;

        if ($this->limit !== null && $this->offset !== null) {
            if ($db instanceof MSSQL) {
                // MSSQL requires ORDER BY for OFFSET...FETCH
                if (empty($this->orders)) {
                    $this->orders[] = '(SELECT NULL)';
                }
            }
            // Add limit and offset as parameters
            $params[':limit'] = $this->limit;
            $params[':offset'] = $this->offset;
        }

        try {
            $result = $db->query($query, $params);
            return $result->fetchAll();
        } catch (\PDOException $e) {
            throw new DatabaseException("Query failed: {$e->getMessage()}", (int)$e->getCode(), $e);
        }
    }

    /**
     * Builds the SQL query based on the current configuration.
     *
     * @return string SQL query
     */
    private function buildQuery(): string
    {
        $db = LazyMePHP::DB_CONNECTION();

        // Build WHERE clause
        $where = '';
        if (!empty($this->filters)) {
            $conditions = [];
            $group = [];
            $currentGroup = null;

            foreach ($this->filters as $filter) {
                $condition = "{$filter['field']} {$filter['operator']} {$filter['param']}";
                if ($filter['logical'] === 'AND' && $currentGroup !== null) {
                    $group[] = $condition;
                } else {
                    if (!empty($group)) {
                        $conditions[] = '(' . implode(' AND ', $group) . ')';
                        $group = [];
                    }
                    $conditions[] = $condition;
                    $currentGroup = $filter['logical'];
                }
            }

            if (!empty($group)) {
                $conditions[] = '(' . implode(' AND ', $group) . ')';
            }

            $where = 'WHERE ' . implode(' OR ', $conditions);
        }

        // Build ORDER BY clause
        $order = '';
        if (!empty($this->orders)) {
            $order = 'ORDER BY ' . implode(', ', $this->orders);
        }

        // Build LIMIT clause
        $limitClause = '';
        if ($this->limit !== null && $this->offset !== null) {
            if ($db instanceof MySQL) {
                $limitClause = 'LIMIT :limit OFFSET :offset';
            } elseif ($db instanceof SQLite) {
                $limitClause = 'LIMIT :limit OFFSET :offset';
            } elseif ($db instanceof MSSQL) {
                $limitClause = 'OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY';
            }
        }

        return "SELECT {$this->fields} FROM {$this->table} {$where} {$order} {$limitClause}";
    }

    /**
     * Validates a SQL identifier (table or field name).
     *
     * @param string $identifier Identifier to validate
     * @return bool True if valid
     */
    private function isValidIdentifier(string $identifier): bool
    {
        // Basic validation: alphanumeric, underscores, and dots (for table.field)
        return preg_match('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)?$/', $identifier) === 1;
    }

    /**
     * Validates a field expression (including aliases, functions).
     *
     * @param string $field Field expression
     * @return bool True if valid
     */
    private function isValidFieldExpression(string $field): bool
    {
        // Allow identifiers, functions (e.g., COUNT(*)), and simple expressions
        return preg_match('/^[a-zA-Z0-9_\*\(\)]+(\.[a-zA-Z0-9_]+)?$/', $field) === 1;
    }
}
