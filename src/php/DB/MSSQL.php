<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\DB;

require_once "ISQL.php";

final class MSSQL extends ISQL {

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
                        $this->connection = new \PDO("sqlsrv:Server=".$this->db_host.";Database=".$this->db_name,$this->db_username,$this->db_password);
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
        * @param (MSSQL_OBJECT) (object)
        * @return (NULL)
        */
        public function Query($string, &$obj, $args = array())
        {
                if (!$this->isConnected)
                        $this->Connect();

                if ($obj==NULL)
                        $obj = new MSSQL_OBJECT();

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
                $this->Query("SELECT @@IDENTITY AS ID FROM $table", $obj);
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
			return "OFFSET $start ROWS FETCH NEXT $end ROWS ONLY";
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
                        $this->Connection = null;

                $this->isConnected = false;
        }
}

final class MSSQL_OBJECT extends IDB_OBJECT {

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
        * Returns Database Result
        *
        * @param (NULL)
        * @return (object)
        */
        public function GetDBResult()
        {
                return $this->db_result;
        }

        /**
        * FetchObject
        *
        * Returns Database Result in Object
        *
        * @param (NULL)
        * @return (object)
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
        * @return (array)
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
            $arr = $this->db_result->fetch(\PDO::FETCH_NUM);

            if (!empty($arr))
                return sizeof($arr);

            return 0;
        }
}

?>
