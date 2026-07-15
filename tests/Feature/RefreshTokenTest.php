<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Auth\Auth;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';
    $_ENV['APP_ENCRYPTION']   = 'a-very-long-secret-key-at-least-32-chars!!';
    $_ENV['AUTH_TABLE']       = 'users';
    $_ENV['AUTH_USERNAME_COLUMN'] = 'email';
    $_ENV['AUTH_PASSWORD_COLUMN'] = 'password';
    $_ENV['AUTH_TOKEN_TTL']   = '3600';
    $_ENV['AUTH_REFRESH_TTL'] = '2592000';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    Auth::reset();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();

    $db->query("CREATE TABLE users (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        email    TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )");

    $db->query("CREATE TABLE __AUTH_TOKENS (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    TEXT NOT NULL,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at TEXT NOT NULL,
        revoked_at TEXT NULL DEFAULT NULL,
        created_at TEXT NOT NULL,
        ip_address TEXT NOT NULL DEFAULT '',
        user_agent TEXT NULL
    )");

    $hash = password_hash('secret', PASSWORD_DEFAULT);
    $db->query("INSERT INTO users (email, password) VALUES ('alice@example.com', ?)", [$hash]);
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
    Auth::reset();
    unset(
        $_ENV['AUTH_TABLE'], $_ENV['AUTH_USERNAME_COLUMN'], $_ENV['AUTH_PASSWORD_COLUMN'],
        $_ENV['AUTH_TOKEN_TTL'], $_ENV['AUTH_REFRESH_TTL'], $_ENV['APP_ENCRYPTION'],
    );
});

// ---------------------------------------------------------------------------
// Auth::login()
// ---------------------------------------------------------------------------

describe('Auth::login()', function () {
    it('returns access + refresh tokens on valid credentials', function () {
        $result = Auth::login('alice@example.com', 'secret', '127.0.0.1', 'TestAgent/1.0');

        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['access_token', 'token_type', 'expires_in', 'refresh_token', 'refresh_expires_in']);
        expect($result['token_type'])->toBe('Bearer');
        expect($result['expires_in'])->toBe(3600);
        expect($result['refresh_expires_in'])->toBe(2592000);
        expect(strlen($result['access_token']))->toBeGreaterThan(0);
        expect(strlen($result['refresh_token']))->toBe(64); // 32 random bytes hex-encoded
    });

    it('returns false on wrong password', function () {
        $result = Auth::login('alice@example.com', 'wrong', '127.0.0.1');
        expect($result)->toBeFalse();
    });

    it('returns false for unknown user', function () {
        $result = Auth::login('nobody@example.com', 'secret');
        expect($result)->toBeFalse();
    });

    it('stores a hashed refresh token in __AUTH_TOKENS', function () {
        $result = Auth::login('alice@example.com', 'secret', '10.0.0.1', 'TestUA');

        $db = LazyMePHP::DB_CONNECTION();
        $r  = $db->query('SELECT * FROM __AUTH_TOKENS WHERE user_id = ?', ['1']);
        $row = $r->fetchArray();

        expect($row)->not->toBeFalse();
        expect($row['token_hash'])->toBe(hash('sha256', $result['refresh_token']));
        expect($row['ip_address'])->toBe('10.0.0.1');
        expect($row['user_agent'])->toBe('TestUA');
        expect($row['revoked_at'])->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Auth::createRefreshToken()
// ---------------------------------------------------------------------------

describe('Auth::createRefreshToken()', function () {
    it('returns a 64-character hex token', function () {
        $token = Auth::createRefreshToken(1);
        expect(strlen($token))->toBe(64);
        expect(ctype_xdigit($token))->toBeTrue();
    });

    it('stores only the SHA-256 hash, not the raw token', function () {
        $token = Auth::createRefreshToken(1);
        $hash  = hash('sha256', $token);

        $db  = LazyMePHP::DB_CONNECTION();
        $r   = $db->query('SELECT token_hash FROM __AUTH_TOKENS WHERE token_hash = ?', [$hash]);
        $row = $r->fetchArray();

        expect($row)->not->toBeFalse();
        expect($row['token_hash'])->toBe($hash);
    });
});

// ---------------------------------------------------------------------------
// Auth::refresh()
// ---------------------------------------------------------------------------

describe('Auth::refresh()', function () {
    it('returns new access + refresh tokens for a valid token', function () {
        $login   = Auth::login('alice@example.com', 'secret');
        $oldRef  = $login['refresh_token'];

        Auth::reset();
        $result = Auth::refresh($oldRef);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['access_token', 'refresh_token']);
        expect($result['refresh_token'])->not->toBe($oldRef);
    });

    it('revokes the old token after refresh (rotation)', function () {
        $login  = Auth::login('alice@example.com', 'secret');
        $oldRef = $login['refresh_token'];
        $oldHash = hash('sha256', $oldRef);

        Auth::refresh($oldRef);

        $db  = LazyMePHP::DB_CONNECTION();
        $r   = $db->query('SELECT revoked_at FROM __AUTH_TOKENS WHERE token_hash = ?', [$oldHash]);
        $row = $r->fetchArray();

        expect($row['revoked_at'])->not->toBeNull();
    });

    it('prevents reuse of a rotated token', function () {
        $login  = Auth::login('alice@example.com', 'secret');
        $oldRef = $login['refresh_token'];

        Auth::refresh($oldRef);   // rotates
        $result = Auth::refresh($oldRef); // second use — must fail

        expect($result)->toBeFalse();
    });

    it('returns false for an unknown token', function () {
        $result = Auth::refresh(str_repeat('a', 64));
        expect($result)->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// Auth::revokeRefreshToken()
// ---------------------------------------------------------------------------

describe('Auth::revokeRefreshToken()', function () {
    it('marks the token as revoked', function () {
        $token = Auth::createRefreshToken(1);
        $hash  = hash('sha256', $token);

        Auth::revokeRefreshToken($token);

        $db  = LazyMePHP::DB_CONNECTION();
        $r   = $db->query('SELECT revoked_at FROM __AUTH_TOKENS WHERE token_hash = ?', [$hash]);
        $row = $r->fetchArray();

        expect($row['revoked_at'])->not->toBeNull();
    });

    it('makes the token unusable for refresh', function () {
        $login = Auth::login('alice@example.com', 'secret');
        $token = $login['refresh_token'];

        Auth::revokeRefreshToken($token);
        $result = Auth::refresh($token);

        expect($result)->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// Auth::revokeAllTokens()
// ---------------------------------------------------------------------------

describe('Auth::revokeAllTokens()', function () {
    it('revokes every active token for the user', function () {
        Auth::createRefreshToken(1);
        Auth::createRefreshToken(1);
        Auth::createRefreshToken(1);

        Auth::revokeAllTokens(1);

        $db = LazyMePHP::DB_CONNECTION();
        $r  = $db->query(
            'SELECT COUNT(*) as cnt FROM __AUTH_TOKENS WHERE user_id = ? AND revoked_at IS NULL',
            ['1']
        );
        $row = $r->fetchArray();

        expect((int)$row['cnt'])->toBe(0);
    });

    it('does not revoke tokens belonging to other users', function () {
        Auth::createRefreshToken(1);
        Auth::createRefreshToken(2);

        Auth::revokeAllTokens(1);

        $db = LazyMePHP::DB_CONNECTION();
        $r  = $db->query(
            'SELECT COUNT(*) as cnt FROM __AUTH_TOKENS WHERE user_id = ? AND revoked_at IS NULL',
            ['2']
        );
        $row = $r->fetchArray();

        expect((int)$row['cnt'])->toBe(1);
    });
});
