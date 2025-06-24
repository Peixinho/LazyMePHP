<?php
/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Forms;

use Controllers;
use Pecee\SimpleRouter\SimpleRouter;
use \eftec\bladeone\BladeOne;

$blade = \Core\BladeFactory::getBlade();

SimpleRouter::get('/', function() use ($blade) : void {
    // Return the content for the index page
    echo $blade->run("_Index.index");
}); 

// Load all routes by default
foreach (glob(__DIR__."/" . '/*.php') as $file) {
  if (
    substr($file, strrpos($file, "/")+1, strlen($file)) != "Routes.php"
  )
  require($file);
}