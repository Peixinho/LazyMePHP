<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Enum;

/**
 *
 * Class EnumProperty
 *
 * @author peixinho
 */
class EnumProperty {

    public $name;
    public $value;

    /**
     * Enum Property
     *
     *Function that emulates the enum property on C/C++
     * @param type $name
     * @param type $value
     */
    public function __construct($name, $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

}

?>
