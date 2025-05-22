<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Values;

enum ExampleValue:int {
  case STATE_1 = 1;
  case STATE_2 = 2;
  case STATE_3 = 3;

  public function getDescription():string {
    return match($this) {
      ExampleValue::STATE_1 => "State 1",
      ExampleValue::STATE_2 => "State 2",
      ExampleValue::STATE_3 => "State 3"
    };
  }
}
?>
