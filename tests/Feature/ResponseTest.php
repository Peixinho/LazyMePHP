<?php

declare(strict_types=1);

use Core\Http\Response;

// ---------------------------------------------------------------------------
// Factories
// ---------------------------------------------------------------------------

test('make() creates a response with defaults', function () {
    $r = Response::make('hello', 200);
    expect($r->getStatus())->toBe(200);
    expect($r->getBody())->toBe('hello');
    expect($r->getContentType())->toContain('text/html');
});

test('json() encodes data and sets content-type', function () {
    $r = Response::json(['id' => 1, 'name' => 'Alice'], 201);
    expect($r->getStatus())->toBe(201);
    expect($r->getContentType())->toContain('application/json');
    $decoded = json_decode($r->getBody(), true);
    expect($decoded['id'])->toBe(1);
    expect($decoded['name'])->toBe('Alice');
});

test('html() sets text/html content-type', function () {
    $r = Response::html('<h1>Hi</h1>', 200);
    expect($r->getContentType())->toContain('text/html');
    expect($r->getBody())->toBe('<h1>Hi</h1>');
});

test('text() sets text/plain content-type', function () {
    $r = Response::text('plain text');
    expect($r->getContentType())->toContain('text/plain');
});

test('redirect() sets Location header and 302 status', function () {
    $r = Response::redirect('/dashboard');
    expect($r->getStatus())->toBe(302);
    expect($r->isRedirect())->toBeTrue();
    expect($r->getHeaders()['Location'])->toBe('/dashboard');
});

test('redirect() accepts custom status', function () {
    $r = Response::redirect('/login', 301);
    expect($r->getStatus())->toBe(301);
});

test('noContent() returns 204', function () {
    $r = Response::noContent();
    expect($r->getStatus())->toBe(204);
    expect($r->getBody())->toBe('');
});

test('notFound() returns 404', function () {
    expect(Response::notFound()->getStatus())->toBe(404);
    expect(Response::notFound()->isClientError())->toBeTrue();
});

test('forbidden() returns 403', function () {
    expect(Response::forbidden()->getStatus())->toBe(403);
});

test('unauthorized() returns 401', function () {
    expect(Response::unauthorized()->getStatus())->toBe(401);
});

// ---------------------------------------------------------------------------
// Fluent modifiers (immutable)
// ---------------------------------------------------------------------------

test('withStatus() returns new instance with updated status', function () {
    $original = Response::make('body', 200);
    $modified = $original->withStatus(404);

    expect($original->getStatus())->toBe(200);
    expect($modified->getStatus())->toBe(404);
    expect($original)->not->toBe($modified);
});

test('withBody() returns new instance with updated body', function () {
    $r = Response::make('old')->withBody('new');
    expect($r->getBody())->toBe('new');
});

test('withContentType() updates content-type', function () {
    $r = Response::make()->withContentType('application/xml');
    expect($r->getContentType())->toBe('application/xml');
});

test('withHeader() adds a header', function () {
    $r = Response::make()->withHeader('X-Custom', 'value');
    expect($r->getHeaders()['X-Custom'])->toBe('value');
});

test('withHeader() can be chained for multiple headers', function () {
    $r = Response::make()
        ->withHeader('X-A', '1')
        ->withHeader('X-B', '2');

    expect($r->getHeaders()['X-A'])->toBe('1');
    expect($r->getHeaders()['X-B'])->toBe('2');
});

test('withHeader() is immutable — original unchanged', function () {
    $original = Response::make();
    $modified = $original->withHeader('X-Test', 'yes');

    expect($original->getHeaders())->not->toHaveKey('X-Test');
    expect($modified->getHeaders())->toHaveKey('X-Test');
});

test('withCookie() records cookie for sending', function () {
    $r = Response::make()->withCookie('session', 'abc123');
    // Cookie is internal — verify it doesn't throw and response is still ok
    expect($r->isOk())->toBeTrue();
});

// ---------------------------------------------------------------------------
// State predicates
// ---------------------------------------------------------------------------

test('isOk() for 2xx', function () {
    expect(Response::make('', 200)->isOk())->toBeTrue();
    expect(Response::make('', 201)->isOk())->toBeTrue();
    expect(Response::make('', 204)->isOk())->toBeTrue();
    expect(Response::make('', 404)->isOk())->toBeFalse();
});

test('isRedirect() for 3xx', function () {
    expect(Response::redirect('/')->isRedirect())->toBeTrue();
    expect(Response::make('', 200)->isRedirect())->toBeFalse();
});

test('isClientError() for 4xx', function () {
    expect(Response::make('', 404)->isClientError())->toBeTrue();
    expect(Response::make('', 200)->isClientError())->toBeFalse();
});

test('isServerError() for 5xx', function () {
    expect(Response::make('', 500)->isServerError())->toBeTrue();
    expect(Response::make('', 200)->isServerError())->toBeFalse();
});

// ---------------------------------------------------------------------------
// JSON encoding edge cases
// ---------------------------------------------------------------------------

test('json() encodes nested arrays', function () {
    $r = Response::json(['users' => [['id' => 1], ['id' => 2]]]);
    $decoded = json_decode($r->getBody(), true);
    expect($decoded['users'])->toHaveCount(2);
});

test('json() encodes empty array', function () {
    $r = Response::json([]);
    expect($r->getBody())->toBe('[]');
});

test('json() default status is 200', function () {
    expect(Response::json(['ok' => true])->getStatus())->toBe(200);
});
