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

require_once __DIR__."/../Controllers/_Default.Controller.php";
require_once __DIR__."/../Controllers/_PageNotFound.Controller.php";

$views = __DIR__ . '/../Views/';
$cache = __DIR__ . '/../Views/_compiled';
$blade = new BladeOne($views,$cache);

SimpleRouter::get('/', [Controllers\DefaultController::class, 'default']); 
SimpleRouter::get('/not-found', [Controllers\PageNotFoundController::class, 'default']);
SimpleRouter::get('/forbidden', [Controllers\PageNotFoundController::class, 'default']);

// Load all routes by default
foreach (glob(__DIR__."/" . '/*.php') as $file) {
  if (
    substr($file, strrpos($file, "/")+1, strlen($file)) != "Routes.php"
  )
  require($file);
}

?>
