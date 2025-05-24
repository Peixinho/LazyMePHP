<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Messages;

class Messages {

  static function ShowError($err)
  {
    $_GET['error'] = $err;
  }

  static function ShowSuccess($succ)
  {
    $_GET['success'] = $succ;
  }

}
