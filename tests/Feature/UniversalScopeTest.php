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
    $db->query("CREATE TABLE posts (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        title     TEXT    NOT NULL,
        tenant_id INTEGER NOT NULL
    )");
    $db->query("CREATE TABLE tags (
        id   INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    )");
    $db->query("INSERT INTO posts (title, tenant_id) VALUES
        ('Post A', 1), ('Post B', 1), ('Post C', 2)");
    $db->query("INSERT INTO tags (name) VALUES ('php'), ('laravel')");
});

afterEach(function () {
    Model::removeUniversalScope('tenant');
    LazyMePHP::reset();
    Model::clearSchemaCache();
    ModelQuery::resetTableVersions();
});

describe('Model::addUniversalScope', function () {
    it('applies to all model queries', function () {
        Model::addUniversalScope('tenant', function (ModelQuery $q): void {
            if ($q->getTable() === 'posts') {
                $q->where('tenant_id', 1);
            }
        });

        $posts = Model::query('posts')->get();
        expect($posts)->toHaveCount(2);
        foreach ($posts as $p) {
            expect((int)$p->tenant_id)->toBe(1);
        }
    });

    it('does not affect tables that the scope skips', function () {
        Model::addUniversalScope('tenant', function (ModelQuery $q): void {
            if ($q->getTable() === 'posts') {
                $q->where('tenant_id', 1);
            }
        });

        $tags = Model::query('tags')->get();
        expect($tags)->toHaveCount(2);
    });

    it('can be bypassed with withoutGlobalScopes', function () {
        Model::addUniversalScope('tenant', function (ModelQuery $q): void {
            $q->where('tenant_id', 1);
        });

        $all = Model::withoutGlobalScopes('posts')->get();
        expect($all)->toHaveCount(3);
    });

    it('can be removed individually', function () {
        Model::addUniversalScope('tenant', function (ModelQuery $q): void {
            $q->where('tenant_id', 1);
        });
        Model::removeUniversalScope('tenant');

        $all = Model::query('posts')->get();
        expect($all)->toHaveCount(3);
    });

    it('getTable() returns the correct table name', function () {
        $captured = null;
        Model::addUniversalScope('capture', function (ModelQuery $q) use (&$captured): void {
            $captured = $q->getTable();
        });

        Model::query('posts')->get();
        Model::removeUniversalScope('capture');
        expect($captured)->toBe('posts');
    });
});
