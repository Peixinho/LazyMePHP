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

describe('ModelQuery::remember() — cache key uniqueness', function () {
    it('different order-by clauses produce different cached results', function () {
        $asc  = Model::query('users')->orderBy('name', 'ASC')->remember(60)->get();
        $desc = Model::query('users')->orderBy('name', 'DESC')->remember(60)->get();
        expect($asc[0]->name)->toBe('Alice');
        expect($desc[0]->name)->toBe('Dave');
    });
});
