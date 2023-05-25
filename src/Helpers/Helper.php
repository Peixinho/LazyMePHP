<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace LazyMePHP\Helper;

function TransformDateDDMMYYYtoYYYMMDD($date)
{
	return substr($date,6,4)."/".substr($date,3,2)."/".substr($date,0,2);
}
function TransformDateYYYMMDDtoDDMMYYY($date)
{
	return substr($date,8,2)."/".substr($date,5,2)."/".substr($date,0,4);
}
?>
