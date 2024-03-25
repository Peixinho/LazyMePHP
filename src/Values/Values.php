<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\ValuesList;

class VALUESLIST {

	// Its a "static" class
	private function __construct() {}

	// Localizações
	static $ExampleValue;

}


/**
 * ExampleValue
 */

enum ExampleValue:string {
  case Value_1 = "Value 1";
  case Value_2 = "Value 2";
}
?>
