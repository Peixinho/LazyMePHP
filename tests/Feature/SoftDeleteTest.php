<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;
    protected static string $table = 'articles';
}

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE articles (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        title      TEXT NOT NULL,
        deleted_at TEXT NULL DEFAULT NULL
    )");
    $db->query("INSERT INTO articles (title) VALUES ('Alpha'), ('Beta'), ('Gamma')");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('SoftDeletes trait', function () {
    it('Delete() sets deleted_at instead of removing the row', function () {
        $article = new Article(null, 1);
        $article->Delete();

        $db  = LazyMePHP::DB_CONNECTION();
        $r   = $db->query('SELECT deleted_at FROM articles WHERE id = 1');
        $row = $r->fetchArray();

        expect($row['deleted_at'])->not->toBeNull();
        // Physical row still exists
        $r2  = $db->query('SELECT COUNT(*) as cnt FROM articles WHERE id = 1');
        expect((int)$r2->fetchArray()['cnt'])->toBe(1);
    });

    it('isTrashed() returns true after Delete()', function () {
        $article = new Article(null, 1);
        expect($article->isTrashed())->toBeFalse();
        $article->Delete();
        expect($article->isTrashed())->toBeTrue();
    });

    it('query()->get() excludes soft-deleted rows by default', function () {
        $article = new Article(null, 1);
        $article->Delete();

        $results = Article::query()->get();
        expect($results)->toHaveCount(2);
        $titles = array_map(fn($a) => $a->title, $results);
        expect($titles)->not->toContain('Alpha');
    });

    it('withTrashed() includes soft-deleted rows', function () {
        $article = new Article(null, 1);
        $article->Delete();

        $results = Article::query()->withTrashed()->get();
        expect($results)->toHaveCount(3);
    });

    it('onlyTrashed() returns only soft-deleted rows', function () {
        $article = new Article(null, 1);
        $article->Delete();

        $results = Article::query()->onlyTrashed()->get();
        expect($results)->toHaveCount(1);
        expect($results[0]->title)->toBe('Alpha');
    });

    it('restore() clears deleted_at', function () {
        $article = new Article(null, 1);
        $article->Delete();
        expect($article->isTrashed())->toBeTrue();

        $article->restore();
        expect($article->isTrashed())->toBeFalse();

        // Row shows up in normal queries again
        $results = Article::query()->get();
        expect($results)->toHaveCount(3);
    });

    it('count() also respects soft deletes', function () {
        $article = new Article(null, 1);
        $article->Delete();

        expect(Article::query()->count())->toBe(2);
        expect(Article::query()->withTrashed()->count())->toBe(3);
        expect(Article::query()->onlyTrashed()->count())->toBe(1);
    });
});
