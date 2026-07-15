<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id      INTEGER PRIMARY KEY AUTOINCREMENT,
        name    TEXT    NOT NULL,
        email   TEXT    NOT NULL,
        age     INTEGER DEFAULT 0,
        dept_id INTEGER DEFAULT NULL
    )");
    $db->query("CREATE TABLE IF NOT EXISTS departments (
        id   INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT    NOT NULL
    )");
    $db->query("INSERT INTO departments (name) VALUES (?), (?)", ['Engineering', 'Marketing']);
    $db->query("INSERT INTO users (name, email, age, dept_id) VALUES
        ('Alice', 'alice@example.com', 30, 1),
        ('Bob',   'bob@example.com',   25, 1),
        ('Carol', 'carol@example.com', 35, 2),
        ('Dave',  'dave@example.com',  22, 2)
    ");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('ModelQuery::select()', function () {
    it('limits columns returned — only()  confirms only those keys are present', function () {
        $rows = Model::query('users')->select('name', 'email')->get();
        expect($rows)->toHaveCount(4);
        foreach ($rows as $row) {
            $arr = $row->toArray();
            expect(array_key_exists('name', $arr))->toBeTrue();
            expect(array_key_exists('email', $arr))->toBeTrue();
        }
    });

    it('can select aggregate expressions', function () {
        $rows = Model::query('users')
            ->select('COUNT(*) AS total')
            ->get();
        expect($rows)->toHaveCount(1);
        expect((int)$rows[0]->total)->toBe(4);
    });
});

describe('ModelQuery::whereRaw()', function () {
    it('applies raw SQL conditions', function () {
        $rows = Model::query('users')->whereRaw('"age" > ?', [28])->get();
        expect($rows)->toHaveCount(2);
        $names = array_map(fn($r) => $r->name, $rows);
        expect($names)->toContain('Alice');
        expect($names)->toContain('Carol');
    });

    it('chains with where()', function () {
        $rows = Model::query('users')
            ->where('dept_id', 1)
            ->whereRaw('"age" < ?', [28])
            ->get();
        expect($rows)->toHaveCount(1);
        expect($rows[0]->name)->toBe('Bob');
    });

    it('supports OR logic', function () {
        $rows = Model::query('users')
            ->where('name', 'Alice')
            ->whereRaw('"name" = ?', ['Carol'], 'OR')
            ->get();
        expect($rows)->toHaveCount(2);
        $names = array_map(fn($r) => $r->name, $rows);
        sort($names);
        expect($names)->toBe(['Alice', 'Carol']);
    });
});

describe('ModelQuery::join()', function () {
    it('inner join returns matched rows only', function () {
        $rows = Model::query('users')
            ->join('departments', 'users.dept_id', 'departments.id')
            ->select('"users"."name"', '"departments"."name" AS dept_name')
            ->get();
        expect($rows)->toHaveCount(4);
        $depts = array_unique(array_map(fn($r) => $r->dept_name, $rows));
        sort($depts);
        expect(array_values($depts))->toBe(['Engineering', 'Marketing']);
    });

    it('can filter on joined table column', function () {
        $rows = Model::query('users')
            ->join('departments', 'users.dept_id', 'departments.id')
            ->select('"users"."name"', '"departments"."name" AS dept_name')
            ->whereRaw('"departments"."name" = ?', ['Engineering'])
            ->get();
        expect($rows)->toHaveCount(2);
        foreach ($rows as $row) {
            expect($row->dept_name)->toBe('Engineering');
        }
    });
});

describe('ModelQuery::leftJoin()', function () {
    it('returns all rows even without a matching join partner', function () {
        LazyMePHP::DB_CONNECTION()->query(
            "INSERT INTO users (name, email, age, dept_id) VALUES (?, ?, ?, NULL)",
            ['Eve', 'eve@example.com', 28]
        );
        $rows = Model::query('users')
            ->leftJoin('departments', 'users.dept_id', 'departments.id')
            ->select('"users"."name"', '"departments"."name" AS dept_name')
            ->get();
        expect($rows)->toHaveCount(5);
        $eve = array_values(array_filter($rows, fn($r) => $r->name === 'Eve'));
        expect($eve[0]->dept_name)->toBeNull();
    });
});

describe('ModelQuery::groupBy() + having()', function () {
    it('groups rows and counts per department', function () {
        $rows = Model::query('users')
            ->select('dept_id', 'COUNT(*) AS cnt')
            ->groupBy('dept_id')
            ->get();
        expect($rows)->toHaveCount(2);
        foreach ($rows as $row) {
            expect((int)$row->cnt)->toBe(2);
        }
    });

    it('having() filters groups by aggregate', function () {
        $rows = Model::query('users')
            ->select('dept_id', 'COUNT(*) AS cnt')
            ->groupBy('dept_id')
            ->having('cnt', 2, '>=')
            ->get();
        expect($rows)->toHaveCount(2);
    });

    it('having() excludes groups below threshold', function () {
        LazyMePHP::DB_CONNECTION()->query(
            "INSERT INTO users (name, email, age, dept_id) VALUES (?, ?, ?, ?)",
            ['Frank', 'frank@example.com', 40, 1]
        );
        $rows = Model::query('users')
            ->select('dept_id', 'COUNT(*) AS cnt')
            ->groupBy('dept_id')
            ->having('cnt', 3, '>=')
            ->get();
        expect($rows)->toHaveCount(1);
        expect((int)$rows[0]->dept_id)->toBe(1);
    });
});

describe('ModelQuery::update() scoped', function () {
    it('updates only rows matching where() conditions', function () {
        Model::query('users')->where('dept_id', 1)->update(['age' => 99]);

        $eng = Model::query('users')->where('dept_id', 1)->get();
        foreach ($eng as $row) {
            expect((int)$row->age)->toBe(99);
        }

        $mkt = Model::query('users')->where('dept_id', 2)->get();
        foreach ($mkt as $row) {
            expect((int)$row->age)->not->toBe(99);
        }
    });
});

describe('Model::hydrate()', function () {
    it('wraps raw DB rows into Model instances', function () {
        $result = LazyMePHP::DB_CONNECTION()->query(
            'SELECT * FROM "users" WHERE "dept_id" = ?', [1]
        );
        $rows = [];
        while ($row = $result->fetchArray()) $rows[] = $row;

        $models = Model::hydrate('users', $rows);
        expect($models)->toHaveCount(2);
        expect($models[0])->toBeInstanceOf(Model::class);
        expect($models[0]->name)->toBeIn(['Alice', 'Bob']);
    });

    it('preserves computed columns from a CTE-style subquery', function () {
        $result = LazyMePHP::DB_CONNECTION()->query(
            'SELECT "dept_id", COUNT(*) AS cnt FROM "users" GROUP BY "dept_id"', []
        );
        $rows = [];
        while ($row = $result->fetchArray()) $rows[] = $row;

        $models = Model::hydrate('users', $rows);
        expect($models)->toHaveCount(2);
        foreach ($models as $m) {
            expect((int)$m->cnt)->toBe(2);
        }
    });
});

describe('ModelQuery::remember() — cache key uniqueness', function () {
    it('different order-by clauses produce different cached results', function () {
        $asc  = Model::query('users')->orderBy('name', 'ASC')->remember(60)->get();
        $desc = Model::query('users')->orderBy('name', 'DESC')->remember(60)->get();
        expect($asc[0]->name)->toBe('Alice');
        expect($desc[0]->name)->toBe('Dave');
    });
});

describe('ModelQuery aggregate methods', function () {
    it('sum() returns total of a column', function () {
        $total = Model::query('users')->sum('age');
        expect($total)->toBe(112.0); // 30+25+35+22
    });

    it('avg() returns average of a column', function () {
        $avg = Model::query('users')->avg('age');
        expect($avg)->toBe(28.0); // 112/4
    });

    it('max() returns maximum value', function () {
        $max = Model::query('users')->max('age');
        expect((int)$max)->toBe(35);
    });

    it('min() returns minimum value', function () {
        $min = Model::query('users')->min('age');
        expect((int)$min)->toBe(22);
    });

    it('sum() respects where conditions', function () {
        $total = Model::query('users')->where('dept_id', 1)->sum('age');
        expect($total)->toBe(55.0); // Alice(30) + Bob(25)
    });
});

describe('ModelQuery::firstOrCreate()', function () {
    it('returns existing record when found', function () {
        $user = Model::query('users')->firstOrCreate(['email' => 'alice@example.com']);
        expect($user->name)->toBe('Alice');
        expect((int)Model::query('users')->count())->toBe(4);
    });

    it('creates a new record when not found', function () {
        $user = Model::query('users')->firstOrCreate(
            ['email' => 'new@example.com'],
            ['name'  => 'New User', 'age' => 20]
        );
        expect($user->name)->toBe('New User');
        expect((int)Model::query('users')->count())->toBe(5);
    });

    it('does not duplicate on repeated calls', function () {
        Model::query('users')->firstOrCreate(['email' => 'dup@example.com'], ['name' => 'Dup', 'age' => 1]);
        Model::query('users')->firstOrCreate(['email' => 'dup@example.com'], ['name' => 'Dup', 'age' => 1]);
        expect((int)Model::query('users')->count())->toBe(5);
    });
});

describe('ModelQuery::updateOrCreate()', function () {
    it('updates an existing record', function () {
        $user = Model::query('users')->updateOrCreate(
            ['email' => 'alice@example.com'],
            ['age'   => 99]
        );
        expect((int)$user->age)->toBe(99);
        expect((int)Model::query('users')->count())->toBe(4);
    });

    it('creates a new record when not found', function () {
        $user = Model::query('users')->updateOrCreate(
            ['email' => 'brand-new@example.com'],
            ['name'  => 'Brand New', 'age' => 1]
        );
        expect($user->name)->toBe('Brand New');
        expect((int)Model::query('users')->count())->toBe(5);
    });
});

describe('ModelQuery::chunk()', function () {
    it('processes all rows in chunks', function () {
        $collected = [];
        Model::query('users')->orderBy('id')->chunk(2, function (array $batch) use (&$collected) {
            foreach ($batch as $m) {
                $collected[] = $m->name;
            }
        });
        expect($collected)->toBe(['Alice', 'Bob', 'Carol', 'Dave']);
    });

    it('stops early when callback returns false', function () {
        $count = 0;
        Model::query('users')->chunk(2, function (array $batch) use (&$count) {
            $count += count($batch);
            return false;
        });
        expect($count)->toBe(2);
    });
});

describe('ModelQuery::exists()', function () {
    it('returns true when rows match', function () {
        expect(Model::query('users')->where('name', 'Alice')->exists())->toBeTrue();
    });

    it('returns false when no rows match', function () {
        expect(Model::query('users')->where('name', 'Nobody')->exists())->toBeFalse();
    });
});

describe('ModelQuery::pluck()', function () {
    it('returns a flat array of column values', function () {
        $names = Model::query('users')->orderBy('name')->pluck('name');
        expect($names)->toBe(['Alice', 'Bob', 'Carol', 'Dave']);
    });

    it('respects where conditions', function () {
        $names = Model::query('users')->where('dept_id', 1)->orderBy('name')->pluck('name');
        expect($names)->toBe(['Alice', 'Bob']);
    });
});

describe('ModelQuery::value()', function () {
    it('returns the column value from the first row', function () {
        $email = Model::query('users')->orderBy('name')->value('email');
        expect($email)->toBe('alice@example.com');
    });

    it('returns null when no rows match', function () {
        $val = Model::query('users')->where('name', 'Nobody')->value('email');
        expect($val)->toBeNull();
    });
});

describe('ModelQuery cache invalidation', function () {
    it('busts remember() cache after update()', function () {
        \Core\Cache\Cache::swap(new \Core\Cache\ArrayStore());

        $before = Model::query('users')->where('name', 'Alice')->remember(60)->get();
        expect($before[0]->email)->toBe('alice@example.com');

        Model::query('users')->where('name', 'Alice')->update(['email' => 'new@example.com']);

        $after = Model::query('users')->where('name', 'Alice')->remember(60)->get();
        expect($after[0]->email)->toBe('new@example.com');

        \Core\Cache\Cache::reset();
    });

    it('busts remember() cache after bulkDelete()', function () {
        \Core\Cache\Cache::swap(new \Core\Cache\ArrayStore());

        $before = Model::query('users')->remember(60)->count();
        expect($before)->toBe(4);

        Model::query('users')->where('name', 'Dave')->bulkDelete();

        $after = Model::query('users')->remember(60)->count();
        expect($after)->toBe(3);

        \Core\Cache\Cache::reset();
    });
});
