<?php

declare(strict_types=1);

// Proves Core\Auth\Gate — the single enforcement point requiredRoles*()/
// authorizeRecord() drive for BOTH GraphQL (Core\GraphQL\SchemaBuilder, see
// GraphQLAuthorizationTest.php) and the auto-wired web CRUD routes
// (Core\AutoRouter) — works from a purely session-style identity, with no
// Core\Auth\Auth (JWT) involved at all. That's the point: one requiredRoles()
// declaration on a controller now governs both surfaces, regardless of which
// of the two identity mechanisms authenticated the request.
//
// Write routes aren't dispatched end-to-end here: Core\Auth\Gate legitimately
// calls exit() on denial (Core\AutoRouter::enforce()), and a successful write
// ends in Pecee\Http\Response::redirect(), which also calls exit() — either
// would kill the test process. Those paths are verified instead via direct
// Gate calls (identical to what the route closures call) plus the standalone
// full-stack dispatch already exercised manually through the real Pecee
// router during development of this feature. The list route (GET, no
// redirect on success) is dispatched for real below, proving the wiring
// itself — not just the Gate logic in isolation.

namespace Controllers {
    class DemoWebRooms extends \Core\CrudController
    {
        protected static string $table = 'demo_web_rooms';

        public function requiredRolesForRead(): array
        {
            return [];
        }

        public function requiredRolesForWrite(): array
        {
            return ['Manager'];
        }
    }
}

namespace {

    use Core\LazyMePHP;
    use Core\Model;
    use Core\Auth\RBAC;
    use Core\Auth\Gate;
    use Core\Auth\AuthorizationException;
    use Core\AutoRouter;
    use Core\CrudController;
    use Core\Http\Request;
    use Pecee\SimpleRouter\SimpleRouter;

    beforeEach(function () {
        $_ENV['DB_TYPE']          = 'sqlite';
        $_ENV['DB_FILE_PATH']     = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        $_ENV['APP_ENV']          = 'testing';
        $_ENV['APP_ENCRYPTION']   = 'test-secret-at-least-32-characters-long';

        LazyMePHP::reset();
        Model::clearSchemaCache();
        RBAC::clearCache();
        SimpleRouter::router()->reset();
        new LazyMePHP();

        $db = LazyMePHP::DB_CONNECTION();
        $db->query('CREATE TABLE demo_web_rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $db->query("INSERT INTO demo_web_rooms (name) VALUES ('Room A')");

        RBAC::createRole('Staff', '');
        RBAC::createRole('Manager', '');
        RBAC::assignRole(1, 'Staff');
        RBAC::assignRole(2, 'Manager');
    });

    afterEach(function () {
        RBAC::$identityResolver = null;
        SimpleRouter::router()->reset();
    });

    function findRoute(string $url)
    {
        foreach (SimpleRouter::router()->getRoutes() as $route) {
            if (method_exists($route, 'getUrl') && $route->getUrl() === $url) {
                return $route;
            }
        }
        return null;
    }

    describe('RBAC::currentUserId() identity resolver chain', function () {
        it('prefers $identityResolver over Auth::id() (JWT)', function () {
            RBAC::$identityResolver = fn() => 1;
            expect(RBAC::currentUserId())->toBe(1);
        });

        it('falls back to Auth::id() (JWT) when the resolver yields null', function () {
            RBAC::$identityResolver = fn() => null;

            $jwt = new \Ahc\Jwt\JWT($_ENV['APP_ENCRYPTION'], 'HS256', 3600);
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $jwt->encode(['sub' => 1]);
            \Core\Auth\Auth::reset();

            expect(RBAC::currentUserId())->toBe(1);

            unset($_SERVER['HTTP_AUTHORIZATION']);
            \Core\Auth\Auth::reset();
        });
    });

    describe('Core\Auth\Gate — the shared enforcement point', function () {
        it('checkRoles() is a no-op when the required-roles list is empty', function () {
            RBAC::$identityResolver = fn() => null;
            Gate::checkRoles([], 'demo_web_rooms');
            expect(true)->toBeTrue();
        });

        it('checkRoles() throws a 401 when nobody is identified', function () {
            RBAC::$identityResolver = fn() => null;
            try {
                Gate::checkRoles(['Manager'], 'demo_web_rooms');
                expect(false)->toBeTrue('expected AuthorizationException');
            } catch (AuthorizationException $e) {
                expect($e->status)->toBe(401);
                expect($e->getMessage())->toContain('requires authentication');
            }
        });

        it('checkRoles() throws a 403 when identified but missing the role', function () {
            RBAC::$identityResolver = fn() => 1; // Staff, not Manager
            try {
                Gate::checkRoles(['Manager'], 'demo_web_rooms');
                expect(false)->toBeTrue('expected AuthorizationException');
            } catch (AuthorizationException $e) {
                expect($e->status)->toBe(403);
                expect($e->getMessage())->toContain('Manager');
            }
        });

        it('checkRoles() passes when the resolved identity has the role', function () {
            RBAC::$identityResolver = fn() => 2; // Manager
            Gate::checkRoles(['Manager'], 'demo_web_rooms');
            expect(true)->toBeTrue();
        });

        it('checkRecord() throws 403 when authorizeRecord() returns false', function () {
            $record = new Model('demo_web_rooms', 1);

            $fixture = new class ('demo_web_rooms', new Request()) extends CrudController {
                public function authorizeRecord(string $operation, Model $record): bool
                {
                    return false;
                }
            };

            try {
                Gate::checkRecord($fixture, 'update', $record, 'demo_web_rooms');
                expect(false)->toBeTrue('expected AuthorizationException');
            } catch (AuthorizationException $e) {
                expect($e->status)->toBe(403);
            }
        });
    });

    describe('Core\AutoRouter dispatches through the same Gate, driven by a session-style identity', function () {
        it('lets a Staff user (no read restriction) reach the real registered list route', function () {
            // No JWT anywhere — this is exactly the shape Tools\Auth::id() takes
            // in the real app (App/Routes/Routes.php wires it as the resolver).
            RBAC::$identityResolver = fn() => 1;

            AutoRouter::register('demo_web_rooms');
            $route = findRoute('/demo_web_rooms/');
            expect($route)->not->toBeNull();

            ob_start();
            ($route->getCallback())();
            $output = ob_get_clean();

            expect($output)->toContain('Room A');
        });

        it('requiredRolesForWrite() on the controller is what the write routes will check (verified via Gate — the actual route legitimately exit()s on denial/redirect, which would kill the test process)', function () {
            $controller = CrudController::forTable('demo_web_rooms', new Request());
            expect($controller->requiredRolesForWrite())->toBe(['Manager']);

            RBAC::$identityResolver = fn() => 1; // Staff
            expect(fn() => Gate::checkRoles($controller->requiredRolesForWrite(), 'demo_web_rooms'))
                ->toThrow(AuthorizationException::class);

            RBAC::$identityResolver = fn() => 2; // Manager
            Gate::checkRoles($controller->requiredRolesForWrite(), 'demo_web_rooms');
            expect(true)->toBeTrue();
        });
    });
}
