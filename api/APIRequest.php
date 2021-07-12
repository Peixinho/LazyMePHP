<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\API;
use \LazyMePHP\Config\Internal\APP;

class APIRequest {

	function POST($query, $body)
	{
		header("HTTP/1.1 204 OK");
		return array("status" => 0);
	}
	function GET($query, $body)
	{
		header("HTTP/1.1 204 OK");
		return array("status" => 0);
	}
	function PUT($query, $body)
	{
		header("HTTP/1.1 204 OK");
		return array("status" => 0);
	}
	function DELETE($query, $body)
	{
		header("HTTP/1.1 204 OK");
		return array("status" => 0);
	}
}
?>
