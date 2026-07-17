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
use Core\Auth\Gate;
use Core\Auth\AuthorizationException;
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
 *
 * Every route here also enforces CrudController::requiredRoles*()/authorizeRecord()
 * via Core\Auth\Gate — the same declaration Core\GraphQL\SchemaBuilder enforces
 * for GraphQL. A table's access rules live in one place and govern both surfaces;
 * there's no separate "web roles" config to keep in sync.
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
            self::enforce($controller, $controller->requiredRolesForRead(), $table);
            $data       = $controller->index((int)($request->get('page') ?? 1), LazyMePHP::NRESULTS());
            echo BladeFactory::render($controller->viewName('index'), array_merge($data, [
                'current' => $request->get('page') ?? 1,
                'limit'   => LazyMePHP::NRESULTS(),
            ]));
        });

        SimpleRouter::get("/$table/new", function () use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            self::enforce($controller, $controller->requiredRolesForWrite(), $table);
            echo BladeFactory::render($controller->viewName('edit'), $controller->edit());
        });

        SimpleRouter::get("/$table/{id}/edit", function (string $id) use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            $record     = new Model($table, $id);
            self::enforce($controller, $controller->requiredRolesForWrite(), $table, $record, 'update');
            echo BladeFactory::render($controller->viewName('edit'), $controller->edit((int)$id));
        });

        // No ->addMiddleware(CsrfMiddleware::class) here: Kernel::loadRoutes() already
        // wraps every route from every App/Routes/*.php file in a group with
        // CsrfMiddleware applied (see routing.md — "you don't need to add it
        // manually"). Adding it again per-route ran the check twice; since
        // CsrfProtection::verifyToken() rotates the token on success (one-time use),
        // the first pass would consume it and the second pass would then compare
        // the original submitted token against the already-rotated session value
        // and fail — silently breaking every create/update/delete through the
        // auto-wired CRUD UI.
        SimpleRouter::post("/$table/{id}", function (string $id) use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            $record     = new Model($table, $id);
            self::enforce($controller, $controller->requiredRolesForWrite(), $table, $record, 'update');
            $controller->save((int)$id);
            Helper::redirect("/$table");
        });

        SimpleRouter::post("/$table", function () use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            self::enforce($controller, $controller->requiredRolesForWrite(), $table);
            $controller->save();
            Helper::redirect("/$table");
        });

        SimpleRouter::post("/$table/{id}/delete", function (string $id) use ($table): void {
            $request    = new Request();
            $controller = CrudController::forTable($table, $request);
            $record     = new Model($table, $id);
            self::enforce($controller, $controller->requiredRolesForWrite(), $table, $record, 'delete');
            $controller->delete((int)$id);
            Helper::redirect("/$table");
        });
    }

    /**
     * Enforces CrudController::requiredRolesForRead()/requiredRolesForWrite()
     * and, when $record is given (and exists), authorizeRecord() — the exact
     * same check Core\GraphQL\SchemaBuilder runs for GraphQL, so a table's
     * access rules are declared once on its controller and apply identically
     * to both surfaces. Emits 401/403 and exits on failure.
     */
    private static function enforce(
        CrudController $controller,
        array $roles,
        string $table,
        ?Model $record = null,
        string $recordOp = '',
    ): void {
        try {
            Gate::checkRoles($roles, $table);
            if ($record !== null && $record->getPrimaryKey() !== null) {
                Gate::checkRecord($controller, $recordOp, $record, $table);
            }
        } catch (AuthorizationException $e) {
            http_response_code($e->status);
            echo $e->getMessage();
            exit;
        }
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
