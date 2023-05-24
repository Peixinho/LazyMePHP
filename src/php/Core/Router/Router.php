<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Core\Router;
use LazyMePHP\Config\Internal\APP;

class Route {

	private $controller;
	private $url;

	function __construct($controller, $url)
	{
		$this->controller = $controller;
		$this->url = $url;
	}

	function GetURL()
	{
		return $this->url;
	}

	function GetController()
	{
		return $this->controller;
	}
}

class Router {

	private static $Routes = array();
	private static $controllerArguments = array();
	private static $defaultRouting;
	private static $defaultRoutingName;

	static function GetRoutes()
	{
		return Router::$Routes;
	}

	static function SetDefaultRouting($name, $file)
	{
		Router::$defaultRoutingName = $name;
		Router::$defaultRouting = $file;
	}

	static function Create($controllerArgument, $controller, $file, $action=null, $value=true)
	{
		if (!array_key_exists($controllerArgument, Router::$Routes))
			Router::$Routes[$controllerArgument] = array();

		if (!array_key_exists($controller,Router::$Routes[$controllerArgument]))
			Router::$Routes[$controllerArgument][$controller][0] = new Route($controller, $file);

		if ($value!=null && $action!=null)
			Router::$Routes[$controllerArgument][$controller][1][$action][] = $value;

		// Update controllerArgument list to look for
		Router::$controllerArguments[] = array();
		foreach(Router::$Routes as $key => $route)
			array_push(Router::$controllerArguments, $key);
	}

	static function Dispatch($url, $method = NULL)
	{
		ob_start();

		$params = $url;
		if (!is_array($url)) {
			$url = substr($url,strrpos ($url ,'?')+1, strlen($url));
			parse_str($url, $params);
		}
		$paramFound = NULL;
		foreach($params as $param => $value) // Search for first matched controller argument
		{
			foreach(Router::$Routes as $key => $route)
			{
				if ($param == $key) // We have a match
				{
					$controller = $params[$param];
					$paramFound = $param;
					break;
				}
			}
			if ($paramFound) break;
		}

		// Check if Route has action defined
		$routeDefined = false;
		$found = false;
		if ($paramFound && is_array(Router::$Routes[$paramFound]) && array_key_exists($controller,Router::$Routes[$paramFound]) && sizeof(Router::$Routes[$paramFound][$controller])>1) {
			$routeDefined = true;
			if (!$method) { // Regular Controller with action defined Request
				foreach($params as $key=>$value) {
					if (array_key_exists($key,Router::$Routes[$paramFound][$controller][1])) {
						foreach(Router::$Routes[$paramFound][$controller][1][$key] as $v){
							if ($v==$value) {
								$found = true;
								break;
							}
						}
					}
				}
			} else { // API Request with defined Method
				if (array_key_exists($method,Router::$Routes[$paramFound][$controller][1])) {
					$found = true;
				}
			}
		}

		if ($paramFound && (($routeDefined && $found) || !$routeDefined) && ((is_array(Router::$Routes[$paramFound]) && array_key_exists($controller,Router::$Routes[$paramFound]))))
		{
			require_once Router::$Routes[$paramFound][$controller][0]->GetURL();
		} else {
			$controller = Router::$defaultRoutingName;
			require_once Router::$defaultRouting;
		}

		$GLOBALS['content'] = ob_get_clean();

		return $controller;
	}
}

?>
