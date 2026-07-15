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

class ConfirmedUser extends Model
{
    protected static string $table = 'val_confirm_users';

    protected static array $rules = [
        'email'    => 'required|email',
        'name'     => 'required|min:2',
        'password' => 'required|confirmed',
    ];
}

class UniqueUser extends Model
{
    protected static string $table = 'val_users';

    protected static array $rules = [
        'email' => 'required|email|unique:val_users,email',
        'name'  => 'required|min:2',
    ];
}

class ExistsUser extends Model
{
    protected static string $table = 'val_exist_users';

    protected static array $rules = [
        'name'    => 'required|min:2',
        'role_id' => 'exists:val_exist_users,id',
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
    LazyMePHP::DB_CONNECTION()->query("CREATE TABLE val_confirm_users (
        id                   INTEGER PRIMARY KEY AUTOINCREMENT,
        email                TEXT,
        name                 TEXT,
        password             TEXT,
        password_confirmation TEXT
    )");
    LazyMePHP::DB_CONNECTION()->query("CREATE TABLE val_exist_users (
        id      INTEGER PRIMARY KEY AUTOINCREMENT,
        name    TEXT,
        role_id INTEGER
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

describe('confirmed rule', function () {
    it('passes when confirmation matches', function () {
        $u = new ConfirmedUser(null, null);
        $u->email                 = 'alice@example.com';
        $u->name                  = 'Alice';
        $u->password              = 'secret123';
        $u->password_confirmation = 'secret123';

        expect($u->passes())->toBeTrue();
    });

    it('fails when confirmation does not match', function () {
        $u = new ConfirmedUser(null, null);
        $u->email                 = 'alice@example.com';
        $u->name                  = 'Alice';
        $u->password              = 'secret123';
        $u->password_confirmation = 'wrong';

        $errors = $u->validate();
        expect($errors)->toHaveKey('password');
        expect($errors['password'][0])->toContain('confirmation');
    });

    it('fails when confirmation field is absent', function () {
        $u = new ConfirmedUser(null, null);
        $u->email    = 'alice@example.com';
        $u->name     = 'Alice';
        $u->password = 'secret123';
        // no password_confirmation set

        $errors = $u->validate();
        expect($errors)->toHaveKey('password');
    });
});

describe('unique rule', function () {
    it('passes when value is not taken', function () {
        $u = new UniqueUser(null, null);
        $u->email = 'newuser@example.com';
        $u->name  = 'New User';

        expect($u->passes())->toBeTrue();
    });

    it('fails when value already exists in the table', function () {
        LazyMePHP::DB_CONNECTION()->query(
            "INSERT INTO val_users (email, name) VALUES ('taken@example.com', 'Existing')"
        );

        $u = new UniqueUser(null, null);
        $u->email = 'taken@example.com';
        $u->name  = 'New User';

        $errors = $u->validate();
        expect($errors)->toHaveKey('email');
        expect($errors['email'][0])->toContain('already taken');
    });
});

describe('exists rule', function () {
    it('passes when value exists in the referenced table', function () {
        LazyMePHP::DB_CONNECTION()->query(
            "INSERT INTO val_exist_users (name) VALUES ('Ref User')"
        );
        $id = LazyMePHP::DB_CONNECTION()->query("SELECT last_insert_rowid() as id")->fetchArray()['id'];

        $u = new ExistsUser(null, null);
        $u->name    = 'Alice';
        $u->role_id = $id;

        expect($u->passes())->toBeTrue();
    });

    it('fails when value does not exist in the referenced table', function () {
        $u = new ExistsUser(null, null);
        $u->name    = 'Alice';
        $u->role_id = 9999;

        $errors = $u->validate();
        expect($errors)->toHaveKey('role_id');
        expect($errors['role_id'][0])->toContain('does not exist');
    });

    it('skips check when value is null', function () {
        $u = new ExistsUser(null, null);
        $u->name    = 'Alice';
        $u->role_id = null;

        expect($u->passes())->toBeTrue();
    });
});
