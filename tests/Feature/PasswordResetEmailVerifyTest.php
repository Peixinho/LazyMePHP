<?php

declare(strict_types=1);

use Core\Auth\Auth;
use Core\LazyMePHP;
use Core\Model;

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';
    $_ENV['APP_ENCRYPTION']   = str_repeat('x', 32);
    $_ENV['AUTH_TABLE']       = 'users';
    $_ENV['AUTH_USERNAME_COLUMN'] = 'email';
    $_ENV['AUTH_PASSWORD_COLUMN'] = 'password';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();
    Auth::reset();

    $db = LazyMePHP::DB_CONNECTION();

    $db->query("CREATE TABLE users (
        id                INTEGER PRIMARY KEY AUTOINCREMENT,
        email             TEXT NOT NULL UNIQUE,
        password          TEXT NOT NULL,
        email_verified_at DATETIME
    )");
    $db->query("INSERT INTO users (email, password) VALUES (?, ?)", [
        'alice@example.com',
        password_hash('secret', PASSWORD_BCRYPT),
    ]);

    $db->query("CREATE TABLE IF NOT EXISTS __AUTH_PASSWORD_RESETS (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        token_hash TEXT    NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at    DATETIME
    )");
    $db->query("CREATE TABLE IF NOT EXISTS __AUTH_EMAIL_VERIFICATIONS (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        token_hash TEXT    NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at    DATETIME
    )");
    $db->query("CREATE TABLE IF NOT EXISTS __AUTH_TOKENS (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL,
        token_hash  TEXT NOT NULL,
        expires_at  DATETIME,
        revoked_at  DATETIME,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
    Auth::reset();
});

describe('Password reset', function () {
    it('creates a reset token for a user', function () {
        $token = Auth::createPasswordResetToken(1);
        expect($token)->toBeString()->toHaveLength(64);

        $userId = Auth::validatePasswordResetToken($token);
        expect($userId)->not->toBeNull();
    });

    it('validates an unexpired token', function () {
        $token  = Auth::createPasswordResetToken(1);
        $userId = Auth::validatePasswordResetToken($token);
        expect((int)$userId)->toBe(1);
    });

    it('rejects an unknown token', function () {
        expect(Auth::validatePasswordResetToken('not_a_real_token'))->toBeNull();
    });

    it('consumes the token and changes the password', function () {
        $token = Auth::createPasswordResetToken(1);
        $ok    = Auth::consumePasswordResetToken($token, 'new_password_123');
        expect($ok)->toBeTrue();

        // Token must now be rejected (single-use)
        expect(Auth::validatePasswordResetToken($token))->toBeNull();

        // New password should work
        $db   = LazyMePHP::DB_CONNECTION();
        $r    = $db->query("SELECT password FROM users WHERE id = 1");
        $hash = $r->fetchArray()['password'];
        expect(password_verify('new_password_123', $hash))->toBeTrue();
    });

    it('rejects a consumed token on second use', function () {
        $token = Auth::createPasswordResetToken(1);
        Auth::consumePasswordResetToken($token, 'pass1');
        $ok = Auth::consumePasswordResetToken($token, 'pass2');
        expect($ok)->toBeFalse();
    });

    it('replaces old token when a new one is created', function () {
        $first  = Auth::createPasswordResetToken(1);
        $second = Auth::createPasswordResetToken(1);
        // Old token should be invalidated
        expect(Auth::validatePasswordResetToken($first))->toBeNull();
        expect(Auth::validatePasswordResetToken($second))->not->toBeNull();
    });
});

describe('Email verification', function () {
    it('creates an email verification token', function () {
        $token = Auth::createEmailVerificationToken(1);
        expect($token)->toBeString()->toHaveLength(64);
    });

    it('verifies email and returns user id', function () {
        $token  = Auth::createEmailVerificationToken(1);
        $userId = Auth::verifyEmail($token);
        expect((int)$userId)->toBe(1);
    });

    it('sets email_verified_at on the user row', function () {
        $token = Auth::createEmailVerificationToken(1);
        Auth::verifyEmail($token);

        $db  = LazyMePHP::DB_CONNECTION();
        $r   = $db->query("SELECT email_verified_at FROM users WHERE id = 1");
        $row = $r->fetchArray();
        expect($row['email_verified_at'])->not->toBeNull();
    });

    it('rejects an unknown token', function () {
        expect(Auth::verifyEmail('bad_token'))->toBeNull();
    });

    it('rejects a used token', function () {
        $token = Auth::createEmailVerificationToken(1);
        Auth::verifyEmail($token);
        expect(Auth::verifyEmail($token))->toBeNull();
    });
});
