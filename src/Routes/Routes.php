<?php
/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Forms;
use Pecee\SimpleRouter\SimpleRouter;

require_once __DIR__."/../Controllers/Default.Controller.php";
SimpleRouter::get('/', [DefaultController::class, 'default']); 

?>
