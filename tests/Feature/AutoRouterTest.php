<?php

declare(strict_types=1);

use Core\AutoRouter;
use Pecee\SimpleRouter\SimpleRouter;

function routerOverridePath(string $table): string
{
    return __DIR__ . "/../../App/Routes/{$table}.php";
}

beforeEach(function () {
    SimpleRouter::router()->reset();
});

afterEach(function () {
    SimpleRouter::router()->reset();
    foreach (['router_test_table', 'router_test_table_preloaded'] as $t) {
        $f = routerOverridePath($t);
        if (is_file($f)) unlink($f);
    }
});

function registeredUrls(): array
{
    return array_map(
        fn($r) => $r->getUrl(),
        array_filter(SimpleRouter::router()->getRoutes(), fn($r) => method_exists($r, 'getUrl'))
    );
}

describe('AutoRouter', function () {
    it('registers the 6 standard CRUD routes when no override file exists', function () {
        AutoRouter::register('router_test_table');

        $urls = registeredUrls();

        expect($urls)->toContain('/router_test_table/');
        expect($urls)->toContain('/router_test_table/new/');
        expect($urls)->toContain('/router_test_table/{id}/edit/');
        expect($urls)->toContain('/router_test_table/{id}/');
        expect($urls)->toContain('/router_test_table/{id}/delete/');
        expect(count($urls))->toBe(6); // GET list, GET new, GET edit, POST update, POST create, POST delete
    });

    it('fully replaces the standard routes when App/Routes/{table}.php exists', function () {
        file_put_contents(routerOverridePath('router_test_table'), <<<'PHP'
<?php
use Pecee\SimpleRouter\SimpleRouter;
SimpleRouter::get('/router_test_table/custom-only', function () {});
PHP
        );

        AutoRouter::register('router_test_table');

        $urls = registeredUrls();

        expect($urls)->toBe(['/router_test_table/custom-only/']);
        expect($urls)->not->toContain('/router_test_table/');
        expect($urls)->not->toContain('/router_test_table/new/');
    });

    it('does not double-register routes when the override was already loaded (public/index.php globs App/Routes/*.php too)', function () {
        // Distinct table/path from the other tests: PHP's require_once registry is
        // keyed by realpath and persists for the process, regardless of the file
        // being deleted/recreated — reusing a path another test already required
        // would make this assertion pass for the wrong reason.
        $path = routerOverridePath('router_test_table_preloaded');
        file_put_contents($path, <<<'PHP'
<?php
use Pecee\SimpleRouter\SimpleRouter;
SimpleRouter::get('/router_test_table_preloaded/custom-only', function () {});
PHP
        );

        // Simulate public/index.php's blanket `foreach (glob('App/Routes/*.php')) require_once`
        // reaching this file before AutoRouter::register() does.
        require_once $path;
        AutoRouter::register('router_test_table_preloaded');

        $urls = registeredUrls();

        expect($urls)->toBe(['/router_test_table_preloaded/custom-only/']);
    });
});
