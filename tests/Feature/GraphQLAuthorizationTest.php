<?php

declare(strict_types=1);

// CrudController::forTable() resolves a custom controller by looking up
// "Controllers\{StudlyCase(table)}" specifically — these fixtures have to live
// in that namespace (not the file's default global namespace) or forTable()
// silently falls back to the generic controller, whose requiredRoles*() always
// return [] regardless of what's declared on the class below.
namespace Controllers {

    /**
     * requiredRolesForRead()/requiredRolesForWrite() let a table have asymmetric
     * access — e.g. any authenticated user can browse it, but only a manager
     * role can create/update/delete. requiredRoles() alone can't express that:
     * it's a single list applied identically to every operation.
     */
    class DemoRooms extends \Core\CrudController
    {
        protected static string $table = 'demo_rooms';

        public function requiredRolesForRead(): array
        {
            return []; // anyone authenticated may browse
        }

        public function requiredRolesForWrite(): array
        {
            return ['Manager'];
        }
    }

    /** requiredRoles() alone still applies to both read and write, unchanged. */
    class DemoLockedRooms extends \Core\CrudController
    {
        protected static string $table = 'demo_locked_rooms';

        public function requiredRoles(): array
        {
            return ['Manager'];
        }
    }
}

namespace {

    use Core\LazyMePHP;
    use Core\Model;
    use Core\Auth\Auth;
    use Core\Auth\RBAC;
    use Core\GraphQL\SchemaBuilder;
    use GraphQL\GraphQL as GQL;

    beforeEach(function () {
        $_ENV['DB_TYPE']          = 'sqlite';
        $_ENV['DB_FILE_PATH']     = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        $_ENV['APP_ENV']          = 'testing';
        $_ENV['APP_ENCRYPTION']   = 'test-secret-at-least-32-characters-long';

        LazyMePHP::reset();
        Model::clearSchemaCache();
        RBAC::clearCache();
        Auth::reset();
        new LazyMePHP();

        $db = LazyMePHP::DB_CONNECTION();
        $db->query('CREATE TABLE demo_rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $db->query("INSERT INTO demo_rooms (name) VALUES ('Room A')");
        $db->query('CREATE TABLE demo_locked_rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $db->query("INSERT INTO demo_locked_rooms (name) VALUES ('Vault')");

        RBAC::createRole('Staff', '');
        RBAC::createRole('Manager', '');
        RBAC::assignRole(1, 'Staff');
        RBAC::assignRole(2, 'Manager');
    });

    afterEach(function () {
        LazyMePHP::reset();
        Model::clearSchemaCache();
        RBAC::clearCache();
        Auth::reset();
        unset($_SERVER['HTTP_AUTHORIZATION']);
    });

    /** Simulates JwtMiddleware having already validated a token for $userId. */
    function actingAsJwt(int $userId): void
    {
        $jwt = new \Ahc\Jwt\JWT($_ENV['APP_ENCRYPTION'], 'HS256', 3600);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $jwt->encode(['sub' => $userId]);
        Auth::reset();
    }

    function gqlAs(int $userId, string $query): array
    {
        actingAsJwt($userId);
        $schema = SchemaBuilder::build(['demo_rooms', 'demo_locked_rooms']);
        return GQL::executeQuery($schema, $query)->toArray();
    }

    describe('Asymmetric read/write GraphQL authorization', function () {
        it('lets a Staff user (no role restriction on read) browse the table', function () {
            $result = gqlAs(1, '{ demoRoomsList { id name } }');
            expect($result)->not->toHaveKey('errors');
            expect($result['data']['demoRoomsList'])->toHaveCount(1);
        });

        it('blocks that same Staff user from writing to it', function () {
            $result = gqlAs(1, 'mutation { createDemoRooms(input: { name: "Room B" }) { id } }');
            expect($result)->toHaveKey('errors');
            expect($result['errors'][0]['message'])->toContain('Forbidden');
            expect($result['errors'][0]['message'])->toContain('Manager');
        });

        it('lets a Manager both read and write', function () {
            $read = gqlAs(2, '{ demoRoomsList { id name } }');
            expect($read)->not->toHaveKey('errors');

            $write = gqlAs(2, 'mutation { createDemoRooms(input: { name: "Room B" }) { id name } }');
            expect($write)->not->toHaveKey('errors');
            expect($write['data']['createDemoRooms']['name'])->toBe('Room B');
        });

        it('requiredRoles() alone still restricts both read and write identically', function () {
            $read = gqlAs(1, '{ demoLockedRoomsList { id } }');
            expect($read)->toHaveKey('errors');
            expect($read['errors'][0]['message'])->toContain('Forbidden');

            $write = gqlAs(1, 'mutation { deleteDemoLockedRooms(id: 1) }');
            expect($write)->toHaveKey('errors');
            expect($write['errors'][0]['message'])->toContain('Forbidden');

            $managerRead = gqlAs(2, '{ demoLockedRoomsList { id name } }');
            expect($managerRead)->not->toHaveKey('errors');
        });
    });
}
