<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Validations;

enum ValidationsMethod {
  case STRING;
  case LATIN;
  case FLOAT;
  case INT;
  case NOTNULL;
  case LENGTH;
  case DATE;
  case POSTAL;
  case EMAIL;
  case LATINPUNCTUATION;
  case REGEXP;
}
function ValidateField($var, $functions)
{
	$error = false;
	for ($i=0;$i<sizeof($functions);$i++)
	{
		match($functions[$i])
		{
      ValidationsMethod::STRING => $error = ValidateString($var),
      ValidationsMethod::LATIN => $error = ValidateLatinString($var),
      ValidationsMethod::FLOAT => $error = ValidateFloat($var),
      ValidationsMethod::INT => $error = ValidateInteger($var),
      ValidationsMethod::NOTNULL => $error = ValidateNotNull($var),
      ValidationsMethod::LENGTH => $error = ValidateNotNull($var,$functions[++$i]),
      ValidationsMethod::DATE => $error = ValidateDate($var),
      ValidationsMethod::POSTAL => $error = ValidatePostal($var),
      ValidationsMethod::EMAIL => $error = ValidateEmail($var),
      ValidationsMethod::LATINPUNCTUATION => $error = ValidateLatinPunctuationString($var),
      ValidationsMethod::REGEXP => $error = ValidateRegExp($var,$functions[++$i])
		};
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
