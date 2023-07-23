<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * Source File Generated Automatically
 */

namespace LazyMePHP\Forms;
use \eftec\bladeone\BladeOne;

class DefaultController {
	private static $views = __DIR__ . '/../Views/';
	private static $cache = __DIR__ . '/../Views/compiled/';

	static function Default() {
		$blade = new BladeOne(DefaultController::$views,DefaultController::$cache);
		echo $blade->run("_Default.view");
	}
}

?>
