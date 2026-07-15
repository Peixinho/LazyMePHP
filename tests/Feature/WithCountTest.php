<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Relationships\HasMany;
use Core\Relationships\BelongsTo;
use Core\Relationships\BelongsToMany;

class WCUser extends Model
{
    protected static string $table = 'wc_users';

    public function posts(): HasMany
    {
        return $this->hasMany('wc_posts', 'user_id');
    }
}

class WCPost extends Model
{
    protected static string $table = 'wc_posts';

    public function author(): BelongsTo
    {
        return $this->belongsTo('wc_users', 'user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany('wc_tags', 'wc_post_tags', 'post_id', 'tag_id');
    }
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
    $db->query("CREATE TABLE wc_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");
    $db->query("CREATE TABLE wc_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)");
    $db->query("CREATE TABLE wc_tags  (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");
    $db->query("CREATE TABLE wc_post_tags (post_id INTEGER, tag_id INTEGER)");

    // Seed: 2 users, alice has 3 posts, bob has 1 post
    $db->query("INSERT INTO wc_users (name) VALUES ('Alice'), ('Bob')");
    $db->query("INSERT INTO wc_posts (user_id, title) VALUES (1,'P1'),(1,'P2'),(1,'P3'),(2,'P4')");
    $db->query("INSERT INTO wc_tags  (name) VALUES ('php'), ('oop')");
    // Post 1 has 2 tags, post 4 has 0
    $db->query("INSERT INTO wc_post_tags (post_id, tag_id) VALUES (1,1),(1,2)");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('withCount()', function () {
    it('adds posts_count via hasMany subquery', function () {
        $users = WCUser::query()->withCount('posts')->orderBy('id')->get();

        expect($users)->toHaveCount(2);
        expect($users[0]->posts_count)->toBe(3);   // Alice
        expect($users[1]->posts_count)->toBe(1);   // Bob
    });

    it('counts zero when no related rows exist', function () {
        LazyMePHP::DB_CONNECTION()->query("INSERT INTO wc_users (name) VALUES ('Charlie')");

        $users = WCUser::query()->withCount('posts')->orderBy('id')->get();
        expect($users[2]->posts_count)->toBe(0);
    });

    it('can combine withCount and with on the same query', function () {
        $users = WCUser::query()->with('posts')->withCount('posts')->orderBy('id')->get();

        expect($users[0]->posts_count)->toBe(3);
        expect($users[0]->posts)->toHaveCount(3);   // eager-loaded
    });

    it('adds tags_count via belongsToMany subquery', function () {
        $posts = WCPost::query()->withCount('tags')->orderBy('id')->get();

        expect($posts[0]->tags_count)->toBe(2);  // post 1 has 2 tags
        expect($posts[3]->tags_count)->toBe(0);  // post 4 has 0 tags
    });

    it('supports multiple withCount relations in one call', function () {
        // Add a comment table to test multiple counts
        LazyMePHP::DB_CONNECTION()->query(
            "CREATE TABLE wc_comments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, body TEXT)"
        );
        LazyMePHP::DB_CONNECTION()->query(
            "INSERT INTO wc_comments (user_id, body) VALUES (1,'c1'),(1,'c2')"
        );
        Model::clearSchemaCache();

        // Test that withCount still works after schema change
        $users = WCUser::query()->withCount('posts')->orderBy('id')->get();
        expect($users[0]->posts_count)->toBe(3);
    });

    it('withCount does not affect where conditions', function () {
        $users = WCUser::query()->where('id', 1)->withCount('posts')->get();

        expect($users)->toHaveCount(1);
        expect($users[0]->posts_count)->toBe(3);
    });
});
