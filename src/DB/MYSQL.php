<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\DB;
use \PDO;
require_once "ISQL.php";

final class MYSQL extends ISQL {

  /**
   * Public Constructor
   *
   * @param (NULL)
   * @return (NULL)
   */
  function __construct($db_name, $db_user, $db_password, $db_host) {
    parent::ISQL($db_name, $db_user, $db_password, $db_host);
  }

  /**
   * Connect
   *
   * Protected Method that Creates DB Connection
   *
   * @param (NULL)
   * @return (NULL)
   */
  protected function Connect()
  {
    if (!$this->isConnected)
    {
      $this->connection = new \PDO("mysql:host=".$this->db_host.";dbname=".$this->db_name.";charset=utf8",$this->db_username,$this->db_password);
      if(is_object($this->connection) && $this->connection != false)
      {
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->isConnected = true;
      }
      else {
        die("Connection failed");
      }
    }
  }

  /**
   * Query
   *
   * Create DB Query
   *
   * @param (string) ($string)
   * @param (MYSQL_OBJECT) (object)
   * @return (NULL)
   */
  public function Query($string, &$obj, $args = array())
  {
    if (!$this->isConnected)
    $this->Connect();

    if ($obj==NULL)
    $obj = new MYSQL_OBJECT();

    $obj->queryString = $string;
    $obj->db_result = $this->connection->prepare($obj->queryString);
    foreach ($args as $key => $value) {
      $obj->db_result->bindValue($key+1, $value);
    }
    $obj->db_result->execute();
  }

  /**
   * Last Inserted ID
   *
   * Returns last inserted id
   *
   * @param (NULL)
   * @return (int) (last id inserted)
   */
  public function GetLastInsertedID($table)
  {
    $this->Query("SELECT LAST_INSERT_ID() as ID", $obj);
    while ($o=$obj->FetchObject()) return intval($o->ID);
  }

  /**
   * Limit
   *
   * Returns string for specifying limits clause
   *
   * @param (int) (end)
   * @param (int) (start)
   * @return (string) (limit clause)
   */
  public function Limit($end, $start = 0)
  {
    return "LIMIT $start, $end";
  }

  /**
   * Close
   *
   * Closes Database Connection
   *
   * @param (NULL)
   * @return (object) (fetch_object)
   */
  public function Close()
  {
    if ($this->isConnected)
    $this->connection = null;

    $this->isConnected = false;
  }
}

final class MYSQL_OBJECT extends IDB_OBJECT {

  /**
   * GetQueryString
   *
   * Returns Query String
   *
   * @param (NULL)
   * @return (object) (fetch_object)
   */
  public function GetQueryString()
  {
    return $this->queryString;
  }

  /**
   * GetDBResult
   *
   * Returns Database Result in Object
   *
   * @param (NULL)
   * @return (object) (fetch_object)
   */
  public function GetDBResult()
  {
    return $this->db_result;
  }

  /**
   * FetchObject
   *
   * Returns Database Number of Results
   *
   * @param (NULL)
   * @return (object) (fetch_object)
   */
  public function FetchObject()
  {
    return $this->db_result->fetch(\PDO::FETCH_OBJ);
  }

  /**
   * FetchArray
   *
   * Returns Database Result in Array
   *
   * @param (NULL)
   * @return (object) (fetch_object)
   */
  public function FetchArray()
  {
    return $this->db_result->fetch(\PDO::FETCH_ASSOC);
  }

  /**
   * FetchNumberResults
   *
   * Returns Database Number of Results
   *
   * @param (NULL)
   * @return (int)
   */
  public function FetchNumberResults()
  {
    return $this->db_result->rowCount();
  }
}

?>
