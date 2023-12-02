<?php
/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Forms;
use Pecee\SimpleRouter\SimpleRouter;
use \eftec\bladeone\BladeOne;

require_once __DIR__."/../Controllers/_Default.Controller.php";
require_once __DIR__."/../Controllers/_PageNotFound.Controller.php";

$views = __DIR__ . '/../Views/';
$cache = __DIR__ . '/../Views/compiled/';
$blade = new BladeOne($views,$cache);

SimpleRouter::get('/', [DefaultController::class, 'default']); 
SimpleRouter::get('/not-found', [PageNotFoundController::class, 'default']);
SimpleRouter::get('/forbidden', [PageNotFoundController::class, 'default']);

SimpleRouter::error(function(\Pecee\Http\Request $request, \Exception $exception) {

    switch($exception->getCode()) {
        // Page not found
        case 404:
            \LazyMePHP\Helper\response()->redirect('/not-found');
        // Forbidden
        case 403:
            \LazyMePHP\Helper\response()->redirect('/forbidden');
    }
    
});

// Load all routes by default
foreach (glob(__DIR__."/" . '/*.php') as $file) {
  if (
    substr($file, strrpos($file, "/")+1, strlen($file)) != "Routes.php"
  )
  require($file);
}

?>
