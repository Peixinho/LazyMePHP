<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\DB;
use LazyMePHP\Config\Internal\APP;

class Select {

	private $queryTables;
	private $queryFields;
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
		$this->queryTables .= (strlen($this->queryTables)>0?",":"").$table." ".$this->tableAliases;
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
	* @operator (String) operator for the join, default is =
	* @return (NULL)
	*/
	public function LJoin($table1, $table2, $field1, $field2, $operator = "=") {
		$this->getFields($table2);
		$this->queryTables .= " LEFT JOIN $table2 ".$this->tableAliases." ON ".$this->getTableAlias($table1).".$field1 $operator ".$this->tableAliases.".$field2";
		$this->tableAliases++;
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
	* @operator (String) operator for the join, default is =
	* @return (NULL)
	*/
	public function RJoin($table1, $table2, $field1, $field2, $operator = "=") {
		$this->getFields($table);
		$this->queryTables .= " RIGHT JOIN $table2 ".$this->tableAliases." ON ".$this->getTableAlias($table1).".$field1 $operator ".$this->tableAliases.".$field2";
		$this->tableAliases++;
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
	* @operator (String) operator for the join, default is =
	* @return (NULL)
	*/
	public function Join($table1, $table2, $field1, $field2, $operator = "=") {
		$this->getFields($table);
		$this->queryTables .= " JOIN $table2 ".$this->tableAliases." ON ".$this->getTableAlias($table1).".$field1 $operator ".$this->tableAliases.".$field2";
		$this->tableAliases++;
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
	* @operator (String) operator for the join, default is =
	* @return (NULL)
	*/
	public function IJoin($table1, $table2, $field1, $field2, $operator = "=") {
		$this->getFields($table);
		$this->queryTables .= " INNER JOIN $table2 ".$this->tableAliases." ON ".$this->getTableAlias($table1).".$field1 $operator ".$this->tableAliases.".$field2";
		$this->tableAliases++;
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
	* @operator (String) operator for the join, default is =
	* @return (NULL)
	*/
	public function OJoin($table1, $table2, $field1, $field2, $operator = "=") {
		$this->getFields($table);
		$this->queryTables .= " OUTTER JOIN $table2 ".$this->tableAliases." ON ".$this->getTableAlias($table1).".$field1 $operator ".$this->tableAliases.".$field2";
		$this->tableAliases++;
	}

	/**
	* Where
	*
	* Where condition
	*
	* @table (String) table name
	* @field (String) field of the condition
	* @operator (String) operator to be used in the condition
	* @value (String) value if the condition
	* @aggregator (String) aggregator for the condition, used when there
	* already a previous condition
	* @return (NULL)
	*/
	public function Where($table, $field, $operator, $value, $aggregator = "AND") {
		$this->queryWhere .= (strlen($this->queryWhere)>0?" $aggregator ":"").$this->getTableAlias($table).".$field $operator $value";
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
		$this->queryOrder .= (strlen($this->queryOrder)>0?",":"").$this->getTableAlias($table).".$field $order";
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
		$sql = "SELECT ".$this->queryFields." FROM ".$this->queryTables." WHERE ".$this->queryWhere." ORDER BY ".$this->queryOrder;
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
				$this->queryFields .= (strlen($this->queryFields)>0?",":"").$this->tableAliases.".".$field." AS ".$this->tableAliases."_".$field;
			}
		}
		array_push($this->tables,array("table" => $table, "alias" => ($alias==null?$this->tableAliases:$alias), "fields" => $fields));
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
		foreach($this->tables as $t) {
			if ($t["table"]==$table) return $t["alias"];
		}
	}
	public function debug() {
		echo "SELECT ".$this->queryFields." FROM ".$this->queryTables." WHERE ".$this->queryWhere." ORDER BY ".$this->queryOrder;
	}
}

?>
