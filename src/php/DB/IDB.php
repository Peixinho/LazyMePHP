<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\ClassesBuilder;

/**
 * Interface for Automatic DB Classes
 */
interface IDB_CLASS
{
    public function Save();
    public function Delete();
}

/**
 * Interface for Automatic DB Classes Lists
 */
interface IDB_CLASS_LIST
{
    public function FindAll();
    public function GetList($buildForeignMembers = true);
}

?>
