<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Core\Enum;
use LazyMePHP\Core\Enum\EnumProperty;

// Required
require_once 'EnumProperty.class.php';

/**
 * Class Enum
 *
 * Emulates the Enum Structure in C/C++
 */
class Enum {

    private $list;
    private $lastValue;

    public function __construct()
    {
        $this->list = array();
    }
    /**
     * Adds a property to the Enum
     * @param type $name
     * @param type $value
     */
    public function Add($name, $value = null)
    {
        if ($value!=null)
        {
            $enum = new EnumProperty($name, $value);
            $this->lastValue = $value;
        } else {
			$this->lastValue = (sizeof($this->list)>0?$this->lastValue+1:1);
			$enum = new EnumProperty($name, $this->lastValue);
        }
        // Add enum to list
        array_push($this->list,$enum);
    }

    /**
     * Returns the value of a specific Enum
     * @param type $name
     * @return type
     */
    public function GetValue($name)
    {
        foreach ($this->list as $e)
        {
            if ($e->name == $name)
            {
                return $e->value;
            }
        }
    }

    /**
     * Returns the name of a specific value
     * @param type $value
     * @return type
     */
    public function GetName($value)
    {
        foreach ($this->list as $e)
        {
            if ($e->value == $value)
            {
                return $e->name;
            }
        }
    }

    /**
     * Returns Enum List
     * @return type Array
     */
    public function GetList()
    {
        return $this->list;
    }

    /**
     * Returns Bidimensional Array
     * to use on select
     */
    public function GetArray()
    {
        $arr = array();
        foreach ($this->list as $e)
        {
            array_push($arr, array($e->value, $e->name));
        }
        return $arr;
    }
}

?>
