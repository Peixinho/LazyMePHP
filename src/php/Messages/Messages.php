<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Messages;
use \LazyMePHP\Enum\Enum;

function ShowError($err)
{
	$_GET['error'] = $err;
}

function ShowSuccess($succ)
{
	$_GET['success'] = $succ;
}

$SuccessMessages = new Enum();
$ErrorMessages = new Enum();

// Add Messages
$SuccessMessages->Add("...", 1);
$ErrorMessages->Add("...", 1);

?>
