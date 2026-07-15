<?php

declare(strict_types=1);

use Core\Http\Request;

// ---------------------------------------------------------------------------
// Construction via create()
// ---------------------------------------------------------------------------

test('create() builds a GET request', function () {
    $req = Request::create('/users', 'GET', ['page' => '2']);
    expect($req->method())->toBe('GET');
    expect($req->path())->toBe('/users');
    expect($req->query('page'))->toBe('2');
});

test('create() builds a POST request', function () {
    $req = Request::create('/users', 'POST', ['name' => 'Alice']);
    expect($req->isPost())->toBeTrue();
    expect($req->post('name'))->toBe('Alice');
});

// ---------------------------------------------------------------------------
// Input
// ---------------------------------------------------------------------------

test('all() merges query and post', function () {
    $req = Request::create('/path', 'POST', ['b' => 2]);
    // query comes from URI params in GET; for POST 'b' is post
    $all = $req->all();
    expect($all)->toHaveKey('b');
});

test('input() returns merged value', function () {
    $req = Request::create('/path', 'GET', ['name' => 'Bob']);
    expect($req->input('name'))->toBe('Bob');
    expect($req->input('missing', 'default'))->toBe('default');
});

test('only() returns subset', function () {
    $req = Request::create('/path', 'GET', ['a' => 1, 'b' => 2, 'c' => 3]);
    expect($req->only(['a', 'c']))->toBe(['a' => 1, 'c' => 3]);
});

test('except() excludes keys', function () {
    $req = Request::create('/path', 'GET', ['a' => 1, 'b' => 2]);
    $result = $req->except(['b']);
    expect($result)->toHaveKey('a');
    expect($result)->not->toHaveKey('b');
});

test('has() checks key existence', function () {
    $req = Request::create('/path', 'GET', ['name' => 'x']);
    expect($req->has('name'))->toBeTrue();
    expect($req->has('email'))->toBeFalse();
});

test('filled() checks non-empty value', function () {
    $req = Request::create('/path', 'GET', ['name' => '', 'age' => '25']);
    expect($req->filled('name'))->toBeFalse();
    expect($req->filled('age'))->toBeTrue();
});

test('missing() is inverse of has()', function () {
    $req = Request::create('/path', 'GET', ['x' => 1]);
    expect($req->missing('x'))->toBeFalse();
    expect($req->missing('y'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// HTTP methods
// ---------------------------------------------------------------------------

test('method() returns uppercased verb', function () {
    foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
        $req = Request::create('/', $verb);
        expect($req->method())->toBe($verb);
    }
});

test('isGet/isPost/isPut/isPatch/isDelete', function () {
    expect(Request::create('/', 'GET')->isGet())->toBeTrue();
    expect(Request::create('/', 'POST')->isPost())->toBeTrue();
    expect(Request::create('/', 'PUT')->isPut())->toBeTrue();
    expect(Request::create('/', 'PATCH')->isPatch())->toBeTrue();
    expect(Request::create('/', 'DELETE')->isDelete())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Path / URL
// ---------------------------------------------------------------------------

test('path() strips query string', function () {
    $req = Request::create('/users?page=1', 'GET');
    expect($req->path())->toBe('/users');
});

test('url() returns base URL without query', function () {
    $req = Request::create('/users?page=1', 'GET', [], [], [], ['HTTP_HOST' => 'example.com']);
    expect($req->url())->toBe('http://example.com/users');
});

test('fullUrl() includes query string', function () {
    $req = Request::create('/users?page=2', 'GET', [], [], [], ['HTTP_HOST' => 'example.com']);
    expect($req->fullUrl())->toContain('page=2');
});

test('is() matches wildcard patterns', function () {
    $req = Request::create('/api/users/42', 'GET');
    expect($req->is('/api/users/*'))->toBeTrue();
    expect($req->is('/api/posts/*'))->toBeFalse();
    expect($req->is('/api/*', '/other/*'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Headers
// ---------------------------------------------------------------------------

test('header() retrieves HTTP header', function () {
    $req = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    expect($req->header('accept'))->toBe('application/json');
});

test('hasHeader() checks existence', function () {
    $req = Request::create('/', 'GET', [], [], [], ['HTTP_X_FOO' => 'bar']);
    expect($req->hasHeader('x-foo'))->toBeTrue();
    expect($req->hasHeader('x-bar'))->toBeFalse();
});

test('bearerToken() extracts token', function () {
    $req = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer abc123']);
    expect($req->bearerToken())->toBe('abc123');
});

test('bearerToken() returns null when absent', function () {
    $req = Request::create('/', 'GET');
    expect($req->bearerToken())->toBeNull();
});

// ---------------------------------------------------------------------------
// Content negotiation
// ---------------------------------------------------------------------------

test('wantsJson() detects JSON accept header', function () {
    $req = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    expect($req->wantsJson())->toBeTrue();
});

test('isJson() detects JSON content-type', function () {
    $req = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json']);
    expect($req->isJson())->toBeTrue();
});

// ---------------------------------------------------------------------------
// JSON body
// ---------------------------------------------------------------------------

test('json() parses JSON body', function () {
    $req = Request::create(
        '/', 'POST', [], [], [],
        ['CONTENT_TYPE' => 'application/json'],
        '{"name":"Alice","age":30}'
    );
    expect($req->json('name'))->toBe('Alice');
    expect($req->json('age'))->toBe(30);
});

test('json() returns full body when no key', function () {
    $req = Request::create(
        '/', 'POST', [], [], [],
        ['CONTENT_TYPE' => 'application/json'],
        '{"x":1}'
    );
    expect($req->json())->toBe(['x' => 1]);
});

// ---------------------------------------------------------------------------
// Cookie
// ---------------------------------------------------------------------------

test('cookie() reads cookie value', function () {
    $req = Request::create('/', 'GET', [], ['session' => 'tok123']);
    expect($req->cookie('session'))->toBe('tok123');
    expect($req->cookie('missing', 'def'))->toBe('def');
});

// ---------------------------------------------------------------------------
// IP
// ---------------------------------------------------------------------------

test('ip() returns REMOTE_ADDR', function () {
    $req = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);
    expect($req->ip())->toBe('1.2.3.4');
});

test('ip() prefers X-Forwarded-For', function () {
    $req = Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR'          => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '99.99.99.99',
    ]);
    expect($req->ip())->toBe('99.99.99.99');
});
