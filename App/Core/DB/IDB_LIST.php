<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace DB;

/**
 * Interface for Automatic DB Classes Lists
 */
interface IDB_LIST
{
    public function FindAll();
    public function GetList(bool $buildForeignMembers = true, bool $serialize = false, array $mask = array());
}
