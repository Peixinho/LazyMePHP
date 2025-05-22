<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace LazyMePHP\Messages;

function ShowError($err)
{
	$_GET['error'] = $err;
}

function ShowSuccess($succ)
{
	$_GET['success'] = $succ;
}

enum SuccessMessages:string {
  case S1 = "Success Message 1";
  case S2 = "Success Message 2";
}
enum ErrorMessages:string {
  case E1 = "Error Message 1";
  case E2 = "Error Message 2";
}
?>
