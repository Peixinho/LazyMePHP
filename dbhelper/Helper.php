<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Helper;

function RMDIR($folder)
{
	// Return true because folder doesnt exist
	if (!is_dir($folder)) return true;

	// Check permissions
	if (is_dir($folder) && is_writable($folder))
	{
		return \rmdir($folder);
	}
	return false;
}
function MKDIR($name)
{
	// Check if exists and you have permissions
	if (!is_dir($name))
	{
		return \mkdir($name, 0775);
	}
	return false;
}
function UNLINK($file)
{
	// Return true because folder doesnt exist
	if (!is_file($file)) return true;

	// Check if exists and you have permissions
	if (is_file($file) && is_writable($file))
	{
		return \unlink($file);
	}
	return false;
}
function TOUCH($name)
{
	// Check if exists and you have permissions
	if (!is_file($name))
	{
		if (\touch($name))
			return chmod($name, 0775);
	}
	return false;
}
