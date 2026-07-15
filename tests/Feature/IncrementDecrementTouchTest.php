<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\ModelQuery;

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE items (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT    NOT NULL,
        views      INTEGER DEFAULT 0,
        stock      INTEGER DEFAULT 10,
        updated_at DATETIME
    )");
    $db->query("INSERT INTO items (name, views, stock) VALUES ('Widget', 5, 10), ('Gadget', 0, 3)");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
    ModelQuery::resetTableVersions();
});

describe('ModelQuery increment/decrement', function () {
    it('increments a column by 1', function () {
        Model::query('items')->where('id', 1)->increment('views');
        $row = Model::query('items')->where('id', 1)->first();
        expect((int)$row->views)->toBe(6);
    });

    it('increments by a custom amount', function () {
        Model::query('items')->where('id', 1)->increment('views', 10);
        $row = Model::query('items')->where('id', 1)->first();
        expect((int)$row->views)->toBe(15);
    });

    it('decrements a column', function () {
        Model::query('items')->where('id', 2)->decrement('stock', 2);
        $row = Model::query('items')->where('id', 2)->first();
        expect((int)$row->stock)->toBe(1);
    });

    it('sets extra columns in the same UPDATE', function () {
        $now = date('Y-m-d H:i:s');
        Model::query('items')->where('id', 1)->increment('views', 1, ['updated_at' => $now]);
        $row = Model::query('items')->where('id', 1)->first();
        expect((int)$row->views)->toBe(6);
        expect($row->updated_at)->toBe($now);
    });

    it('increments without WHERE affects all rows', function () {
        Model::query('items')->increment('views', 100);
        $rows = Model::query('items')->get();
        foreach ($rows as $r) {
            expect((int)$r->views)->toBeGreaterThanOrEqual(100);
        }
    });
});

describe('Model instance increment/decrement', function () {
    it('atomically increments on a loaded model', function () {
        $item = Model::query('items')->where('id', 1)->first();
        $item->increment('views');
        expect((int)$item->views)->toBe(6);

        // Reload from DB to confirm persistence
        $fresh = Model::query('items')->where('id', 1)->first();
        expect((int)$fresh->views)->toBe(6);
    });

    it('decrements on a loaded model', function () {
        $item = Model::query('items')->where('id', 1)->first();
        $item->decrement('stock', 3);
        expect((int)$item->stock)->toBe(7);
    });

    it('returns false on unsaved model', function () {
        $item = new Model('items');
        expect($item->increment('views'))->toBeFalse();
    });
});

describe('Model::touch', function () {
    it('updates updated_at to now', function () {
        $item = Model::query('items')->where('id', 1)->first();
        $before = $item->updated_at;
        $result = $item->touch();
        expect($result)->toBeTrue();
        expect($item->updated_at)->not->toBe($before);

        $fresh = Model::query('items')->where('id', 1)->first();
        expect($fresh->updated_at)->toBe($item->updated_at);
    });

    it('touches a named column', function () {
        $item = Model::query('items')->where('id', 1)->first();
        $item->touch('updated_at');
        expect($item->updated_at)->not->toBeNull();
    });

    it('returns false on unsaved model', function () {
        $item = new Model('items');
        expect($item->touch())->toBeFalse();
    });
});
