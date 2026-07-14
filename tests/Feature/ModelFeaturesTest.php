<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\ModelQuery;
use Core\Events\ModelEvents;

// ---------------------------------------------------------------------------
// Model subclasses used across tests
// ---------------------------------------------------------------------------

class Product extends Model
{
    protected static string $table = 'products';

    // Local scope: ->active() or ->scope('active')
    public function scopeActive(ModelQuery $q): ModelQuery
    {
        return $q->where('active', 1);
    }

    public function scopeExpensive(ModelQuery $q, int $threshold = 100): ModelQuery
    {
        return $q->where('price', $threshold, '>=');
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
    ModelEvents::clearAll();
    ModelQuery::clearMemCache();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE products (
        id     INTEGER PRIMARY KEY AUTOINCREMENT,
        name   TEXT NOT NULL,
        price  REAL NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1
    )");

    for ($i = 1; $i <= 25; $i++) {
        $price  = $i * 10;
        $active = ($i % 5 === 0) ? 0 : 1;
        $db->query("INSERT INTO products (name, price, active) VALUES (?, ?, ?)", ["Product {$i}", $price, $active]);
    }
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
    ModelEvents::clearAll();
    ModelQuery::clearMemCache();
});

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

describe('ModelQuery::paginate()', function () {
    it('returns correct page metadata', function () {
        $page = Product::query()->paginate(10, 1);

        expect($page['total'])->toBe(25);
        expect($page['per_page'])->toBe(10);
        expect($page['current_page'])->toBe(1);
        expect($page['last_page'])->toBe(3);
        expect($page['from'])->toBe(1);
        expect($page['to'])->toBe(10);
        expect($page['data'])->toHaveCount(10);
    });

    it('returns the correct slice on page 2', function () {
        $page = Product::query()->paginate(10, 2);

        expect($page['current_page'])->toBe(2);
        expect($page['from'])->toBe(11);
        expect($page['to'])->toBe(20);
        expect($page['data'])->toHaveCount(10);
    });

    it('handles the last partial page correctly', function () {
        $page = Product::query()->paginate(10, 3);

        expect($page['to'])->toBe(25);
        expect($page['data'])->toHaveCount(5);
    });

    it('works with where filters', function () {
        $page = Product::query()->where('active', 1)->paginate(10, 1);

        // 5 out of 25 are inactive (every 5th)
        expect($page['total'])->toBe(20);
        expect($page['last_page'])->toBe(2);
    });
});

// ---------------------------------------------------------------------------
// Bulk update
// ---------------------------------------------------------------------------

describe('ModelQuery::update()', function () {
    it('updates all matching rows', function () {
        Model::query('products')->where('active', 0)->update(['active' => 1]);

        $stillInactive = Model::query('products')->where('active', 0)->count();
        expect($stillInactive)->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// Bulk delete
// ---------------------------------------------------------------------------

describe('ModelQuery::bulkDelete()', function () {
    it('deletes all matching rows', function () {
        Model::query('products')->where('active', 0)->bulkDelete();

        $count = Model::query('products')->count();
        expect($count)->toBe(20);
    });
});

// ---------------------------------------------------------------------------
// Bulk insert
// ---------------------------------------------------------------------------

describe('Model::insertMany()', function () {
    it('inserts multiple rows at once', function () {
        $inserted = Model::insertMany('products', [
            ['name' => 'Alpha', 'price' => 1.0, 'active' => 1],
            ['name' => 'Beta',  'price' => 2.0, 'active' => 1],
            ['name' => 'Gamma', 'price' => 3.0, 'active' => 0],
        ]);

        expect($inserted)->toBe(3);
        expect(Model::query('products')->count())->toBe(28);
    });

    it('returns 0 for an empty array', function () {
        expect(Model::insertMany('products', []))->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// Transactions
// ---------------------------------------------------------------------------

describe('Model::transaction()', function () {
    it('commits changes when the callback succeeds', function () {
        Model::transaction(function () {
            $p = new Product(null, null);
            $p->name   = 'Transacted';
            $p->price  = 99.0;
            $p->active = 1;
            $p->Save();
        });

        $found = Product::query()->where('name', 'Transacted')->first();
        expect($found)->not->toBeNull();
    });

    it('rolls back when the callback throws', function () {
        $before = Model::query('products')->count();

        try {
            Model::transaction(function () {
                $p = new Product(null, null);
                $p->name   = 'Will be rolled back';
                $p->price  = 0;
                $p->active = 1;
                $p->Save();
                throw new \RuntimeException('Deliberate error');
            });
        } catch (\RuntimeException) {}

        expect(Model::query('products')->count())->toBe($before);
    });
});

// ---------------------------------------------------------------------------
// Model scopes
// ---------------------------------------------------------------------------

describe('Local scopes', function () {
    it('applies a scope via ->scope()', function () {
        $active = Product::query()->scope('active')->get();
        expect($active)->toHaveCount(20);
    });

    it('applies a scope via magic method call', function () {
        $active = Product::query()->active()->get();
        expect($active)->toHaveCount(20);
    });

    it('passes arguments to scopes', function () {
        $expensive = Product::query()->scope('expensive', 200)->get();
        foreach ($expensive as $p) {
            expect((float)$p->price)->toBeGreaterThanOrEqual(200.0);
        }
    });

    it('chains multiple scopes', function () {
        $results = Product::query()->active()->scope('expensive', 100)->get();
        foreach ($results as $p) {
            expect((int)$p->active)->toBe(1);
            expect((float)$p->price)->toBeGreaterThanOrEqual(100.0);
        }
    });

    it('throws when the scope does not exist', function () {
        expect(fn() => Product::query()->scope('nonexistent'))
            ->toThrow(\BadMethodCallException::class);
    });
});

// ---------------------------------------------------------------------------
// Model events
// ---------------------------------------------------------------------------

describe('ModelEvents', function () {
    it('fires created after a successful insert', function () {
        $fired = false;
        ModelEvents::listen('products', 'created', function ($m) use (&$fired) {
            $fired = true;
        });

        $p = new Product(null, null);
        $p->name   = 'Event Test';
        $p->price  = 5.0;
        $p->active = 1;
        $p->Save();

        expect($fired)->toBeTrue();
    });

    it('fires updated after a successful update', function () {
        $fired = false;
        ModelEvents::listen('products', 'updated', function ($m) use (&$fired) {
            $fired = true;
        });

        $p = new Product(null, 1);
        $p->name = 'Changed';
        $p->Save();

        expect($fired)->toBeTrue();
    });

    it('fires deleted after Delete()', function () {
        $fired = false;
        ModelEvents::listen('products', 'deleted', function ($m) use (&$fired) {
            $fired = true;
        });

        $p = new Product(null, 1);
        $p->Delete();

        expect($fired)->toBeTrue();
    });

    it('cancels save when creating listener returns false', function () {
        ModelEvents::listen('products', 'creating', fn() => false);

        $before = Model::query('products')->count();

        $p = new Product(null, null);
        $p->name   = 'Cancelled';
        $p->price  = 1.0;
        $p->active = 1;
        $result = $p->Save();

        expect($result)->toBeFalse();
        expect(Model::query('products')->count())->toBe($before);
    });

    it('cancels delete when deleting listener returns false', function () {
        ModelEvents::listen('products', 'deleting', fn() => false);

        $p = new Product(null, 1);
        $result = $p->Delete();

        expect($result)->toBeFalse();
        expect(Model::query('products')->count())->toBe(25);
    });

    it('supports observer objects', function () {
        $log = [];
        $observer = new class($log) {
            public function __construct(private array &$log) {}
            public function created(mixed $m): void  { $this->log[] = 'created'; }
            public function updated(mixed $m): void  { $this->log[] = 'updated'; }
        };

        Model::observe('products', $observer);

        $p = new Product(null, null);
        $p->name   = 'Observer Test';
        $p->price  = 1.0;
        $p->active = 1;
        $p->Save();

        $p->name = 'Updated';
        $p->Save();

        expect($log)->toBe(['created', 'updated']);
    });
});

// ---------------------------------------------------------------------------
// Query caching (in-process)
// ---------------------------------------------------------------------------

describe('ModelQuery::remember()', function () {
    it('returns the same result from cache on the second call', function () {
        $first  = Product::query()->where('active', 1)->remember(60)->get();

        // Insert a new active product — should NOT appear in cached result
        $db = LazyMePHP::DB_CONNECTION();
        $db->query("INSERT INTO products (name, price, active) VALUES ('New', 1, 1)");

        $second = Product::query()->where('active', 1)->remember(60)->get();

        expect(count($first))->toBe(count($second)); // cache hit — same count
    });

    it('cache can be cleared', function () {
        $first = Product::query()->where('active', 1)->remember(60)->get();

        $db = LazyMePHP::DB_CONNECTION();
        $db->query("INSERT INTO products (name, price, active) VALUES ('New2', 1, 1)");

        ModelQuery::clearMemCache();

        $second = Product::query()->where('active', 1)->remember(60)->get();
        expect(count($second))->toBe(count($first) + 1);
    });
});
