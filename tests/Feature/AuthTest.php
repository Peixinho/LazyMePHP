<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Auth\Auth;

// A 32+ char key required by the JWT secret validation
const AUTH_TEST_SECRET = 'test-secret-key-that-is-long-enough-32+';

beforeEach(function () {
    $_ENV['DB_TYPE']              = 'sqlite';
    $_ENV['DB_FILE_PATH']         = ':memory:';
    $_ENV['APP_ACTIVITY_LOG']     = 'false';
    $_ENV['APP_ENV']              = 'testing';
    $_ENV['APP_ENCRYPTION']       = AUTH_TEST_SECRET;
    $_ENV['AUTH_TABLE']           = 'users';
    $_ENV['AUTH_USERNAME_COLUMN'] = 'email';
    $_ENV['AUTH_PASSWORD_COLUMN'] = 'password';
    $_ENV['AUTH_TOKEN_TTL']       = '3600';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    Auth::reset();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE users (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        email    TEXT    NOT NULL UNIQUE,
        password TEXT    NOT NULL,
        name     TEXT
    )");

    $hash = Auth::hashPassword('secret123');
    $db->query("INSERT INTO users (email, password, name) VALUES (?, ?, ?)", [
        'alice@test.com', $hash, 'Alice',
    ]);
});

afterEach(function () {
    unset($_SERVER['HTTP_AUTHORIZATION']);
    Auth::reset();
    LazyMePHP::reset();
    Model::clearSchemaCache();
    unset(
        $_ENV['AUTH_TABLE'],
        $_ENV['AUTH_USERNAME_COLUMN'],
        $_ENV['AUTH_PASSWORD_COLUMN'],
        $_ENV['AUTH_TOKEN_TTL'],
    );
});

describe('Auth', function () {
    describe('attempt()', function () {
        it('returns a JWT string for valid credentials', function () {
            $token = Auth::attempt('alice@test.com', 'secret123');
            expect($token)->toBeString();
            expect(substr_count($token, '.'))->toBe(2); // header.payload.signature
        });

        it('returns false for a wrong password', function () {
            expect(Auth::attempt('alice@test.com', 'wrong'))->toBeFalse();
        });

        it('returns false for an unknown email', function () {
            expect(Auth::attempt('nobody@test.com', 'secret123'))->toBeFalse();
        });
    });

    describe('user() / check() / id()', function () {
        it('returns null when no Authorization header is present', function () {
            expect(Auth::user())->toBeNull();
            expect(Auth::check())->toBeFalse();
        });

        it('resolves the authenticated user from a valid token', function () {
            $token = Auth::attempt('alice@test.com', 'secret123');
            $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";
            Auth::reset();

            $user = Auth::user();
            expect($user)->toBeArray();
            expect($user['email'])->toBe('alice@test.com');
            expect($user['name'])->toBe('Alice');
        });

        it('strips the password column from the resolved user', function () {
            $token = Auth::attempt('alice@test.com', 'secret123');
            $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";
            Auth::reset();

            expect(Auth::user())->not->toHaveKey('password');
        });

        it('returns true from check() with a valid token', function () {
            $token = Auth::attempt('alice@test.com', 'secret123');
            $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";
            Auth::reset();

            expect(Auth::check())->toBeTrue();
        });

        it('returns false from check() with a tampered token', function () {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid.token.here';
            Auth::reset();

            expect(Auth::check())->toBeFalse();
        });

        it('returns the user id from id()', function () {
            $token = Auth::attempt('alice@test.com', 'secret123');
            $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";
            Auth::reset();

            expect((int)Auth::id())->toBe(1);
        });

        it('caches the user lookup within the same request', function () {
            $token = Auth::attempt('alice@test.com', 'secret123');
            $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";
            Auth::reset();

            $first  = Auth::user();
            $second = Auth::user(); // should not hit DB again
            expect($first)->toBe($second);
        });
    });

    describe('hashPassword()', function () {
        it('produces a hash verifiable by password_verify()', function () {
            $hash = Auth::hashPassword('mypassword');
            expect(password_verify('mypassword', $hash))->toBeTrue();
            expect(password_verify('wrong', $hash))->toBeFalse();
        });

        it('produces different hashes for the same password (bcrypt salting)', function () {
            $a = Auth::hashPassword('same');
            $b = Auth::hashPassword('same');
            expect($a)->not->toBe($b);
        });
    });

    describe('bearerToken()', function () {
        it('extracts the token from a well-formed Authorization header', function () {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc.def.ghi';
            expect(Auth::bearerToken())->toBe('abc.def.ghi');
        });

        it('returns null when the header is absent', function () {
            unset($_SERVER['HTTP_AUTHORIZATION']);
            expect(Auth::bearerToken())->toBeNull();
        });

        it('returns null for a non-Bearer scheme', function () {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
            expect(Auth::bearerToken())->toBeNull();
        });
    });
});
