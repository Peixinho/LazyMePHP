<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Core;

class ClassBase
{
  protected $__initialized = false;

  protected function initialize() {
    $this->__initialized = true;
  }

  protected function isInitialized() {
    return $this->__initialized;
  }

}
