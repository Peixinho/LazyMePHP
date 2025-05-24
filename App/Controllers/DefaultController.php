<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Controllers;
use \eftec\bladeone\BladeOne;

class DefaultController {
	private static $views = __DIR__ . '/../Views/';
	private static $cache = __DIR__ . '/../Views/_compiled/';

	static function Default() {
		$blade = new BladeOne(DefaultController::$views,DefaultController::$cache);
		echo $blade->run("_Default.view");
	}
}

?>
