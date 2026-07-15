<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;

class ValidatedUser extends Model
{
    protected static string $table = 'val_users';

    protected static array $rules = [
        'email'  => 'required|email',
        'name'   => 'required|min:2|max:50',
        'age'    => 'integer|min:0|max:150',
        'role'   => 'in:admin,editor,viewer',
        'website'=> 'url',
    ];
}

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    LazyMePHP::DB_CONNECTION()->query("CREATE TABLE val_users (
        id      INTEGER PRIMARY KEY AUTOINCREMENT,
        email   TEXT,
        name    TEXT,
        age     INTEGER,
        role    TEXT,
        website TEXT
    )");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('Model validation rules', function () {
    it('passes() returns true when all rules pass', function () {
        $u = new ValidatedUser(null, null);
        $u->email   = 'alice@example.com';
        $u->name    = 'Alice';
        $u->age     = 30;
        $u->role    = 'admin';
        $u->website = 'https://example.com';

        expect($u->passes())->toBeTrue();
        expect($u->validate())->toBeEmpty();
    });

    it('required rule catches missing values', function () {
        $u = new ValidatedUser(null, null);
        // email and name left null

        $errors = $u->validate();
        expect($errors)->toHaveKey('email');
        expect($errors)->toHaveKey('name');
        expect($errors['email'][0])->toContain('required');
    });

    it('email rule rejects invalid addresses', function () {
        $u = new ValidatedUser(null, null);
        $u->email = 'not-an-email';
        $u->name  = 'Alice';

        $errors = $u->validate();
        expect($errors)->toHaveKey('email');
        expect($errors['email'][0])->toContain('email address');
    });

    it('min/max rule checks string length', function () {
        $u = new ValidatedUser(null, null);
        $u->email = 'a@b.com';
        $u->name  = 'A';  // too short

        $errors = $u->validate();
        expect($errors)->toHaveKey('name');
        expect($errors['name'][0])->toContain('at least 2');

        $u->name = str_repeat('x', 60);  // too long
        $errors2 = $u->validate();
        expect($errors2['name'][0])->toContain('at most 50');
    });

    it('min/max rule checks numeric values', function () {
        $u = new ValidatedUser(null, null);
        $u->email = 'a@b.com';
        $u->name  = 'Alice';
        $u->age   = -1;

        $errors = $u->validate();
        expect($errors)->toHaveKey('age');
        expect($errors['age'][0])->toContain('at least 0');
    });

    it('in rule rejects values not in the allowed list', function () {
        $u = new ValidatedUser(null, null);
        $u->email = 'a@b.com';
        $u->name  = 'Alice';
        $u->role  = 'superuser';

        $errors = $u->validate();
        expect($errors)->toHaveKey('role');
        expect($errors['role'][0])->toContain('admin,editor,viewer');
    });

    it('url rule rejects non-URLs', function () {
        $u = new ValidatedUser(null, null);
        $u->email   = 'a@b.com';
        $u->name    = 'Alice';
        $u->website = 'not a url';

        $errors = $u->validate();
        expect($errors)->toHaveKey('website');
    });

    it('optional fields are skipped when null', function () {
        $u = new ValidatedUser(null, null);
        $u->email = 'alice@example.com';
        $u->name  = 'Alice';
        // age, role, website left null — not required, should not error

        $errors = $u->validate();
        expect($errors)->not->toHaveKey('age');
        expect($errors)->not->toHaveKey('role');
        expect($errors)->not->toHaveKey('website');
    });

    it('addError() appends external errors', function () {
        $u = new ValidatedUser(null, null);
        $u->email = 'alice@example.com';
        $u->name  = 'Alice';
        $u->addError('email', 'Email is already taken.');

        $errors = $u->errors();
        expect($errors)->toHaveKey('email');
        expect(implode(' ', $errors['email']))->toContain('already taken');
    });
});
