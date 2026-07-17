<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\ModelQuery;

class ActivePost extends Model
{
    protected static string $table = 'scope_posts';
    protected static array $globalScopes = [];
}

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    ActivePost::removeGlobalScope('active');  // reset between tests
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE scope_posts (
        id     INTEGER PRIMARY KEY AUTOINCREMENT,
        title  TEXT NOT NULL,
        active INTEGER NOT NULL DEFAULT 1
    )");
    $db->query("INSERT INTO scope_posts (title, active) VALUES ('Pub A', 1), ('Pub B', 1), ('Draft', 0)");
});

afterEach(function () {
    ActivePost::removeGlobalScope('active');
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('Global scopes', function () {
    it('applies the global scope to every query automatically', function () {
        ActivePost::addGlobalScope('active', fn(ModelQuery $q) => $q->where('active', 1));

        $results = ActivePost::query()->get();
        expect($results)->toHaveCount(2);
        foreach ($results as $p) {
            expect((int)$p->active)->toBe(1);
        }
    });

    it('does not affect count() without the scope', function () {
        expect(ActivePost::query()->count())->toBe(3);
    });

    it('count() also respects the global scope', function () {
        ActivePost::addGlobalScope('active', fn(ModelQuery $q) => $q->where('active', 1));
        expect(ActivePost::query()->count())->toBe(2);
    });

    it('withoutGlobalScopes() bypasses the scope', function () {
        ActivePost::addGlobalScope('active', fn(ModelQuery $q) => $q->where('active', 1));

        $all = ActivePost::withoutGlobalScopes()->get();
        expect($all)->toHaveCount(3);
    });

    it('removeGlobalScope() stops applying the scope', function () {
        ActivePost::addGlobalScope('active', fn(ModelQuery $q) => $q->where('active', 1));
        expect(ActivePost::query()->count())->toBe(2);

        ActivePost::removeGlobalScope('active');
        expect(ActivePost::query()->count())->toBe(3);
    });

    it('multiple global scopes are all applied', function () {
        ActivePost::addGlobalScope('active', fn(ModelQuery $q) => $q->where('active', 1));
        ActivePost::addGlobalScope('title_filter', fn(ModelQuery $q) => $q->where('title', 'Pub A'));

        $results = ActivePost::query()->get();
        expect($results)->toHaveCount(1);
        expect($results[0]->title)->toBe('Pub A');

        ActivePost::removeGlobalScope('title_filter');
    });

    it('applies the global scope to a direct-by-id lookup, not just query()', function () {
        // Regression test: new Model($table, $id) — which Model::find() and
        // GraphQL's single-record query both use — loaded via a raw SELECT that
        // bypassed global scopes entirely, so a record a scope was meant to hide
        // (soft-deleted, another tenant's, inactive, ...) could still be fetched
        // directly by id even though ->query()->get() would never include it.
        ActivePost::addGlobalScope('active', fn(ModelQuery $q) => $q->where('active', 1));

        $draft = ActivePost::withoutGlobalScopes()->where('title', 'Draft')->first();

        expect(ActivePost::find('scope_posts', $draft->getPrimaryKey()))->toBeNull();
        expect(ActivePost::find('scope_posts', 1)->title)->toBe('Pub A');
    });
});
