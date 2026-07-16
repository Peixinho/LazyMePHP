<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\CrudController;
use Core\Http\Request;

beforeEach(function () {
    $_ENV['DB_TYPE'] = 'sqlite';
    $_ENV['DB_FILE_PATH'] = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV'] = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        age INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1
    )");
    $db->query("INSERT INTO users (name, email, age, active) VALUES ('Alice', 'alice@example.com', 30, 1)");
    $db->query("INSERT INTO users (name, email, age, active) VALUES ('Bob', 'bob@example.com', 25, 1)");
    $db->query("INSERT INTO users (name, email, age, active) VALUES ('Carol', 'carol@example.com', 35, 0)");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
    $_GET  = [];
    $_POST = [];
});

function makeController(string $table = 'users'): CrudController {
    return CrudController::forTable($table, new Request());
}

describe('CrudController', function () {
    describe('index()', function () {
        it('returns all records and a count', function () {
            $data = makeController()->index();
            expect(count($data['users']))->toBe(3);
            expect($data['length'])->toBe(3);
            expect($data['filters'])->toBe([]);
        });

        it('filters by column via FindBy* GET param', function () {
            $_GET['FindByactive'] = '1';
            $data = makeController()->index();
            expect(count($data['users']))->toBe(2);
            expect($data['filters'])->toHaveKey('FindByactive');
        });

        it('paginates with page and limit', function () {
            $data = makeController()->index(page: 1, limit: 2);
            expect(count($data['users']))->toBe(2);
            expect($data['length'])->toBe(3);
        });

        it('returns Model instances', function () {
            $data = makeController()->index();
            expect($data['users'][0])->toBeInstanceOf(Model::class);
        });
    });

    describe('edit()', function () {
        it('returns a new empty model when no id given', function () {
            $data = makeController()->edit();
            expect($data['users'])->toBeInstanceOf(Model::class);
            expect($data['users']->getPrimaryKey())->toBeNull();
        });

        it('loads an existing record by id', function () {
            $db = LazyMePHP::DB_CONNECTION();
            $id = $db->getLastInsertedId(); // Carol is last inserted

            $data = makeController()->edit($id);
            expect($data['users']->name)->toBe('Carol');
        });
    });

    describe('save()', function () {
        it('inserts a new record from POST data', function () {
            $_POST = ['name' => 'Dave', 'email' => 'dave@example.com', 'age' => '40', 'active' => '1'];

            $result = makeController()->save();
            expect($result)->toBeInstanceOf(Model::class);
            expect($result->getPrimaryKey())->toBeGreaterThan(0);
            expect($result->name)->toBe('Dave');
        });

        it('updates an existing record', function () {
            $db = LazyMePHP::DB_CONNECTION();
            $db->query("INSERT INTO users (name, email, age) VALUES ('Eve', 'eve@example.com', 28)");
            $id = $db->getLastInsertedId();

            $_POST = ['name' => 'Eve Updated', 'email' => 'eve@example.com'];
            $result = makeController()->save($id);

            expect($result)->toBeInstanceOf(Model::class);
            $reloaded = new Model('users', $id);
            expect($reloaded->name)->toBe('Eve Updated');
        });

        it('returns false on validation failure', function () {
            $_POST = ['name' => '', 'email' => 'not-an-email'];
            $result = makeController()->save();
            expect($result)->toBeFalse();
        });
    });

    describe('delete()', function () {
        it('deletes a record by id', function () {
            $db = LazyMePHP::DB_CONNECTION();
            $db->query("INSERT INTO users (name, email) VALUES ('Frank', 'frank@example.com')");
            $id = $db->getLastInsertedId();

            makeController()->delete($id);

            $gone = new Model('users', $id);
            expect($gone->getPrimaryKey())->toBeNull();
        });
    });

    describe('foreign keys', function () {
        beforeEach(function () {
            $db = LazyMePHP::DB_CONNECTION();
            $db->query("CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                author_id INTEGER NOT NULL REFERENCES users(id)
            )");
            Model::clearSchemaCache();
        });

        it('auto-detects a schema-declared FK column and loads dropdown options', function () {
            $data = makeController('posts')->edit();

            expect($data['foreignKeys'])->toHaveKey('author_id');
            expect($data['foreignKeys']['author_id']['table'])->toBe('users');
            expect($data['foreignKeys']['author_id']['options'])->toHaveCount(3);
            expect($data['foreignKeys']['author_id']['options'][0])->toHaveKeys(['value', 'label']);
        });

        it('picks a human-readable label over the raw id', function () {
            $data = makeController('posts')->edit();
            $labels = array_column($data['foreignKeys']['author_id']['options'], 'label');
            expect($labels)->toContain('Alice', 'Bob', 'Carol');
        });

        it('renders a <select> for the FK column in the generic edit view', function () {
            $db = LazyMePHP::DB_CONNECTION();
            $db->query("INSERT INTO posts (title, author_id) VALUES ('Hello', 1)");
            $id = $db->getLastInsertedId();

            $controller = makeController('posts');
            $data = $controller->edit($id);
            $html = \Core\BladeFactory::render($controller->viewName('edit'), $data);

            expect($html)->toContain('name="author_id"');
            expect($html)->toContain('<select');
            expect($html)->toContain('Alice');
        });

        it('lets an explicit foreignKeys() override the auto-detected target table', function () {
            $controller = new class('posts', new Request()) extends CrudController {
                protected function foreignKeys(): array { return ['author_id' => 'users']; }
                public function exposedFk(): array { return $this->edit(); }
            };
            $data = $controller->exposedFk();
            expect($data['foreignKeys']['author_id']['table'])->toBe('users');
        });
    });

    describe('forTable() factory', function () {
        it('returns a CrudController for unknown tables', function () {
            $controller = CrudController::forTable('users', new Request());
            expect($controller)->toBeInstanceOf(CrudController::class);
        });
    });

    describe('validationRules()', function () {
        it('derives rules from schema without a PK entry', function () {
            $controller = new class('users', new Request()) extends CrudController {
                public function exposedRules(): array { return $this->validationRules(); }
            };
            $rules = $controller->exposedRules();
            expect($rules)->toHaveKey('name');
            expect($rules)->toHaveKey('email');
            expect($rules)->not->toHaveKey('id');
        });

        it('marks nullable columns as not required', function () {
            LazyMePHP::DB_CONNECTION()->query(
                "CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL, body TEXT)"
            );
            Model::clearSchemaCache();
            $controller = new class('posts', new Request()) extends CrudController {
                public function exposedRules(): array { return $this->validationRules(); }
            };
            $rules = $controller->exposedRules();
            expect($rules['title']['required'])->toBeTrue();
            expect($rules['body']['required'])->toBeFalse();
        });

        it('detects email columns by name heuristic', function () {
            $controller = new class('users', new Request()) extends CrudController {
                public function exposedRules(): array { return $this->validationRules(); }
            };
            $rules = $controller->exposedRules();
            expect($rules['email']['validations'])->toContain(\Core\Validations\ValidationsMethod::EMAIL);
        });
    });
});
