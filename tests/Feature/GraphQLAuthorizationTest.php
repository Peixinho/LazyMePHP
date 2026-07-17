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

    /**
     * Row-level ("edit your own, not anyone else's") authorization —
     * requiredRolesForWrite() alone can't express this: it has no idea which
     * record is being touched, only that a write to the table was attempted.
     * authorizeRecord() runs after that table-level check passes, with the
     * actual target record already loaded.
     */
    class DemoAccounts extends \Core\CrudController
    {
        protected static string $table = 'demo_accounts';

        public function requiredRolesForWrite(): array
        {
            return []; // any authenticated user may attempt a write — narrowed below
        }

        public function authorizeRecord(string $operation, \Core\Model $record): bool
        {
            if (\Core\Auth\RBAC::is('Manager')) {
                return true; // managers may touch anyone's record
            }
            return (string) \Core\Auth\Auth::id() === (string) $record->getPrimaryKey();
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
        $db->query('CREATE TABLE demo_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $db->query("INSERT INTO demo_accounts (id, name) VALUES (1, 'Alice')"); // row 1 "belongs to" user 1
        $db->query("INSERT INTO demo_accounts (id, name) VALUES (2, 'Bob')");   // row 2 "belongs to" user 2

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
        $schema = SchemaBuilder::build(['demo_rooms', 'demo_locked_rooms', 'demo_accounts']);
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

    describe('Row-level authorization via authorizeRecord()', function () {
        it('lets a user update their own record', function () {
            $result = gqlAs(1, 'mutation { updateDemoAccounts(id: 1, input: { name: "Alice Updated" }) { id name } }');
            expect($result)->not->toHaveKey('errors');
            expect($result['data']['updateDemoAccounts']['name'])->toBe('Alice Updated');
        });

        it('blocks a user from updating someone else\'s record', function () {
            $result = gqlAs(1, 'mutation { updateDemoAccounts(id: 2, input: { name: "Hijacked" }) { id name } }');
            expect($result)->toHaveKey('errors');
            expect($result['errors'][0]['message'])->toContain('Forbidden');

            // and the record is untouched
            $check = gqlAs(2, '{ demoAccounts(id: 2) { name } }');
            expect($check['data']['demoAccounts']['name'])->toBe('Bob');
        });

        it('blocks a user from deleting someone else\'s record', function () {
            $result = gqlAs(1, 'mutation { deleteDemoAccounts(id: 2) }');
            expect($result)->toHaveKey('errors');
            expect($result['errors'][0]['message'])->toContain('Forbidden');
        });

        it('lets a Manager write to any record, bypassing the ownership check', function () {
            $result = gqlAs(2, 'mutation { updateDemoAccounts(id: 1, input: { name: "Fixed by Manager" }) { id name } }');
            expect($result)->not->toHaveKey('errors');
            expect($result['data']['updateDemoAccounts']['name'])->toBe('Fixed by Manager');
        });

        it('requiredRolesForWrite() being empty means any authenticated user reaches authorizeRecord() at all', function () {
            // No role is required table-wide — user 1 isn't a Manager and has no
            // special role, yet still gets past the table-level check and is
            // narrowed only by authorizeRecord()'s ownership rule.
            $ownRecord = gqlAs(1, 'mutation { updateDemoAccounts(id: 1, input: { name: "Still mine" }) { id } }');
            expect($ownRecord)->not->toHaveKey('errors');
        });
    });
}
