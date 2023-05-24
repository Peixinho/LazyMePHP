<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Core\Request;

class Request {

  static $request;

  static function Process() {
    if ($_POST)
    foreach($_POST as $k => $p)
    {
     Request::$request->{$k} = filter_input(INPUT_POST, $k);
    }
    // Clear Post
    $_POST = array();
  }
}

Request::Process();

?>
