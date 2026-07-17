<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\GraphQL\SchemaBuilder;
use GraphQL\GraphQL as GQL;

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE demo_users (
        id    INTEGER PRIMARY KEY AUTOINCREMENT,
        name  TEXT    NOT NULL,
        email TEXT    NOT NULL,
        age   INTEGER
    )");
    $db->query("INSERT INTO demo_users (name, email, age) VALUES ('Alice', 'alice@test.com', 30)");
    $db->query("INSERT INTO demo_users (name, email, age) VALUES ('Bob',   'bob@test.com',   25)");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

function gql(string $query, ?array $variables = null): array
{
    $schema = SchemaBuilder::build(['demo_users']);
    return GQL::executeQuery($schema, $query, null, null, $variables)->toArray();
}

describe('GraphQL', function () {
    describe('queries', function () {
        it('lists all records', function () {
            $result = gql('{ demoUsersList { id name email } }');
            expect($result)->not->toHaveKey('errors');
            expect($result['data']['demoUsersList'])->toHaveCount(2);
            expect($result['data']['demoUsersList'][0]['name'])->toBe('Alice');
        });

        it('paginates with page and limit', function () {
            $result = gql('{ demoUsersList(page: 1, limit: 1) { name } }');
            expect($result['data']['demoUsersList'])->toHaveCount(1);
        });

        it('fetches a single record by id', function () {
            $result = gql('{ demoUsers(id: 1) { id name email } }');
            expect($result)->not->toHaveKey('errors');
            expect($result['data']['demoUsers']['name'])->toBe('Alice');
        });

        it('returns null for a missing id', function () {
            $result = gql('{ demoUsers(id: 999) { id name } }');
            expect($result)->not->toHaveKey('errors');
            expect($result['data']['demoUsers'])->toBeNull();
        });

        it('returns id as a string (GraphQL ID type)', function () {
            $result = gql('{ demoUsers(id: 1) { id } }');
            expect($result['data']['demoUsers']['id'])->toBe('1');
        });
    });

    describe('mutations', function () {
        it('creates a new record', function () {
            $result = gql('mutation {
                createDemoUsers(input: { name: "Carol", email: "carol@test.com", age: 28 }) {
                    id name email age
                }
            }');
            expect($result)->not->toHaveKey('errors');
            $created = $result['data']['createDemoUsers'];
            expect($created['name'])->toBe('Carol');
            expect($created['email'])->toBe('carol@test.com');
            expect((int)$created['id'])->toBeGreaterThan(0);
        });

        it('updates an existing record', function () {
            $result = gql('mutation {
                updateDemoUsers(id: 1, input: { name: "Alice Updated" }) { id name }
            }');
            expect($result)->not->toHaveKey('errors');
            expect($result['data']['updateDemoUsers']['name'])->toBe('Alice Updated');

            $reloaded = new Model('demo_users', 1);
            expect($reloaded->name)->toBe('Alice Updated');
        });

        it('deletes a record', function () {
            $result = gql('mutation { deleteDemoUsers(id: 2) }');
            expect($result)->not->toHaveKey('errors');
            expect($result['data']['deleteDemoUsers'])->toBeTrue();

            $gone = new Model('demo_users', 2);
            expect($gone->getPrimaryKey())->toBeNull();
        });
    });

    describe('exposedFields()', function () {
        it('hides columns excluded by the controller', function () {
            // Anonymous controller that hides 'age'
            $controller = new class('demo_users', new \Core\Http\Request()) extends \Core\CrudController {
                public function exposedFields(): array { return ['id', 'name', 'email']; }
            };

            $schema = Model::schemaFor('demo_users');
            $exposed = $controller->exposedFields();
            $filtered = array_intersect_key($schema, array_flip($exposed));

            expect($filtered)->toHaveKey('id');
            expect($filtered)->toHaveKey('name');
            expect($filtered)->not->toHaveKey('age');
        });
    });
});
