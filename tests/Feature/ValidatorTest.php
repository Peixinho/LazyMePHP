<?php

declare(strict_types=1);

use Core\Validator;
use Core\Http\ValidationException;

// ---------------------------------------------------------------------------
// Basic passes / fails
// ---------------------------------------------------------------------------

test('passes() returns true when all rules pass', function () {
    $v = Validator::make(['name' => 'Alice', 'email' => 'alice@example.com'], [
        'name'  => 'required|min:3',
        'email' => 'required|email',
    ]);
    expect($v->passes())->toBeTrue();
    expect($v->fails())->toBeFalse();
});

test('fails() returns true when any rule fails', function () {
    $v = Validator::make(['name' => ''], ['name' => 'required']);
    expect($v->fails())->toBeTrue();
});

test('errors() returns error messages keyed by field', function () {
    $v = Validator::make(['name' => ''], ['name' => 'required']);
    expect($v->errors())->toHaveKey('name');
    expect($v->errors()['name'])->toBeArray();
    expect($v->errors()['name'][0])->toContain('required');
});

// ---------------------------------------------------------------------------
// Validated data
// ---------------------------------------------------------------------------

test('validated() returns only rule-declared fields', function () {
    $data = ['name' => 'Alice', 'extra' => 'ignored'];
    $v    = Validator::make($data, ['name' => 'required']);
    expect($v->validated())->toBe(['name' => 'Alice']);
    expect($v->validated())->not->toHaveKey('extra');
});

// ---------------------------------------------------------------------------
// Individual rules
// ---------------------------------------------------------------------------

test('email rule rejects invalid email', function () {
    $v = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
    expect($v->fails())->toBeTrue();
    expect($v->errors())->toHaveKey('email');
});

test('email rule accepts valid email', function () {
    $v = Validator::make(['email' => 'user@example.com'], ['email' => 'email']);
    expect($v->passes())->toBeTrue();
});

test('min rule for strings', function () {
    $v = Validator::make(['name' => 'ab'], ['name' => 'required|min:3']);
    expect($v->fails())->toBeTrue();
});

test('max rule for strings', function () {
    $v = Validator::make(['name' => 'toolongname'], ['name' => 'max:5']);
    expect($v->fails())->toBeTrue();
});

test('integer rule', function () {
    $v = Validator::make(['age' => 'abc'], ['age' => 'integer']);
    expect($v->fails())->toBeTrue();

    $v2 = Validator::make(['age' => '25'], ['age' => 'integer']);
    expect($v2->passes())->toBeTrue();
});

test('numeric rule', function () {
    $v = Validator::make(['price' => 'abc'], ['price' => 'numeric']);
    expect($v->fails())->toBeTrue();

    $v2 = Validator::make(['price' => '3.14'], ['price' => 'numeric']);
    expect($v2->passes())->toBeTrue();
});

test('in rule', function () {
    $v = Validator::make(['role' => 'superadmin'], ['role' => 'in:admin,user,editor']);
    expect($v->fails())->toBeTrue();

    $v2 = Validator::make(['role' => 'admin'], ['role' => 'in:admin,user,editor']);
    expect($v2->passes())->toBeTrue();
});

test('not_in rule', function () {
    $v = Validator::make(['name' => 'admin'], ['name' => 'not_in:admin,root']);
    expect($v->fails())->toBeTrue();
});

test('confirmed rule', function () {
    $v = Validator::make(
        ['password' => 'secret', 'password_confirmation' => 'wrong'],
        ['password' => 'required|confirmed'],
    );
    expect($v->fails())->toBeTrue();

    $v2 = Validator::make(
        ['password' => 'secret', 'password_confirmation' => 'secret'],
        ['password' => 'required|confirmed'],
    );
    expect($v2->passes())->toBeTrue();
});

test('regex rule', function () {
    $v = Validator::make(['code' => 'ABC123'], ['code' => 'regex:/^[a-z]+$/']);
    expect($v->fails())->toBeTrue();

    $v2 = Validator::make(['code' => 'abc'], ['code' => 'regex:/^[a-z]+$/']);
    expect($v2->passes())->toBeTrue();
});

test('alpha rule', function () {
    $v = Validator::make(['name' => 'hello123'], ['name' => 'alpha']);
    expect($v->fails())->toBeTrue();

    $v2 = Validator::make(['name' => 'hello'], ['name' => 'alpha']);
    expect($v2->passes())->toBeTrue();
});

test('alpha_num rule', function () {
    $v = Validator::make(['code' => 'hello!'], ['code' => 'alpha_num']);
    expect($v->fails())->toBeTrue();
});

test('boolean rule', function () {
    $v = Validator::make(['active' => 'yes'], ['active' => 'boolean']);
    expect($v->fails())->toBeTrue();

    $v2 = Validator::make(['active' => '1'], ['active' => 'boolean']);
    expect($v2->passes())->toBeTrue();
});

test('min_digits / max_digits rules', function () {
    $v = Validator::make(['pin' => '12'], ['pin' => 'min_digits:4']);
    expect($v->fails())->toBeTrue();

    $v2 = Validator::make(['pin' => '1234'], ['pin' => 'min_digits:4|max_digits:6']);
    expect($v2->passes())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Optional fields
// ---------------------------------------------------------------------------

test('non-required fields are skipped when empty', function () {
    $v = Validator::make(['email' => ''], ['email' => 'email']);
    expect($v->passes())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Multiple errors
// ---------------------------------------------------------------------------

test('multiple fields can fail simultaneously', function () {
    $v = Validator::make(
        ['name' => '', 'email' => 'bad'],
        ['name' => 'required', 'email' => 'email'],
    );
    $errors = $v->errors();
    expect($errors)->toHaveKey('name');
    expect($errors)->toHaveKey('email');
});

// ---------------------------------------------------------------------------
// validate() throws on failure
// ---------------------------------------------------------------------------

test('validate() returns data on success', function () {
    $data = Validator::make(['name' => 'Alice'], ['name' => 'required'])->validate();
    expect($data)->toBe(['name' => 'Alice']);
});

test('validate() throws ValidationException on failure', function () {
    expect(fn() => Validator::make(['name' => ''], ['name' => 'required'])->validate())
        ->toThrow(ValidationException::class);
});

test('ValidationException carries errors', function () {
    try {
        Validator::make(['name' => ''], ['name' => 'required'])->validate();
        expect(true)->toBeFalse('should have thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
        expect($e->firstError())->toContain('required');
        expect($e->getCode())->toBe(422);
    }
});

// ---------------------------------------------------------------------------
// sometimes() — conditional rules
// ---------------------------------------------------------------------------

test('sometimes() adds rules only when condition is met', function () {
    $data = ['role' => 'admin', 'admin_code' => ''];
    $v = Validator::make($data, ['role' => 'required'])
        ->sometimes('admin_code', 'required', fn($d) => $d['role'] === 'admin');

    expect($v->fails())->toBeTrue();
    expect($v->errors())->toHaveKey('admin_code');
});

test('sometimes() skips rules when condition is false', function () {
    $data = ['role' => 'user', 'admin_code' => ''];
    $v = Validator::make($data, ['role' => 'required'])
        ->sometimes('admin_code', 'required', fn($d) => $d['role'] === 'admin');

    expect($v->passes())->toBeTrue();
});
