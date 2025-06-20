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

$views = __DIR__ . '/../Views/';
$cache = __DIR__ . '/../Views/_compiled';
$blade = new BladeOne($views,$cache);

SimpleRouter::get('/', function() : void {
    global $blade;
    echo $blade->run("_Default.view");
}); 

SimpleRouter::get('/not-found', function() : void {
    global $blade;
    echo $blade->run("_Error.view");
});

SimpleRouter::get('/forbidden', function() : void {
    global $blade;
    echo $blade->run("_Error.view");
});

// Load all routes by default
foreach (glob(__DIR__."/" . '/*.php') as $file) {
  if (
    substr($file, strrpos($file, "/")+1, strlen($file)) != "Routes.php"
  )
  require($file);
}

?>
