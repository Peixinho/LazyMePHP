<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
 */

namespace LazyMePHP\DB;
use LazyMePHP\Config\Internal\APP;

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

class Select {

  private $queryTables;
  private $queryFields;
  private $queryWhere;
  private $hasWhereCondition = false;
  private $queryOrder;
  private $tables = array();
  private $tableAliases = "A";

   /**
   * From
   *
   * Public Method that initiates a query
   *
   * @table (String) table name
   * @return (NULL)
   */
  public function From($table) {
    $this->getFields($table);
    $this->queryTables .= (!empty($this->queryTables)?",":"").$table." ".$this->tableAliases;
    $this->tableAliases++;
 }

   /**
   * LJoin
   *
   * Left Join
   *
   * @table1 (String) table name to be on the left of the join
   * @table2 (String) table name to be on the right of the join
   * @field1 (String) field name of the table1
   * @field2 (String) field name of the table2
   * @operator (Enum) operator for the join, default is =
   * @return (NULL)
   */
 public function LJoin($table1, $table2, $field1, $field2, $operator = Operator::Equal) {
   $this->_Join("LEFT JOIN", $table1, $table2, $field1, $field2, $operator);
 }

   /**
   * RJoin
   *
   * Right Join
   *
   * @table1 (String) table name to be on the right of the join
   * @table2 (String) table name to be on the left of the join
   * @field1 (String) field name of the table1
   * @field2 (String) field name of the table2
   * @operator (Enum) operator for the join, default is =
   * @return (NULL)
   */
 public function RJoin($table1, $table2, $field1, $field2, $operator = Operator::Equal) {
   $this->_Join("RIGHT JOIN", $table1, $table2, $field1, $field2, $operator);
 }

   /**
   * Join
   *
   * Join
   *
   * @table1 (String) table name to be on the left of the join
   * @table2 (String) table name to be on the right of the join
   * @field1 (String) field name of the table1
   * @field2 (String) field name of the table2
   * @operator (Enum) operator for the join, default is =
   * @return (NULL)
   */
 public function Join($table1, $table2, $field1, $field2, $operator = Operator::Equal) {
   $this->_Join("JOIN", $table1, $table2, $field1, $field2, $operator);
 }
 /**
  * IJoin
  *
  * Inner Join
  *
  * @table1 (String) table name to be on the left of the join
  * @table2 (String) table name to be on the right of the join
  * @field1 (String) field name of the table1
  * @field2 (String) field name of the table2
  * @operator (Enum) operator for the join, default is =
  * @return (NULL)
  */
 public function IJoin($table1, $table2, $field1, $field2, $operator = Operator::Equal) {
   $this->_Join("INNER JOIN", $table1, $table2, $field1, $field2, $operator);
 }

 /**
  * OJoin
  *
  * Outter Join
  *
  * @table1 (String) table name to be on the left of the join
  * @table2 (String) table name to be on the right of the join
  * @field1 (String) field name of the table1
  * @field2 (String) field name of the table2
  * @operator (Enum) operator for the join, default is =
  * @return (NULL)
  */
 public function OJoin($table1, $table2, $field1, $field2, $operator = Operator::Equal) {
   $this->_Join("OUTTER JOIN", $table1, $table2, $field1, $field2, $operator);
 }

 /**
  *
  * Private function to run our JOINs
  *
  */
 public function _Join($type, $table1, $table2, $field1, $field2, $operator = Operator::Equal) {
   $this->getFields($table2);
   switch($operator) {
     case Operator::IsNull:
       $this->queryTables .= " $type $table2 ".$this->tableAliases." ON ".$this->getTableAlias($table1).".$field1 ".$operator->value;
       break;
     default:
       $this->queryTables .= " $type $table2 ".$this->tableAliases." ON ".$this->getTableAlias($table1).".$field1 $operator->value (".$this->tableAliases.".$field2)";
       break;
   }
   $this->tableAliases++;
  }
  /**
  * Where
  *
  * Where condition
  *
  * @table (String) table name
  * @field (String) field of the condition
  * @operator (Enum) operator to be used in the condition
  * @value (String) value if the condition
  * @aggregator (String) aggregator for the condition, used when there
  * already a previous condition
  * @return (NULL)
  */
   public function Where($table, $field, $operator, $value = NULL, $aggregator = "AND") {
     switch($operator) {
     case Operator::IsNull:
       $this->queryWhere .= ($this->hasWhereCondition?" $aggregator ":"").$this->getTableAlias($table).".$field ".$operator->value;
       break;
     case Operator::In:
     case Operator::NotIn:
       $this->queryWhere .= ($this->hasWhereCondition?" $aggregator ":"").$this->getTableAlias($table).".$field $operator->value (".(is_array($value?implode(',',$value):$value)).")";
       break;
     default:
       $this->queryWhere .= ($this->hasWhereCondition?" $aggregator ":"").$this->getTableAlias($table).".$field $operator->value ($value)";
       break;
    }
  $this->hasWhereCondition = true;
}

  /**
  *
  * Left Parentesis
  *
  * This adds a left parentesis to the where condition
  */
  public function AddLeftParentesis() {
    $this->queryWhere .= "(";
  }

  /**
  *
  * Right Parentesis
  *
  * This adds a right parentesis to the where condition
  */
  public function AddRightParentesis() {
    $this->queryWhere .= ")";
  }

  /**
  * Order
  *
  * Order
  *
  * @table (String) table name
  * @field (String) field of the condition
  * @order (String) order (ASC, DESC)
  * @return (NULL)
  */
  public function Order($table, $field, $order = "ASC") {
    $this->queryOrder .= (!empty($this->queryOrder)?",":"").$this->getTableAlias($table).".$field $order";
  }

  /**
  * Fetch
  *
  * Fetch the results
  *
  * @table (NULL)
  * @return (array)
  */
  public function Fetch() {
    $result = array();
    $sql = "SELECT ".$this->queryFields." FROM ".$this->queryTables." ".($this->hasWhereCondition?" WHERE ".$this->queryWhere:"")." ".(!empty($this->queryOrder)?" ORDER BY ".$this->queryOrder:"");
    APP::DB_CONNECTION()->Query($sql, $rtn);
    while($o=$rtn->FetchArray())
    {
      $objs = array();
      foreach($this->tables as $t) {
        $class = "\\LazyMePHP\\Classes\\Priv\\__".$t["table"];
        $obj = new $class;
        foreach($t["fields"] as $f) {
          $method = "_Set$f";
          $obj->$method($o[$t["alias"]."_$f"]);
        }
        array_push($objs, array("table" => $t["table"], "object" => $obj));
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
  * @table (NULL)
  * @return (array)
  */
  private function getFields($table) {
    $fields = array();
    foreach(get_class_methods("\\LazyMePHP\\Classes\\Priv\\__$table") as $method) {
      if (substr($method,0,4) == "_Set") {
        $field = substr($method,4,strlen($method));
        array_push($fields, $field);
        $this->queryFields .= (!empty($this->queryFields)?",":"").$this->tableAliases.".".$field." AS ".$this->tableAliases."_".$field;
      }
    }
    array_push($this->tables,array("table" => $table, "alias" => (empty($alias)?$this->tableAliases:$alias), "fields" => $fields));
  }

  /**
  * getTableAlias
  *
  * Private method that gets table's alias
  *
  * @table (NULL)
  * @return (array)
  */
  private function getTableAlias($table) {
    // We reverse search so our where conditions could be added after left join
    foreach(array_reverse($this->tables) as $t) {
      if ($t["table"]==$table) return $t["alias"];
    }
  }
  public function debug() {
    echo "SELECT ".$this->queryFields." FROM ".$this->queryTables." WHERE ".$this->queryWhere." ORDER BY ".$this->queryOrder;
  }
}

?>
