<?php

declare(strict_types=1);

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
 */

namespace Core\DB;
use \Core\LazyMePHP;
use \Models;

/**
 * Operator enum
 */
enum Operator:string {
  case Equal = '=';
  case LessEqual = '<=';
  case Less = '<';
  case Greater = '>';
  case GreaterEqual = '>=';
  case Different = '!=';
  case In = 'IN';
  case NotIn = 'NOT IN';
  case IsNull = 'IS NULL';
}

/**
 * Query builder for generating SQL SELECT queries with support for joins, where clauses, and ordering.
 *
 * @package Core\DB
 * 
 * @example
 * // Simple query with model hydration
 * $results = (new Select())
 *     ->From('Utilizadores')
 *     ->Where('Utilizadores', 'id', Operator::Equal, 1)
 *     ->Fetch();
 * // Returns: [['table' => 'Utilizadores', 'object' => Utilizadores]]
 * 
 * @example
 * // Join query with automatic field aliasing and model hydration
 * $results = (new Select())
 *     ->From('Utilizadores')
 *     ->Join('Utilizadores', 'Unidades', 'id_unidade', 'id')
 *     ->Where('Utilizadores', 'mecanografico', Operator::Greater, 10000)
 *     ->Order('Utilizadores', 'id', 'ASC')
 *     ->Fetch();
 * // Returns: [['table' => 'Utilizadores', 'object' => Utilizadores], ['table' => 'Unidades', 'object' => Unidades]]
 * 
 * @example
 * // Complex query with selected fields, joins, and aggregates
 * $results = (new Select())
 *     ->From('Utilizadores')
 *     ->SelectFields('Utilizadores', ['id', 'mecanografico'])
 *     ->Join('Utilizadores', 'Unidades', 'id_unidade', 'id')
 *     ->SelectFields('Unidades', ['unidade'])
 *     ->AddSelectExpression('COUNT(*) as total')
 *     ->GroupBy('Utilizadores', 'id_unidade')
 *     ->Having('Utilizadores', 'id_unidade', Operator::Greater, 1)
 *     ->Fetch();
 * // Returns: raw associative arrays due to aggregate expressions
 */
class Select {

  private string $queryTables;
  private string $queryFields;
  private string $queryWhere;
  private bool $hasWhereCondition;
  private string $queryOrder;
  /**
   * @var array<int, array{table: string, alias: string, fields: list<string>}> 
   */
  private array $tables;
  private string $tableAliases;
  /**
   * @var array<int, mixed> 
   */
  private array $queryParams;
  private string $queryGroupBy;
  private string $queryHaving;
  /**
   * @var array{limit: int, offset: int}|null
   */
  private ?array $queryLimit;
  /**
   * @var array<int, array{table: string, field: string}> 
   */
  private array $selectedFields = [];
  /**
   * @var list<string> 
   */
  private array $selectExpressions = [];
  private string $dbDriver = 'mysql'; // default, can be set to 'mysql', 'mssql', 'sqlite'

  public function __construct() {
    $this->queryTables = '';
    $this->queryFields = '';
    $this->queryWhere = '';
    $this->hasWhereCondition = false;
    $this->queryOrder = '';
    $this->tables = array();
    $this->tableAliases = 'A';
    $this->queryParams = [];
    $this->queryGroupBy = '';
    $this->queryHaving = '';
    $this->queryLimit = null;
  }

  public function setDbDriver(string $driver): self {
    $this->dbDriver = strtolower($driver);
    return $this;
  }
  public function getDbDriver(): string {
    return $this->dbDriver;
  }

  private function quoteIdentifier(string $name): string {
    switch ($this->dbDriver) {
      case 'mysql':
        return "`$name`";
      case 'mssql':
        return "[$name]";
      case 'sqlite':
        return '"' . str_replace('"', '""', $name) . '"';
      default:
        return $name;
    }
  }

  /**
   * @param list<string> $fields
   */
  public function SelectFields(string $table, array $fields): self {
    $alias = $this->getTableAlias($table);
    foreach ($fields as $field) {
      $this->selectedFields[] = ["table" => $table, "field" => $field];
    }
    return $this;
  }

  public function GroupBy(string $table, string $field): self {
    $alias = $this->getTableAlias($table);
    if ($alias === null) {
        throw new \InvalidArgumentException("Table '$table' not found in query.");
    }
    $this->queryGroupBy .= (!empty($this->queryGroupBy)?",":"") . $this->quoteIdentifier($alias) . "." . $this->quoteIdentifier($field);
    return $this;
  }

  /**
   * @param mixed $value
   */
  public function Having(string $table, string $field, Operator $operator, $value = null, string $aggregator = "AND"): self {
    $alias = $this->getTableAlias($table);
    if ($alias === null) {
        throw new \InvalidArgumentException("Table '$table' not found in query.");
    }
    $fragment = '';
    switch ($operator) {
      case Operator::In:
      case Operator::NotIn:
        if (!is_array($value)) {
          // Single value, use = or !=
          $op = $operator === Operator::In ? '=' : '!=';
          $fragment = ($this->queryHaving ? " $aggregator " : "") . $this->quoteIdentifier($alias) . "." . $this->quoteIdentifier($field) . " $op ?";
          $this->queryHaving .= $fragment;
          $this->queryParams[] = $value;
        } else {
          if (empty($value)) {
            throw new \InvalidArgumentException('Value for IN/NOT IN must be a non-empty array');
          }
          $placeholders = implode(', ', array_fill(0, count($value), '?'));
          $op = $operator === Operator::In ? 'IN' : 'NOT IN';
          $fragment = ($this->queryHaving ? " $aggregator " : "") . $this->quoteIdentifier($alias) . "." . $this->quoteIdentifier($field) . " $op ($placeholders)";
          $this->queryHaving .= $fragment;
          foreach ($value as $v) {
            $this->queryParams[] = $v;
          }
        }
        break;
      default:
        $fragment = ($this->queryHaving ? " $aggregator " : "") . $this->quoteIdentifier($alias) . "." . $this->quoteIdentifier($field) . " {$operator->value} ?";
        $this->queryHaving .= $fragment;
        $this->queryParams[] = $value;
        break;
    }
    return $this;
  }

  public function Limit(int $limit, int $offset = 0): self {
    $this->queryLimit = [
        'limit' => $limit,
        'offset' => $offset,
    ];
    return $this;
  }

   /**
   * Sets the main table for the query.
   *
   * @param string $table Table name
   * @return self
   */
  public function From(string $table): self {
    $this->getFields($table, $this->tableAliases);
    $this->queryTables .= (!empty($this->queryTables) ? ", " : "") . $this->quoteIdentifier($table) . " " . $this->quoteIdentifier($this->tableAliases);
    $this->tableAliases++;
    return $this;
 }

   /**
   * Adds a LEFT JOIN to the query.
   *
   * @param string $table1 Left table name
   * @param string $table2 Right table name
   * @param string $field1 Field from left table
   * @param string $field2 Field from right table
   * @param Operator $operator Join operator (default: Equal)
   * @return self
   */
 public function LJoin(string $table1, string $table2, string $field1, string $field2, Operator $operator = Operator::Equal): self {
   $this->_Join("LEFT JOIN", $table1, $table2, $field1, $field2, $operator);
   return $this;
 }

   /**
   * Adds a RIGHT JOIN to the query.
   *
   * @param string $table1 Right table name
   * @param string $table2 Left table name
   * @param string $field1 Field from right table
   * @param string $field2 Field from left table
   * @param Operator $operator Join operator (default: Equal)
   * @return self
   */
 public function RJoin(string $table1, string $table2, string $field1, string $field2, Operator $operator = Operator::Equal): self {
   $this->_Join("RIGHT JOIN", $table1, $table2, $field1, $field2, $operator);
   return $this;
 }

   /**
   * Adds a JOIN to the query.
   *
   * @param string $table1 Left table name
   * @param string $table2 Right table name
   * @param string $field1 Field from left table
   * @param string $field2 Field from right table
   * @param Operator $operator Join operator (default: Equal)
   * @return self
   */
 public function Join(string $table1, string $table2, string $field1, string $field2, Operator $operator = Operator::Equal): self {
   $this->_Join("JOIN", $table1, $table2, $field1, $field2, $operator);
   return $this;
 }
 /**
  * Adds an INNER JOIN to the query.
  *
  * @param string $table1 Left table name
  * @param string $table2 Right table name
  * @param string $field1 Field from left table
  * @param string $field2 Field from right table
  * @param Operator $operator Join operator (default: Equal)
  * @return self
  */
 public function IJoin(string $table1, string $table2, string $field1, string $field2, Operator $operator = Operator::Equal): self {
   $this->_Join("INNER JOIN", $table1, $table2, $field1, $field2, $operator);
   return $this;
 }

 /**
  * Adds an OUTER JOIN to the query.
  *
  * @param string $table1 Left table name
  * @param string $table2 Right table name
  * @param string $field1 Field from left table
  * @param string $field2 Field from right table
  * @param Operator $operator Join operator (default: Equal)
  * @return self
  */
 public function OJoin(string $table1, string $table2, string $field1, string $field2, Operator $operator = Operator::Equal): self {
   $this->_Join("OUTER JOIN", $table1, $table2, $field1, $field2, $operator);
   return $this;
 }

 /**
  * Adds a join of any type to the query.
  *
  * @param string $type Join type (e.g., 'LEFT JOIN')
  * @param string $table1 First table name
  * @param string $table2 Second table name
  * @param string $field1 Field from first table
  * @param string $field2 Field from second table
  * @param Operator $operator Join operator (default: Equal)
  * @return self
  */
 public function _Join(string $type, string $table1, string $table2, string $field1, string $field2, Operator $operator = Operator::Equal): self {
    $this->getFields($table2, $this->tableAliases);
    $t1Alias = $this->getTableAlias($table1);
    if ($t1Alias === null) {
        throw new \InvalidArgumentException("Table '$table1' not found in query.");
    }
    $t2Alias = $this->tableAliases;

    $joinCondition = $this->quoteIdentifier($t1Alias) . "." . $this->quoteIdentifier($field1) . " {$operator->value} " . $this->quoteIdentifier($t2Alias) . "." . $this->quoteIdentifier($field2);

    if ($operator === Operator::IsNull) {
        $joinCondition = $this->quoteIdentifier($t1Alias) . "." . $this->quoteIdentifier($field1) . " IS NULL";
    }

    $this->queryTables .= " $type " . $this->quoteIdentifier($table2) . " " . $this->quoteIdentifier($t2Alias) . " ON " . $joinCondition;
    $this->tableAliases++;
    return $this;
 }
  /**
  * Adds a WHERE condition to the query.
  *
  * @param string $table Table name
  * @param string $field Field name
  * @param Operator $operator Comparison operator
  * @param string|null $value Value for the condition
  * @param string $aggregator Logical aggregator (AND/OR)
  * @return self
  */
   /**
   * @param mixed $value
   */
  public function Where(string $table, string $field, Operator $operator, $value = null, string $aggregator = "AND"): self {
     $alias = $this->getTableAlias($table);
     if ($alias === null) {
         throw new \InvalidArgumentException("Table '$table' not found in query.");
     }
     $fieldIdentifier = $this->quoteIdentifier($alias) . '.' . $this->quoteIdentifier($field);

     switch ($operator) {
         case Operator::IsNull:
             $fragment = ($this->hasWhereCondition ? " $aggregator " : "") . "$fieldIdentifier IS NULL";
             $this->queryWhere .= $fragment;
             break;
         case Operator::In:
         case Operator::NotIn:
             if (!is_array($value) || empty($value)) {
                 throw new \InvalidArgumentException('Value for IN/NOT IN must be a non-empty array');
             }
             $placeholders = implode(', ', array_fill(0, count($value), '?'));
             $op = $operator === Operator::In ? 'IN' : 'NOT IN';
             $fragment = ($this->hasWhereCondition ? " $aggregator " : "") . "$fieldIdentifier $op ($placeholders)";
             $this->queryWhere .= $fragment;
             foreach ($value as $v) {
                 $this->queryParams[] = $v;
             }
             break;
         default:
             if ($value === null) {
                 throw new \InvalidArgumentException('Value cannot be null for this operator');
             }
             $fragment = ($this->hasWhereCondition ? " $aggregator " : "") . "$fieldIdentifier {$operator->value} ?";
             $this->queryWhere .= $fragment;
             $this->queryParams[] = $value;
             break;
     }
     $this->hasWhereCondition = true;
     return $this;
 }

  /**
  * Adds a left parenthesis to the WHERE clause (for grouping conditions).
  *
  * @return self
  */
  public function AddLeftParentesis(): self {
    $this->queryWhere .= "(";
    return $this;
  }

  /**
  * Adds a right parenthesis to the WHERE clause (for grouping conditions).
  *
  * @return self
  */
  public function AddRightParentesis(): self {
    $this->queryWhere .= ")";
    return $this;
  }

  /**
  * Adds an ORDER BY clause to the query.
  *
  * @param string $table Table name
  * @param string $field Field name
  * @param string $order Order direction (ASC or DESC)
  * @return self
  */
  public function Order(string $table, string $field, string $order = "ASC"): self {
    $order = strtoupper($order);
    if ($order !== 'ASC' && $order !== 'DESC') {
        throw new \InvalidArgumentException('Invalid order direction. Must be ASC or DESC.');
    }
    $alias = $this->getTableAlias($table);
    if ($alias === null) {
        throw new \InvalidArgumentException("Table '$table' not found in query.");
    }
    $this->queryOrder .= (!empty($this->queryOrder) ? ", " : "") . $this->quoteIdentifier($alias) . "." . $this->quoteIdentifier($field) . " $order";
    return $this;
  }

  public function AddSelectExpression(string $expr): self {
    $this->selectExpressions[] = $expr;
    return $this;
  }

  /**
  * Executes the built query and fetches the results as arrays of model objects.
  *
  * @return array<int, mixed>
  */
  public function Fetch(): array {
    // Build SELECT fields
    $fields = [];
    if (!empty($this->selectedFields)) {
      foreach ($this->selectedFields as $sf) {
        $alias = $this->getTableAlias((string)$sf["table"]);
        if ($alias === null) {
            throw new \InvalidArgumentException("Table '{$sf["table"]}' not found in query.");
        }
        $fields[] = $this->quoteIdentifier($alias) . "." . $this->quoteIdentifier((string)$sf["field"]) . " AS " . $this->quoteIdentifier($alias . "_" . (string)$sf["field"]);
      }
    } else {
      $fields = explode(',', $this->queryFields);
    }
    if (!empty($this->selectExpressions)) {
      foreach ($this->selectExpressions as $expr) {
        $fields[] = $expr;
      }
    }
    $selectClause = implode(",", array_filter($fields));

    $limitClause = '';
    if (!empty($this->queryLimit)) {
        switch ($this->dbDriver) {
            case 'mysql':
            case 'sqlite':
                $limitClause = "LIMIT ?, ?";
                $this->queryParams[] = (int)$this->queryLimit['offset'];
                $this->queryParams[] = (int)$this->queryLimit['limit'];
                break;
            case 'mssql':
                $limitClause = "OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                $this->queryParams[] = (int)$this->queryLimit['offset'];
                $this->queryParams[] = (int)$this->queryLimit['limit'];
                break;
        }
    }

    $sql = "SELECT " . $selectClause . " FROM " . $this->queryTables . " " . ($this->hasWhereCondition ? " WHERE " . $this->queryWhere : "") . 
      (!empty($this->queryGroupBy) ? " GROUP BY " . $this->queryGroupBy : "") . 
      (!empty($this->queryHaving) ? " HAVING " . $this->queryHaving : "") . 
      (!empty($this->queryOrder) ? " ORDER BY " . $this->queryOrder : "") . 
      (!empty($limitClause) ? " " . $limitClause : "");

    $params = $this->queryParams;
    $this->queryParams = [];
    $placeholderCount = substr_count($sql, '?');
    if ($placeholderCount !== count($params)) {
      throw new \RuntimeException("Parameter/placeholder mismatch in Fetch: SQL has $placeholderCount placeholders, but params has " . count($params) . " values. SQL: $sql Params: " . var_export($params, true));
    }
    $result = array();
    $dbConnection = LazyMePHP::DB_CONNECTION();
    if ($dbConnection === null) {
        throw new \RuntimeException("Database connection not established.");
    }

    $resultObj = $dbConnection->Query($sql, $params);
    
    // If any selectExpressions are present (aggregate query), return raw associative arrays
    if (!empty($this->selectExpressions)) {
        $result = [];
        while (($row = $resultObj->fetchArray()) !== null) {
            $result[] = $row;
        }
        return $result;
    }

    $result = [];
    while (($o = $resultObj->fetchArray()) !== null) {
        $objs = [];
        foreach ($this->tables as $t) {
            $class = "\\Models\\" . $t["table"];
            $data = [];
            // If we have selectedFields, only include those fields for this table
            if (!empty($this->selectedFields)) {
                foreach ($this->selectedFields as $sf) {
                    if ((string)$sf["table"] === $t["table"]) {
                        $fieldKey = $t["alias"] . "_" . (string)$sf["field"];
                        if (isset($o[$fieldKey])) {
                            $data[(string)$sf["field"]] = $o[$fieldKey];
                        }
                    }
                }
            } else {
                // Use all fields from the table
                foreach ($t["fields"] as $f) {
                    if (isset($o[$t["alias"] . "_" . $f])) {
                        $data[$f] = $o[$t["alias"] . "_" . $f];
                    }
                }
            }
            $obj = new $class($data);
            array_push($objs, ["table" => $t["table"], "object" => $obj]);
        }
        array_push($result, $objs);
    }
    return $result;
  }

  /**
  * getFields
  *
  * Private method that gets table's fields
  *
  * @param string $table
  * @param string $alias
  * @return void
  */
  private function getFields(string $table, string $alias): void {
    $fields = array();
    foreach(get_class_methods("\\Models\\$table") as $method) {
      if (substr($method,0,3) == "Set") {
        $field = lcfirst(substr($method,3));
        array_push($fields, $field);
        // Use identifier quoting
        $this->queryFields .= (!empty($this->queryFields)?",":"") . $this->quoteIdentifier($alias) . "." . $this->quoteIdentifier($field) . " AS " . $this->quoteIdentifier($alias . "_" . $field);
      }
    }
    array_push($this->tables,array("table" => $table, "alias" => $alias, "fields" => $fields));
  }

  /**
  * getTableAlias
  *
  * Private method that gets table's alias
  *
  * @param string $table
  * @return string|null
  */
  private function getTableAlias(string $table): ?string {
    // We reverse search so our where conditions could be added after left join
    foreach(array_reverse($this->tables) as $t) {
      if ($t["table"]==$table) return (string)$t["alias"];
    }
    return null;
  }
  /**
   * Debugs the built query by echoing the SQL string.
   *
   * @return void
   */
  public function debug(): void {
    $limitClause = '';
    if (!empty($this->queryLimit)) {
        switch ($this->dbDriver) {
            case 'mysql':
            case 'sqlite':
                $limitClause = "LIMIT {$this->queryLimit['offset']}, {$this->queryLimit['limit']}";
                break;
            case 'mssql':
                $limitClause = "OFFSET {$this->queryLimit['offset']} ROWS FETCH NEXT {$this->queryLimit['limit']} ROWS ONLY";
                break;
        }
    }

    echo "SELECT ".$this->queryFields." FROM ".$this->queryTables." WHERE ".$this->queryWhere." ORDER BY ".$this->queryOrder . " " . $limitClause;
  }
}