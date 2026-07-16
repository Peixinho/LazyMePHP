<?php

declare(strict_types=1);

/**
 * LazyMePHP AutoRouter
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core;

use Core\Http\Request;
use Core\LazyMePHP;
use Core\CrudController;
use Core\Helpers\Helper;
use Pecee\SimpleRouter\SimpleRouter;
use eftec\bladeone\BladeOne;

/**
 * Registers standard CRUD web routes for tables at runtime — no code generation needed.
 *
 * Call from App/Routes/Routes.php:
 *
 *   AutoRouter::registerAll($blade);         // all tables known to the schema cache / DB
 *   AutoRouter::register('products', $blade); // single table
 *
 * To override behaviour for a specific table, create App/Controllers/TableName.php
 * extending Core\CrudController and implement the desired hooks.
 *
 * To replace the routes themselves (different URLs, extra endpoints, dropped
 * actions), create App/Routes/{table}.php — its presence completely replaces
 * the standard 6-route registration below for that table. Scaffold a starting
 * point with `php LazyMePHP make:router <table>`.
 */
class AutoRouter
{
    /**
     * Register the 6 standard CRUD web routes for a single table, unless
     * App/Routes/{table}.php exists, in which case that file fully replaces them.
     */
    public static function register(string $table, ?BladeOne $blade = null): void
    {
        // require_once (not require): public/index.php also globs every top-level
        // App/Routes/*.php file at boot, so this file may already have been loaded
        // by the time AutoRouter reaches it — require_once keeps it a no-op either way.
        $override = __DIR__ . "/../Routes/{$table}.php";
        if (is_file($override)) {
            require_once $override;
            return;
        }

        SimpleRouter::get("/$table", function () use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            $data       = $controller->index((int)($request->get('page') ?? 1), LazyMePHP::NRESULTS());
            echo BladeFactory::render($controller->viewName('index'), array_merge($data, [
                'current' => $request->get('page') ?? 1,
                'limit'   => LazyMePHP::NRESULTS(),
            ]));
        });

        SimpleRouter::get("/$table/new", function () use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            echo BladeFactory::render($controller->viewName('edit'), $controller->edit());
        });

        SimpleRouter::get("/$table/{id}/edit", function (string $id) use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            echo BladeFactory::render($controller->viewName('edit'), $controller->edit((int)$id));
        });

        SimpleRouter::post("/$table/{id}", function (string $id) use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            $controller->save((int)$id);
            Helper::redirect("/$table");
        })->addMiddleware(\Core\Security\CsrfMiddleware::class);

        SimpleRouter::post("/$table", function () use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            $controller->save();
            Helper::redirect("/$table");
        })->addMiddleware(\Core\Security\CsrfMiddleware::class);

        SimpleRouter::post("/$table/{id}/delete", function (string $id) use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            $controller->delete((int)$id);
            Helper::redirect("/$table");
        })->addMiddleware(\Core\Security\CsrfMiddleware::class);
    }

    /**
     * Register CRUD routes for all visible tables.
     * Uses schema cache file names when available, otherwise queries the DB.
     * Tables whose controller subclass sets $hidden = true are skipped.
     */
    public static function registerAll(?BladeOne $blade = null): void
    {
        foreach (Model::listTables() as $table) {
            if (CrudController::isHidden($table)) continue;
            self::register($table);
        }
    }
}
