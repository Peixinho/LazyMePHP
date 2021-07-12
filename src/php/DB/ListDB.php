<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\DB;
use \LazyMePHP\Config\Internal\APP;

class ListDB {

    private $_Table;
    private $_Fields;
    private $_FieldsArr = array();
    private $_Filter;
    private $_Limit;
    private $_Offset;
    private $_Order;
    private $_CustomFilter;
    private $_fieldsFilterCounter = array();

    /**
	 * Constructor
	 *
	 * @param (string) ($table)
	 * @return NULL
	 */
    public function __construct($table)
    {
        $this->_Table = $table;
        if (isset($class)) {
			$this->_Class = $class;
		}
    }

    /**
	 * SetOrder
	 *
	 * Sets the order of the results
	 *
	 * @param (string) ($field)
     * @param (string) ($order)
	 * @return NULL
	 */
    public function SetOrder($field, $order = "ASC")
    {
        $this->_Order = (strlen($this->_Order)>0?$this->_Order.", $field $order ":" ORDER BY $field $order ");
    }

    /**
	 * AddFilter
	 *
	 * Adds a new filter
	 *
	 * @param (string) ($field)
     * @param (string) ($value)
     * @param (string) ($append)
     * @param (string) ($operator)
	 * @return NULL
	 */
    public function AddFilter($field, $value, $append = NULL, $operator = "=")
    {
        if (!array_key_exists($field, $this->_fieldsFilterCounter)) {
			$this->_fieldsFilterCounter[$field] = 1;
		}
        else {
			$this->_fieldsFilterCounter[$field]++;
		}
        $this->_Filter .= (strlen($this->_Filter)>0?$append:"")." $field $operator ? ";
        array_push($this->_FieldsArr, $value);
        //$this->_Filter .= (strlen($this->_Filter)>0?$append:"")." $field $operator '$value' ";
    }

    /**
	 * SetFields
	 *
	 * Sets Query Fields
	 *
	 * @param (string) ($fields)
	 * @return NULL
	 */
    public function SetFields($fields)
    {
        $this->_Fields = preg_replace('/\s+/', '', $fields);
    }

    /**
	 * AddCustomFilter
	 *
	 * Ads a custom filter to query
	 *
	 * @param (string) ($fields)
	 * @return NULL
	 */
    public function AddCustomFilter($filter)
    {
        $this->_CustomFilter = $filter;
    }

    /**
	 * Clear Filter
	 *
	 * Clears Created Filters
	 *
	 * @param (NULL)
	 * @return NULL
	 */
    public function ClearFilter()
    {
        $this->_Filter = NULL;
        $this->_Limit = NULL;
        $this->_Offset = NULL;
        $this->_FieldsArr = NULL;
    }

    /**
	 * Set Limit
	 *
	 * Sets the limit and offset of the results
	 *
	 * @param (number) ($limit)
     * @param (number) ($offset)
	 * @return NULL
	 */
    public function SetLimit($limit, $offset = 0)
    {
        $this->_Limit = $limit;
        $this->_Offset = $offset;
    }

    /**
	 * GetList
	 *
	 * Gets the generated results
	 *
	 * @param (NULL)
	 * @return array
	 */
    public function GetList()
    {
        // Add Parentheses to Filters
        foreach ($this->_fieldsFilterCounter as $key => $field) {
            if ($field>1)
            {
                // Set First Parentheses
                $pos = strpos($this->_Filter, " ".$key." ");
                $this->_Filter = substr_replace($this->_Filter, " (", $pos, 0);

                // Set Second Parentheses
                $pos2 = strrpos($this->_Filter, " ".$key." ", $pos);
                $posF = strpos($this->_Filter, " ? ", $pos2);
                $this->_Filter = substr_replace($this->_Filter, " )", $posF+2, 0);
            }
        }

        if (strlen($this->_CustomFilter)>0) {
			$this->_Filter.=$this->_CustomFilter;
		}

        switch(APP::DB_TYPE())
        {
            case 1:
            // MSSQL
                $queryString =
				(strlen($this->_Limit)>0 || strlen($this->_Offset)>0?"SELECT * FROM (":"")
				."SELECT "
				.$this->_Fields
				." "
				.(strlen($this->_Limit)>0 || strlen($this->_Offset>0)?", ROW_NUMBER() OVER (".(strlen($this->_Order)>0?$this->_Order:"ORDER BY (SELECT NULL)").") as __ROW":"")
				." FROM ".$this->_Table." ".(strlen($this->_Filter)>0?"WHERE ".$this->_Filter:"")." "
				.(strlen($this->_Limit)>0 || strlen($this->_Offset>0)?"":$this->_Order)." "
				.(strlen($this->_Limit)>0 || strlen($this->_Offset)>0?") T WHERE ":"")." "
				.(strlen($this->_Offset)>0?"__ROW >= '".$this->_Offset."'":"")." "
				.(strlen($this->_Limit)>0?(strlen($this->_Offset)>0?" AND __ROW <= '".$this->_Limit:" __ROW <= '".$this->_Limit)."'":"");
            break;
            case 2:
            // MySQL
            default:
                $queryString = "SELECT "
                .$this->_Fields." FROM "
                .$this->_Table." "
                .(strlen($this->_Filter)>0?"WHERE ".$this->_Filter:"")." "
                .$this->_Order." "
                .($this->_Limit>0?"LIMIT ".$this->_Limit:"")." ".($this->_Offset>0?"OFFSET ".$this->_Offset:"");
            break;
        }

        APP::DB_CONNECTION()->Query($queryString, $obj, $this->_FieldsArr);

        $arr = array();

        while($o = $obj->FetchObject())
        {
            $row = array();
            foreach (explode(',',$this->_Fields) as $field) {
                $_field = explode(" as ", $field);
                $_field = $_field[sizeof($_field)-1];
                $row[$_field]=$o->$_field;
            }
            array_push($arr,$row);
        }

        return $arr;
    }

}

?>
