<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Core\Validations;

use  \LazyMePHP\Core\Enum\Enum;

$ValidationsMethod = new Enum();
$ValidationsMethod->Add("STRING",0);
$ValidationsMethod->Add("LATIN",1);
$ValidationsMethod->Add("FLOAT",2);
$ValidationsMethod->Add("INT",3);
$ValidationsMethod->Add("NOTNULL",4);
$ValidationsMethod->Add("LENGTH",5);
$ValidationsMethod->Add("DATE",6);
$ValidationsMethod->Add("POSTAL",7);
$ValidationsMethod->Add("EMAIL",8);
$ValidationsMethod->Add("LATINPUNCTUATION",9);
$ValidationsMethod->Add("REGEXP",10);

function ValidateField($var, $functions)
{
	$error = false;
	for ($i=0;$i<sizeof($functions);$i++)
	{
		switch($functions[$i])
		{
			case "STRING":
				$error = (ValidateString($var));
			break;
			case "LATIN":
				$error = (ValidateLatinString($var));
			break;
			case "FLOAT":
				$error = (ValidateFloat($var));
			break;
			case "INT":
				$error = (ValidateInteger($var));
			break;
			case "NOTNULL":
				$error = (ValidateNotNull($var));
			break;
			case "LENGTH":
				$error = (ValidateLength($var,$functions[++$i]));
			break;
			case "DATE":
				$error = (ValidateDate($var));
			break;
			case "POSTAL":
				$error = (ValidatePostal($var));
			break;
			case "EMAIL":
				$error = (ValidateEmail($var));
			break;
			case "LATINPUNCTUATION":
				$error = (ValidateLatinPunctuationString($var));
			break;	
			case "REGEXP":
				$error = (ValidateRegExp($var, $functions[++$i]));
			break;	
		}
	}
	return $error;
}

function ValidateNotNull($value)
{
	return (isset($value)); 
}
function ValidateFloat($value)
{
	$reg = "/^[+-]?(?=.)(?:\d+,)*\d*(?:\.\d+)?$/";
	return ValidateRegExp($value, $reg);
}
function ValidateInteger($value)
{
	$reg = "/^\d+$/";
	return ValidateRegExp($value, $reg);
}
function ValidateString($value)
{
	$reg = "/^[a-zA-Z0-9](?:([a-zA-Z0-9\x20]+$)?$)/";
	return ValidateRegExp($value, $reg);
}
function ValidateLatinString($value)
{
	$reg = "/^[A-zÀ-ú0-9 ]+$/";
	return ValidateRegExp($value, $reg);
}
function ValidateLatinPunctuationString($value)
{
	$reg = "/^[A-zÀ-ú0-9 .:,;?!~+-€@#%&\/\\_\-\*\n\r]+$/";
	return ValidateRegExp($value, $reg);
}
function ValidateLength($value,$size)
{
	return (strlen($value)==$size);
}
function ValidateDate($value)
{
	$reg = "/^[0-9][0-9]\/[0-9][0-9]\/[0-9][0-9][0-9][0-9]+$/";
	return ValidateRegExp($value, $reg);
}
function ValidatePostal($value)
{
	$reg = "/^[0-9][0-9][0-9][0-9]-[0-9][0-9][0-9]$/";
	return ValidateRegExp($value, $reg);
}
function ValidateEmail($value)
{
	$reg = "/^([a-zA-Z0-9]+[\.|_|\-|£|$|%|&]{0,1})*[a-zA-Z0-9]{1}@([a-zA-Z0-9]+[\.|_|\-|£|$|%|&]{0,1})*([\.]{1}([a-zA-Z]{2,4}))$/";
	return ValidateRegExp($value, $reg);
}
function ValidateRegExp($value, $regexp)
{
	if ($value)
	{
		return sizeof(preg_match($regexp, $value, $match))>0;
	}
	return true;
}
?>
