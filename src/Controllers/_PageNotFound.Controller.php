<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace LazyMePHP\Views;
use \eftec\bladeone\BladeOne;

class PageNotFoundController {
	private static $views = __DIR__ . '/../Views/';
	private static $cache = __DIR__ . '/../Views/_compiled/';

	static function Default() {
		$blade = new BladeOne(PageNotFoundController::$views,PageNotFoundController::$cache);
		echo $blade->run("_Error.view");
	}
}

?>
