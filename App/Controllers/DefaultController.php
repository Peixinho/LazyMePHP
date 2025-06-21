<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Controllers;
use \eftec\bladeone\BladeOne;

class DefaultController {
	private static string $views = __DIR__ . '/../Views/';
	private static string $cache = __DIR__ . '/../Views/_compiled/';

	static function Default() : void {
		$blade = new BladeOne(DefaultController::$views,DefaultController::$cache);
		echo $blade->run("_Default.view");
	}
}