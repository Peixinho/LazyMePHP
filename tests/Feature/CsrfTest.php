<?php

declare(strict_types=1);

use Core\Security\CsrfProtection;
use Core\Session\Session;

beforeEach(function () {
    $_ENV['APP_ENV'] = 'testing';
    // Reset the session singleton so each test starts clean
    $ref = new ReflectionClass(Session::class);
    $ref->getProperty('instance')->setValue(null, null);

    // Ensure session is available
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
});

afterEach(function () {
    CsrfProtection::clearToken();
    $_SESSION = [];
});

// ---------------------------------------------------------------------------

test('getToken() generates a 64-char hex token', function () {
    $token = CsrfProtection::getToken();
    expect($token)->toBeString()->toHaveLength(64);
    expect(ctype_xdigit($token))->toBeTrue();
});

test('getToken() returns the same token on repeated calls within the same request', function () {
    $a = CsrfProtection::getToken();
    $b = CsrfProtection::getToken();
    expect($a)->toBe($b);
});

test('getCurrentToken() returns the existing token without rotation', function () {
    $initial = CsrfProtection::getToken();
    $current = CsrfProtection::getCurrentToken();
    expect($current)->toBe($initial);
});

test('hasToken() is false before the first getToken() call', function () {
    expect(CsrfProtection::hasToken())->toBeFalse();
});

test('hasToken() is true after getToken()', function () {
    CsrfProtection::getToken();
    expect(CsrfProtection::hasToken())->toBeTrue();
});

test('verifyToken() returns true for the current token', function () {
    $token = CsrfProtection::getToken();
    expect(CsrfProtection::verifyToken($token))->toBeTrue();
});

test('verifyToken() returns false for an incorrect token', function () {
    CsrfProtection::getToken();
    expect(CsrfProtection::verifyToken('wrong-token'))->toBeFalse();
});

test('verifyToken() rotates the token after successful verification', function () {
    $original = CsrfProtection::getToken();
    CsrfProtection::verifyToken($original);
    $rotated = CsrfProtection::getCurrentToken();
    expect($rotated)->not->toBe($original);
    expect($rotated)->toHaveLength(64);
});

test('verifyToken() returns false when no token is stored', function () {
    expect(CsrfProtection::verifyToken('anything'))->toBeFalse();
});

test('clearToken() removes the stored token', function () {
    CsrfProtection::getToken();
    CsrfProtection::clearToken();
    expect(CsrfProtection::hasToken())->toBeFalse();
});

test('renderInput() returns HTML-safe string', function () {
    $out = CsrfProtection::renderInput();
    expect($out)->toBeString()->toHaveLength(64); // no HTML encoding needed for hex
    expect(ctype_xdigit($out))->toBeTrue();
});

test('token contains no special HTML characters', function () {
    $token = CsrfProtection::getToken();
    expect($token)->not->toContain('<');
    expect($token)->not->toContain('>');
    expect($token)->not->toContain('"');
    expect($token)->not->toContain("'");
    expect($token)->not->toContain('&');
});

test('verifyToken() is timing-safe (uses hash_equals)', function () {
    // We can only assert it behaves correctly — timing guarantees are runtime-level
    $token = CsrfProtection::getToken();

    // Correct prefix with wrong suffix should still fail
    $tampered = substr($token, 0, 32) . str_repeat('0', 32);
    expect(CsrfProtection::verifyToken($tampered))->toBeFalse();
});
