<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Messages;

class Messages {

  static function ShowError(string $err) : void
  {
    $_GET['error'] = $err;
  }

  static function ShowSuccess(string $succ) : void
  {
    $_GET['success'] = $succ;
  }

}
