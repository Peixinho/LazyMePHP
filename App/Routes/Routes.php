<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Routes;

use Pecee\SimpleRouter\SimpleRouter;
use Core\LazyMePHP;

require_once __DIR__ . '/../Core/BladeFactory.php';
$blade = \Core\BladeFactory::getBlade();

SimpleRouter::get('/', function () use ($blade): void {
    echo $blade->run('_Index.index');
});

// Auto-wire CRUD routes + GraphQL for every table in the DB schema.
// To exclude a table, set `public static bool $hidden = true` in its controller subclass.
// To add custom routes, define them above or below this call.
LazyMePHP::boot($blade);
