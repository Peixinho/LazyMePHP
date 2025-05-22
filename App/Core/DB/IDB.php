<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace DB;

/**
 * Interface for Automatic DB Classes
 */
interface IDB
{
    public function Save();
    public function Delete();
}
