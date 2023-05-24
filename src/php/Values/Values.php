<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\ValuesList;
use \LazyMePHP\Core\Enum\Enum;


class VALUESLIST {

	// Its a "static" class
	private function __construct() {}

	// Localizações
	static $ExampleValue;

}


/**
 * ExampleValue
 */
VALUESLIST::$ExampleValue = new Enum();
VALUESLIST::$ExampleValue->Add("Value 1",1);
VALUESLIST::$ExampleValue->Add("Value 2",2);

?>
