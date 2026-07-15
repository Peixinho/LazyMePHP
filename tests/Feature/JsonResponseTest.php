<?php

declare(strict_types=1);

use Core\Http\JsonResponse;

// -----------------------------------------------------------------------
// successBody
// -----------------------------------------------------------------------

test('successBody wraps data under a data key', function () {
    $body = JsonResponse::successBody(['id' => 1, 'name' => 'Alice']);
    expect($body)->toHaveKey('data');
    expect($body['data']['id'])->toBe(1);
    expect($body['data']['name'])->toBe('Alice');
});

test('successBody accepts scalar data', function () {
    expect(JsonResponse::successBody(true))->toBe(['data' => true]);
    expect(JsonResponse::successBody(null))->toBe(['data' => null]);
});

test('successBody does not contain an error key', function () {
    expect(JsonResponse::successBody([]))->not->toHaveKey('error');
});

// -----------------------------------------------------------------------
// errorBody
// -----------------------------------------------------------------------

test('errorBody has message and status inside error key', function () {
    $body = JsonResponse::errorBody('Not found', 404);
    expect($body)->toHaveKey('error');
    expect($body['error']['message'])->toBe('Not found');
    expect($body['error']['status'])->toBe(404);
});

test('errorBody omits details key when no details provided', function () {
    $body = JsonResponse::errorBody('Oops', 500);
    expect($body['error'])->not->toHaveKey('details');
});

test('errorBody includes details when provided', function () {
    $body = JsonResponse::errorBody('Bad request', 400, ['field' => 'required']);
    expect($body['error'])->toHaveKey('details');
    expect($body['error']['details']['field'])->toBe('required');
});

test('errorBody does not contain a data key', function () {
    expect(JsonResponse::errorBody('err', 400))->not->toHaveKey('data');
});

// -----------------------------------------------------------------------
// validationErrorBody
// -----------------------------------------------------------------------

test('validationErrorBody uses 422 status', function () {
    $body = JsonResponse::validationErrorBody(['email' => 'The email is required.']);
    expect($body['error']['status'])->toBe(422);
});

test('validationErrorBody nests field errors under errors key', function () {
    $body = JsonResponse::validationErrorBody(['name' => 'Required', 'email' => 'Invalid']);
    expect($body['error']['errors']['name'])->toBe('Required');
    expect($body['error']['errors']['email'])->toBe('Invalid');
});

test('validationErrorBody uses default message', function () {
    $body = JsonResponse::validationErrorBody([]);
    expect($body['error']['message'])->toBe('Validation failed');
});

test('validationErrorBody accepts custom message', function () {
    $body = JsonResponse::validationErrorBody([], 'Form errors');
    expect($body['error']['message'])->toBe('Form errors');
});

// -----------------------------------------------------------------------
// JSON encoding
// -----------------------------------------------------------------------

test('successBody encodes to valid JSON', function () {
    $json    = json_encode(JsonResponse::successBody(['key' => 'value']));
    $decoded = json_decode($json, true);
    expect($decoded['data']['key'])->toBe('value');
});

test('errorBody encodes to valid JSON', function () {
    $json    = json_encode(JsonResponse::errorBody('Forbidden', 403));
    $decoded = json_decode($json, true);
    expect($decoded['error']['status'])->toBe(403);
});
