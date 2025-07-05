<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Core;

class ModelBase
{
  protected bool $__initialized = false;

  public function initialize() : void {
    $this->__initialized = true;
  }

  protected function isInitialized() : bool {
    return $this->__initialized;
  }

}
