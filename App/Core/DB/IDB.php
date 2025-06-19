<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Core\DB;

/**
 * Interface for Automatic DB Classes
 */
interface IDB
{
    public function Save() : mixed;
    public function Delete() : bool;
}
