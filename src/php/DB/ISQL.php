<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\DB;
use LazyMePHP\Config\Internal\APP;

class ISQL {

        // Protected Configs
        protected $isConnected = false;
        // Connection
        protected $connection;
        // Selected DB
        protected $db_selected;

        // Credentials
        protected $db_username;
        protected $db_password;
        protected $db_host;

        // Database Name
        protected $db_name;

        protected function ISQL($db_name, $db_user, $db_password, $db_host)
        {
			$this->db_username = $db_user;
			$this->db_password =  $db_password;
			$this->db_host = $db_host;
			$this->db_name = $db_name;
        }

        protected function Connect() {}

        public function Query($string, &$obj) {}

        public function GetLastInsertedID($table) {}

		public function Limit($end, $start = 0) {}

        public function Close() {}
}

class IDB_OBJECT {

        // Query Result
        public $db_result;
        // Query String
        public $queryString;

        public function FetchObject() {}

        public function FetchArray() {}

        public function FetchNumberResults() {}
}

?>
