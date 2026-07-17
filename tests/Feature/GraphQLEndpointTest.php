<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\GraphQL\Endpoint;

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
        email TEXT    NOT NULL
    )");
    $db->query("INSERT INTO demo_users (name, email) VALUES ('Alice', 'alice@test.com')");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

function callGraphqlEndpoint(string $body): array
{
    // Endpoint::handle() only ever calls http_response_code() explicitly on its
    // error path (500) — reset to a known baseline first so a prior test's
    // failure status can't leak into this assertion.
    http_response_code(200);

    ob_start();
    Endpoint::handle(['demo_users'], $body);
    $raw = ob_get_clean();

    return ['status' => http_response_code(), 'body' => json_decode($raw, true)];
}

describe('GraphQL Endpoint', function () {
    // Regression test for a bug where `new DisableIntrospection()` was called
    // with no constructor argument, throwing an ArgumentCountError on every
    // single request outside APP_ENV=development — i.e. the /graphql endpoint
    // 500'd unconditionally in production. GraphQLTest.php never caught this
    // because it calls SchemaBuilder + GQL::executeQuery directly, bypassing
    // Endpoint::handle() (and its dev/non-dev branching) entirely.
    it('answers a plain query in production without 500ing', function () {
        $_ENV['APP_ENV'] = 'production';

        $res = callGraphqlEndpoint(json_encode([
            'query' => '{ demoUsersList { id name } }',
        ]));

        expect($res['status'])->toBe(200);
        expect($res['body'])->not->toHaveKey('errors');
        expect($res['body']['data']['demoUsersList'][0]['name'])->toBe('Alice');
    });

    it('executes a valid query without error', function () {
        $res = callGraphqlEndpoint(json_encode([
            'query' => '{ demoUsersList { id name } }',
        ]));

        expect($res['status'])->toBe(200);
        expect($res['body'])->not->toHaveKey('errors');
        expect($res['body']['data']['demoUsersList'])->toHaveCount(1);
    });

    it('passes variables through to the query', function () {
        $res = callGraphqlEndpoint(json_encode([
            'query'     => 'query($id: ID!) { demoUsers(id: $id) { name } }',
            'variables' => ['id' => 1],
        ]));

        expect($res['body']['data']['demoUsers']['name'])->toBe('Alice');
    });

    it('rejects a query that exceeds MAX_COMPLEXITY via aliased field fan-out', function () {
        // No field on this schema defines a custom complexity function, so cost
        // is 1 per selected field node — aliasing the same scalar 250 times is
        // a reliable way to cross the MAX_COMPLEXITY=200 ceiling either way.
        $aliases = '';
        for ($i = 0; $i < 250; $i++) {
            $aliases .= "f{$i}: id ";
        }

        $res = callGraphqlEndpoint(json_encode([
            'query' => '{ demoUsersList { ' . $aliases . '} }',
        ]));

        expect($res['body'])->toHaveKey('errors');
        expect($res['body']['errors'][0]['message'])->toContain('Max query complexity should be 200');
    });

    it('rejects a query nested deeper than MAX_DEPTH', function () {
        $_ENV['APP_ENV'] = 'development'; // keep introspection enabled so only the depth error fires

        // __Type.ofType is self-referential, so nesting it 12 deep is valid
        // GraphQL regardless of the app's own (flat) table schema and reliably
        // blows past MAX_DEPTH=7.
        $nesting = str_repeat('ofType { ', 12) . 'name' . str_repeat(' }', 12);
        $res = callGraphqlEndpoint(json_encode([
            'query' => '{ __schema { types { fields { type { ' . $nesting . ' } } } } }',
        ]));

        expect($res['body'])->toHaveKey('errors');
        expect($res['body']['errors'][0]['message'])->toContain('Max query depth should be 7');
    });

    it('disables introspection outside development', function () {
        $_ENV['APP_ENV'] = 'production';

        $res = callGraphqlEndpoint(json_encode([
            'query' => '{ __schema { types { name } } }',
        ]));

        expect($res['body'])->toHaveKey('errors');
        expect($res['body']['errors'][0]['message'])->toContain('introspection is not allowed');
    });

    it('allows introspection in development', function () {
        $_ENV['APP_ENV'] = 'development';

        $res = callGraphqlEndpoint(json_encode([
            'query' => '{ __schema { types { name } } }',
        ]));

        expect($res['body'])->not->toHaveKey('errors');
    });

    it('strips debug trace details outside development', function () {
        $_ENV['APP_ENV'] = 'production';

        $res = callGraphqlEndpoint(json_encode([
            'query' => 'mutation { deleteDemoUsers(id: 999) }',
        ]));

        expect($res['body']['errors'][0]['extensions'] ?? [])->not->toHaveKey('trace');
    });

    it('includes debug trace details in development', function () {
        $_ENV['APP_ENV'] = 'development';

        $res = callGraphqlEndpoint(json_encode([
            'query' => 'mutation { deleteDemoUsers(id: 999) }',
        ]));

        expect($res['body']['errors'][0]['extensions'])->toHaveKey('trace');
    });

    it('returns a JSON error response for a malformed request body without crashing', function () {
        $res = callGraphqlEndpoint('{not valid json');

        expect($res['body'])->toBeArray();
        expect($res['body'])->toHaveKey('errors');
    });
});
