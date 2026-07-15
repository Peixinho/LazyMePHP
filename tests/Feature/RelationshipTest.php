<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Relationships\HasMany;
use Core\Relationships\HasOne;
use Core\Relationships\BelongsTo;
use Core\Relationships\BelongsToMany;

// ---------------------------------------------------------------------------
// Test model subclasses (defined here, not in App/Models)
// ---------------------------------------------------------------------------

class User extends Model
{
    protected static string $table = 'users';

    public function posts(): HasMany
    {
        return $this->hasMany('posts', 'user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne('profiles', 'user_id');
    }
}

class Post extends Model
{
    protected static string $table = 'posts';

    public function author(): BelongsTo
    {
        return $this->belongsTo('users', 'user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany('tags', 'post_tags', 'post_id', 'tag_id');
    }
}

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();

    $db->query("CREATE TABLE users (
        id    INTEGER PRIMARY KEY AUTOINCREMENT,
        name  TEXT NOT NULL
    )");
    $db->query("CREATE TABLE posts (
        id      INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title   TEXT NOT NULL
    )");
    $db->query("CREATE TABLE profiles (
        id      INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        bio     TEXT
    )");
    $db->query("CREATE TABLE tags (
        id   INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    )");
    $db->query("CREATE TABLE post_tags (
        post_id INTEGER NOT NULL,
        tag_id  INTEGER NOT NULL,
        PRIMARY KEY (post_id, tag_id)
    )");

    // Seed
    $db->query("INSERT INTO users (name) VALUES ('Alice'), ('Bob')");
    $db->query("INSERT INTO posts (user_id, title) VALUES (1,'Alpha'),(1,'Beta'),(2,'Gamma')");
    $db->query("INSERT INTO profiles (user_id, bio) VALUES (1,'Alice bio')");
    $db->query("INSERT INTO tags (name) VALUES ('php'),('orm'),('testing')");
    $db->query("INSERT INTO post_tags (post_id, tag_id) VALUES (1,1),(1,2),(2,2),(3,3)");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

// ---------------------------------------------------------------------------
// hasMany — lazy
// ---------------------------------------------------------------------------

describe('hasMany (lazy)', function () {
    it('returns related models via property access', function () {
        $user  = new User(null, 1);
        $posts = $user->posts;

        expect($posts)->toBeArray()->toHaveCount(2);
        expect($posts[0])->toBeInstanceOf(Model::class);
        expect($posts[0]->title)->toBe('Alpha');
        expect($posts[1]->title)->toBe('Beta');
    });

    it('caches the result so a second access does not re-query', function () {
        $user  = new User(null, 1);
        $first  = $user->posts;
        $second = $user->posts;
        expect($first)->toBe($second);
    });

    it('returns an empty array when no related records exist', function () {
        $db = LazyMePHP::DB_CONNECTION();
        $db->query("INSERT INTO users (name) VALUES ('Carol')");
        $carol = new User(null, 3);
        expect($carol->posts)->toBe([]);
    });
});

// ---------------------------------------------------------------------------
// hasMany — eager
// ---------------------------------------------------------------------------

describe('hasMany (eager)', function () {
    it('loads all related posts in one batch query', function () {
        $users = User::query()->with('posts')->get();

        expect($users)->toHaveCount(2);

        $alice = $users[0];
        $bob   = $users[1];

        expect($alice->getRelations())->toHaveKey('posts');
        expect($alice->posts)->toHaveCount(2);
        expect($bob->posts)->toHaveCount(1);
        expect($bob->posts[0]->title)->toBe('Gamma');
    });

    it('assigns an empty array to models with no related records', function () {
        $db = LazyMePHP::DB_CONNECTION();
        $db->query("INSERT INTO users (name) VALUES ('Carol')");

        $users = User::query()->with('posts')->get();
        $carol = array_filter($users, fn($u) => $u->name === 'Carol');
        $carol = array_values($carol)[0];

        expect($carol->posts)->toBe([]);
    });
});

// ---------------------------------------------------------------------------
// hasOne — lazy
// ---------------------------------------------------------------------------

describe('hasOne (lazy)', function () {
    it('returns a single related model', function () {
        $alice   = new User(null, 1);
        $profile = $alice->profile;

        expect($profile)->toBeInstanceOf(Model::class);
        expect($profile->bio)->toBe('Alice bio');
    });

    it('returns null when no related record exists', function () {
        $bob = new User(null, 2);
        expect($bob->profile)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// hasOne — eager
// ---------------------------------------------------------------------------

describe('hasOne (eager)', function () {
    it('batch-loads profiles for all users', function () {
        $users = User::query()->with('profile')->get();

        $alice = array_values(array_filter($users, fn($u) => $u->name === 'Alice'))[0];
        $bob   = array_values(array_filter($users, fn($u) => $u->name === 'Bob'))[0];

        expect($alice->profile->bio)->toBe('Alice bio');
        expect($bob->profile)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// belongsTo — lazy
// ---------------------------------------------------------------------------

describe('belongsTo (lazy)', function () {
    it('returns the parent model', function () {
        $post   = new Post(null, 1);
        $author = $post->author;

        expect($author)->toBeInstanceOf(Model::class);
        expect($author->name)->toBe('Alice');
    });

    it('returns null when the FK is null', function () {
        // create a post with no author (FK null requires nullable column)
        $db = LazyMePHP::DB_CONNECTION();
        // Recreate with nullable FK
        $db->query("CREATE TABLE orphans (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)");
        Model::clearSchemaCache();

        class Orphan extends Model {
            protected static string $table = 'orphans';
            public function owner(): BelongsTo { return $this->belongsTo('users', 'user_id'); }
        }

        $db->query("INSERT INTO orphans (user_id, title) VALUES (NULL, 'no owner')");
        $orphan = new Orphan(null, 1);
        expect($orphan->owner)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// belongsTo — eager
// ---------------------------------------------------------------------------

describe('belongsTo (eager)', function () {
    it('batch-loads parent models for all posts', function () {
        $posts = Post::query()->with('author')->get();

        expect($posts)->toHaveCount(3);
        expect($posts[0]->author->name)->toBe('Alice');
        expect($posts[2]->author->name)->toBe('Bob');
    });
});

// ---------------------------------------------------------------------------
// belongsToMany — lazy
// ---------------------------------------------------------------------------

describe('belongsToMany (lazy)', function () {
    it('returns related models through the pivot table', function () {
        $post = new Post(null, 1);
        $tags = $post->tags;

        expect($tags)->toHaveCount(2);
        $tagNames = array_map(fn($t) => $t->name, $tags);
        expect($tagNames)->toContain('php');
        expect($tagNames)->toContain('orm');
    });

    it('returns an empty array when no pivot rows exist', function () {
        $db = LazyMePHP::DB_CONNECTION();
        $db->query("INSERT INTO posts (user_id, title) VALUES (1, 'Untagged')");
        $post = new Post(null, 4);
        expect($post->tags)->toBe([]);
    });
});

// ---------------------------------------------------------------------------
// belongsToMany — eager
// ---------------------------------------------------------------------------

describe('belongsToMany (eager)', function () {
    it('batch-loads tags for all posts', function () {
        $posts = Post::query()->with('tags')->get();

        $tagCounts = array_map(fn($p) => count($p->tags), $posts);
        expect($tagCounts[0])->toBe(2); // Alpha: php, orm
        expect($tagCounts[1])->toBe(1); // Beta: orm
        expect($tagCounts[2])->toBe(1); // Gamma: testing
    });
});

// ---------------------------------------------------------------------------
// Multiple with() relations in one query
// ---------------------------------------------------------------------------

describe('multiple eager relations', function () {
    it('loads several relations in one get() call', function () {
        $users = User::query()->with('posts', 'profile')->get();

        $alice = $users[0];
        expect($alice->posts)->toHaveCount(2);
        expect($alice->profile->bio)->toBe('Alice bio');
    });
});

// ---------------------------------------------------------------------------
// toArray() includes loaded relations
// ---------------------------------------------------------------------------

describe('toArray()', function () {
    it('includes eagerly-loaded relations as nested arrays', function () {
        $users = User::query()->with('posts')->get();
        $arr   = $users[0]->toArray();

        expect($arr)->toHaveKey('posts');
        expect($arr['posts'][0])->toBeArray();
        expect($arr['posts'][0]['title'])->toBe('Alpha');
    });
});

// ---------------------------------------------------------------------------
// whereIn() directly on ModelQuery
// ---------------------------------------------------------------------------

describe('whereIn()', function () {
    it('filters by a list of values', function () {
        $posts = Model::query('posts')->whereIn('id', [1, 3])->get();
        expect($posts)->toHaveCount(2);
        $titles = array_map(fn($p) => $p->title, $posts);
        expect($titles)->toContain('Alpha');
        expect($titles)->toContain('Gamma');
    });

    it('returns no rows for an empty values list', function () {
        $posts = Model::query('posts')->whereIn('id', [])->get();
        expect($posts)->toBe([]);
    });
});
