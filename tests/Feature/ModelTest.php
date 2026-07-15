<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;

beforeEach(function () {
    $_ENV['DB_TYPE'] = 'sqlite';
    $_ENV['DB_FILE_PATH'] = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV'] = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        age INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1
    )");
    $db->query("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        body TEXT
    )");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('Model', function () {
    it('inserts a new record and auto-populates the PK', function () {
        $user = new Model('users');
        $user->name = 'Alice';
        $user->email = 'alice@example.com';
        $user->age = 30;
        $user->Save();

        expect($user->getPrimaryKey())->toBeGreaterThan(0);
        expect($user->name)->toBe('Alice');
    });

    it('reads a record by primary key', function () {
        $db = LazyMePHP::DB_CONNECTION();
        $db->query("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['Bob', 'bob@example.com', 25]);
        $id = $db->getLastInsertedId();

        $user = new Model('users', $id);

        expect($user->name)->toBe('Bob');
        expect($user->email)->toBe('bob@example.com');
        expect((int)$user->age)->toBe(25);
    });

    it('updates an existing record', function () {
        $user = new Model('users');
        $user->name = 'Carol';
        $user->email = 'carol@example.com';
        $user->Save();
        $id = $user->getPrimaryKey();

        $user->name = 'Carol Updated';
        $user->Save();

        $reloaded = new Model('users', $id);
        expect($reloaded->name)->toBe('Carol Updated');
    });

    it('deletes a record', function () {
        $user = new Model('users');
        $user->name = 'Dave';
        $user->email = 'dave@example.com';
        $user->Save();
        $id = $user->getPrimaryKey();

        $result = $user->Delete();
        expect($result)->toBeTrue();

        $gone = new Model('users', $id);
        expect($gone->getPrimaryKey())->toBeNull();
    });

    it('returns null PK for a non-existent record', function () {
        $missing = new Model('users', 99999);
        expect($missing->getPrimaryKey())->toBeNull();
    });

    it('serializes to array', function () {
        $user = new Model('users');
        $user->name = 'Eve';
        $user->email = 'eve@example.com';
        $user->Save();

        $arr = $user->Serialize();
        expect($arr)->toHaveKey('name');
        expect($arr['name'])->toBe('Eve');
    });

    it('serializes with field mask', function () {
        $user = new Model('users');
        $user->name = 'Frank';
        $user->email = 'frank@example.com';
        $user->Save();

        $arr = $user->Serialize(['users' => ['name']]);
        expect($arr)->toHaveKey('name');
        expect($arr)->not->toHaveKey('email');
    });

    it('exposes table column names', function () {
        $model = new Model('users');
        $columns = $model->getColumns();
        expect($columns)->toContain('id');
        expect($columns)->toContain('name');
        expect($columns)->toContain('email');
    });

    it('ignores unknown properties', function () {
        $user = new Model('users');
        $user->nonexistent_field = 'nope';
        expect($user->nonexistent_field)->toBeNull();
    });
});

describe('ModelQuery', function () {
    beforeEach(function () {
        $db = LazyMePHP::DB_CONNECTION();
        $db->query("INSERT INTO users (name, email, age, active) VALUES (?, ?, ?, ?)", ['Alice', 'alice@example.com', 30, 1]);
        $db->query("INSERT INTO users (name, email, age, active) VALUES (?, ?, ?, ?)", ['Bob', 'bob@example.com', 25, 1]);
        $db->query("INSERT INTO users (name, email, age, active) VALUES (?, ?, ?, ?)", ['Carol', 'carol@example.com', 35, 0]);
    });

    it('fetches all records', function () {
        $list = Model::query('users')->get();
        expect(count($list))->toBe(3);
    });

    it('filters by exact value', function () {
        $list = Model::query('users')->where('active', 1)->get();
        expect(count($list))->toBe(2);
    });

    it('filters by comparison operator', function () {
        $list = Model::query('users')->where('age', 30, '>=')->get();
        expect(count($list))->toBe(2); // Alice (30) and Carol (35)
    });

    it('filters with LIKE', function () {
        $list = Model::query('users')->whereLike('name', 'al')->get();
        expect(count($list))->toBe(1);
        expect($list[0]->name)->toBe('Alice');
    });

    it('orders results', function () {
        $list = Model::query('users')->orderBy('age', 'ASC')->get();
        expect($list[0]->name)->toBe('Bob');
        expect($list[2]->name)->toBe('Carol');
    });

    it('limits results', function () {
        $list = Model::query('users')->orderBy('id')->limit(2)->get();
        expect(count($list))->toBe(2);
    });

    it('paginates with offset', function () {
        $list = Model::query('users')->orderBy('id')->limit(1, 1)->get();
        expect(count($list))->toBe(1);
        expect($list[0]->name)->toBe('Bob');
    });

    it('counts records', function () {
        $count = Model::query('users')->where('active', 1)->count();
        expect($count)->toBe(2);
    });

    it('returns serialized arrays via toArray()', function () {
        $list = Model::query('users')->where('active', 1)->toArray();
        expect($list[0])->toBeArray();
        expect($list[0])->toHaveKey('name');
    });

    it('chains multiple where conditions', function () {
        $list = Model::query('users')
            ->where('active', 1)
            ->where('age', 28, '>')
            ->get();
        expect(count($list))->toBe(1);
        expect($list[0]->name)->toBe('Alice');
    });

    it('returns Model instances', function () {
        $list = Model::query('users')->get();
        expect($list[0])->toBeInstanceOf(Model::class);
    });
});
