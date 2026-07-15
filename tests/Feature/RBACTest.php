<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Auth\Auth;
use Core\Auth\RBAC;

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';
    $_ENV['APP_ENCRYPTION']   = 'a-very-long-secret-key-at-least-32-chars!!';
    $_ENV['AUTH_TABLE']       = 'users';
    $_ENV['AUTH_USERNAME_COLUMN'] = 'email';
    $_ENV['AUTH_PASSWORD_COLUMN'] = 'password';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    Auth::reset();
    RBAC::clearCache();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();

    $db->query("CREATE TABLE users (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        email    TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )");
    $db->query("CREATE TABLE __AUTH_ROLES (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL UNIQUE,
        description TEXT NOT NULL DEFAULT ''
    )");
    $db->query("CREATE TABLE __AUTH_ROLE_PERMISSIONS (
        role_id    INTEGER NOT NULL,
        permission TEXT NOT NULL,
        PRIMARY KEY (role_id, permission)
    )");
    $db->query("CREATE TABLE __AUTH_USER_ROLES (
        user_id TEXT NOT NULL,
        role_id INTEGER NOT NULL,
        PRIMARY KEY (user_id, role_id)
    )");

    $hash = password_hash('secret', PASSWORD_DEFAULT);
    $db->query("INSERT INTO users (email, password) VALUES ('alice@example.com', ?)", [$hash]);
    $db->query("INSERT INTO users (email, password) VALUES ('bob@example.com', ?)", [$hash]);
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
    Auth::reset();
    RBAC::clearCache();
    unset($_ENV['AUTH_TABLE'], $_ENV['AUTH_USERNAME_COLUMN'], $_ENV['AUTH_PASSWORD_COLUMN'], $_ENV['APP_ENCRYPTION']);
});

describe('RBAC::createRole()', function () {
    it('creates a new role', function () {
        RBAC::createRole('admin', 'Full access');
        $roles = RBAC::allRoles();
        expect(array_column($roles, 'name'))->toContain('admin');
    });

    it('is idempotent — creating the same role twice does not error', function () {
        RBAC::createRole('editor');
        RBAC::createRole('editor');
        $roles = RBAC::allRoles();
        $editors = array_filter($roles, fn($r) => $r['name'] === 'editor');
        expect(count($editors))->toBe(1);
    });
});

describe('RBAC::grantPermission() / revokePermission()', function () {
    it('grants a permission to a role', function () {
        RBAC::createRole('editor');
        RBAC::grantPermission('editor', 'posts:write');

        RBAC::assignRole(1, 'editor');
        expect(RBAC::permissionsFor(1))->toContain('posts:write');
    });

    it('revokes a permission from a role', function () {
        RBAC::createRole('editor');
        RBAC::grantPermission('editor', 'posts:write');
        RBAC::revokePermission('editor', 'posts:write');

        RBAC::assignRole(1, 'editor');
        expect(RBAC::permissionsFor(1))->not->toContain('posts:write');
    });

    it('throws when granting to a non-existent role', function () {
        expect(fn() => RBAC::grantPermission('ghost', 'posts:write'))
            ->toThrow(\InvalidArgumentException::class);
    });
});

describe('RBAC::assignRole() / removeRole()', function () {
    it('assigns a role to a user', function () {
        RBAC::createRole('viewer');
        RBAC::assignRole(1, 'viewer');
        expect(RBAC::rolesFor(1))->toContain('viewer');
    });

    it('removes a role from a user', function () {
        RBAC::createRole('viewer');
        RBAC::assignRole(1, 'viewer');
        RBAC::removeRole(1, 'viewer');
        expect(RBAC::rolesFor(1))->not->toContain('viewer');
    });
});

describe('RBAC::can() / is() using Auth::id()', function () {
    it('can() returns true when user has the permission', function () {
        RBAC::createRole('admin');
        RBAC::grantPermission('admin', 'posts:delete');
        RBAC::assignRole(1, 'admin');

        // Fake being logged in as user 1
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . buildToken(1, 'alice@example.com');
        Auth::reset();
        RBAC::clearCache();

        expect(RBAC::can('posts:delete'))->toBeTrue();
    });

    it('can() returns false when the permission is not granted', function () {
        RBAC::createRole('editor');
        RBAC::grantPermission('editor', 'posts:write');
        RBAC::assignRole(2, 'editor');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . buildToken(1, 'alice@example.com');
        Auth::reset();
        RBAC::clearCache();

        expect(RBAC::can('posts:delete'))->toBeFalse();
    });

    it('is() returns true when user has the role', function () {
        RBAC::createRole('admin');
        RBAC::assignRole(1, 'admin');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . buildToken(1, 'alice@example.com');
        Auth::reset();
        RBAC::clearCache();

        expect(RBAC::is('admin'))->toBeTrue();
        expect(RBAC::is('editor'))->toBeFalse();
    });
});

describe('RBAC::deleteRole()', function () {
    it('removes the role and all its permissions and user assignments', function () {
        RBAC::createRole('temp');
        RBAC::grantPermission('temp', 'x:y');
        RBAC::assignRole(1, 'temp');

        RBAC::deleteRole('temp');

        expect(RBAC::rolesFor(1))->not->toContain('temp');
        $roles = RBAC::allRoles();
        expect(array_column($roles, 'name'))->not->toContain('temp');
    });
});

// ---------------------------------------------------------------------------
// Helper — build a real JWT for a user without going through HTTP
// ---------------------------------------------------------------------------
function buildToken(int $userId, string $email): string
{
    $jwt = new \Ahc\Jwt\JWT($_ENV['APP_ENCRYPTION'], 'HS256', (int)($_ENV['AUTH_TOKEN_TTL'] ?? 3600));
    return $jwt->encode(['sub' => $userId, 'username' => $email]);
}
