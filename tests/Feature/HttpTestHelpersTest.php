<?php

declare(strict_types=1);

use Core\Http\Request;
use Core\Http\Response;
use Core\Testing\TestResponse;
use Core\Testing\MakesHttpRequests;

uses(MakesHttpRequests::class);

// ---------------------------------------------------------------------------
// Inline "controllers" for testing
// ---------------------------------------------------------------------------

function echoHandler(Request $req): Response
{
    return Response::json([
        'method' => $req->method(),
        'path'   => $req->path(),
        'input'  => $req->all(),
        'auth'   => $req->bearerToken(),
    ]);
}

function createHandler(Request $req): Response
{
    $data = $req->all();
    if (empty($data['name'])) {
        return Response::json(['error' => 'name required'], 422);
    }
    return Response::json(['id' => 1, 'name' => $data['name']], 201);
}

function htmlHandler(Request $req): Response
{
    return Response::html('<h1>Hello World</h1>');
}

function redirectHandler(Request $req): Response
{
    return Response::redirect('/dashboard');
}

function noContentHandler(Request $req): Response
{
    return Response::noContent();
}

function arrayHandler(Request $req): array
{
    return ['key' => 'value'];
}

// ---------------------------------------------------------------------------
// TestResponse — status assertions
// ---------------------------------------------------------------------------

test('assertOk passes for 200', function () {
    $r = new TestResponse('body', 200);
    $r->assertOk()->assertSuccessful();
});

test('assertCreated passes for 201', function () {
    $r = new TestResponse('', 201);
    $r->assertCreated();
});

test('assertNoContent passes for 204', function () {
    $r = new TestResponse('', 204);
    $r->assertNoContent();
});

test('assertNotFound passes for 404', function () {
    $r = new TestResponse('', 404);
    $r->assertNotFound()->assertStatus(404);
});

test('assertRedirect passes for 302 with Location', function () {
    $r = new TestResponse('', 302, ['Location' => '/home']);
    $r->assertRedirect('/home');
});

test('assertUnauthorized passes for 401', function () {
    $r = new TestResponse('', 401);
    $r->assertUnauthorized();
});

test('assertForbidden passes for 403', function () {
    $r = new TestResponse('', 403);
    $r->assertForbidden();
});

test('assertUnprocessable passes for 422', function () {
    $r = new TestResponse('', 422);
    $r->assertUnprocessable();
});

// ---------------------------------------------------------------------------
// TestResponse — JSON assertions
// ---------------------------------------------------------------------------

test('assertJson checks exact structure', function () {
    $r = new TestResponse(json_encode(['id' => 1, 'name' => 'Alice']));
    $r->assertJson(['id' => 1, 'name' => 'Alice']);
});

test('assertJsonFragment checks subset', function () {
    $r = new TestResponse(json_encode(['id' => 1, 'name' => 'Alice', 'role' => 'admin']));
    $r->assertJsonFragment(['name' => 'Alice']);
});

test('assertJsonPath checks dot-notation path', function () {
    $r = new TestResponse(json_encode(['user' => ['name' => 'Alice']]));
    $r->assertJsonPath('user.name', 'Alice');
});

test('assertJsonCount checks array length', function () {
    $r = new TestResponse(json_encode(['items' => [1, 2, 3]]));
    $r->assertJsonCount(3, 'items');
});

test('assertJsonCount on root array', function () {
    $r = new TestResponse(json_encode([['id' => 1], ['id' => 2]]));
    $r->assertJsonCount(2);
});

test('assertJsonMissing checks key is absent', function () {
    $r = new TestResponse(json_encode(['id' => 1]));
    $r->assertJsonMissing(['password']);
});

// ---------------------------------------------------------------------------
// TestResponse — body assertions
// ---------------------------------------------------------------------------

test('assertSee checks substring', function () {
    $r = new TestResponse('<h1>Hello World</h1>');
    $r->assertSee('Hello World');
});

test('assertDontSee checks string absent', function () {
    $r = new TestResponse('<h1>Hello</h1>');
    $r->assertDontSee('Goodbye');
});

test('json() accessor decodes body', function () {
    $r = new TestResponse(json_encode(['foo' => 'bar']));
    expect($r->json())->toBe(['foo' => 'bar']);
    expect($r->json('foo'))->toBe('bar');
});

test('body() returns raw body', function () {
    $r = new TestResponse('raw string');
    expect($r->body())->toBe('raw string');
});

// ---------------------------------------------------------------------------
// TestResponse::fromResponse()
// ---------------------------------------------------------------------------

test('fromResponse wraps a Response object', function () {
    $response = Response::json(['ok' => true], 201);
    $r = TestResponse::fromResponse($response);
    $r->assertCreated()->assertJsonFragment(['ok' => true]);
});

test('fromRaw wraps an array as JSON', function () {
    $r = TestResponse::fromRaw(['key' => 'val']);
    $r->assertOk()->assertJsonFragment(['key' => 'val']);
});

// ---------------------------------------------------------------------------
// MakesHttpRequests — HTTP methods
// ---------------------------------------------------------------------------

test('get() builds a GET request', function () {
    $response = $this->get('echoHandler', '/api/ping');
    $response->assertOk();
    expect($response->json('method'))->toBe('GET');
    expect($response->json('path'))->toBe('/api/ping');
});

test('post() sends POST data', function () {
    $response = $this->post('createHandler', '/api/users', ['name' => 'Alice']);
    $response->assertCreated()->assertJsonFragment(['name' => 'Alice']);
});

test('post() with missing required field returns 422', function () {
    $response = $this->post('createHandler', '/api/users', []);
    $response->assertUnprocessable();
});

test('postJson() sets Content-Type application/json', function () {
    $response = $this->postJson('echoHandler', '/api/users', ['key' => 'val']);
    $response->assertOk();
    expect($response->json('input'))->toHaveKey('key');
});

test('withToken() adds Authorization header', function () {
    $response = $this->withToken('my-token')->get('echoHandler', '/api/me');
    expect($response->json('auth'))->toBe('my-token');
});

test('actingAs() extracts token from user array', function () {
    $user = ['id' => 1, 'token' => 'user-jwt'];
    $response = $this->actingAs($user)->get('echoHandler', '/api/me');
    expect($response->json('auth'))->toBe('user-jwt');
});

test('get() with query params', function () {
    $response = $this->get('echoHandler', '/api/items', ['page' => '2']);
    expect($response->json('input'))->toHaveKey('page');
});

test('handler returning array is wrapped as JSON', function () {
    $response = $this->get('arrayHandler', '/api/data');
    $response->assertOk()->assertJsonFragment(['key' => 'value']);
});

test('handler returning HTML Response', function () {
    $response = $this->get('htmlHandler', '/page');
    $response->assertOk()->assertSee('Hello World');
});

test('redirect response is asserted correctly', function () {
    $response = $this->get('redirectHandler', '/old');
    $response->assertRedirect('/dashboard');
});

test('noContent response is asserted correctly', function () {
    $response = $this->delete('noContentHandler', '/api/items/1');
    $response->assertNoContent()->assertEmpty();
});

test('header() accessor retrieves response header', function () {
    $r = new TestResponse('', 200, ['X-Custom' => 'hello']);
    expect($r->header('X-Custom'))->toBe('hello');
    expect($r->header('x-custom'))->toBe('hello'); // case-insensitive
});

test('assertHeader() checks header existence', function () {
    $r = new TestResponse('', 200, ['X-Foo' => 'bar']);
    $r->assertHeader('x-foo')->assertHeader('X-Foo', 'bar');
});

test('assertHeaderMissing() checks header absent', function () {
    $r = new TestResponse('', 200, []);
    $r->assertHeaderMissing('X-Missing');
});

// ---------------------------------------------------------------------------
// Chaining
// ---------------------------------------------------------------------------

test('assertions can be chained fluently', function () {
    $response = $this->postJson('createHandler', '/api/users', ['name' => 'Bob']);

    $response
        ->assertCreated()
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'Bob'])
        ->assertJsonPath('id', 1);
});
