<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Relationships\HasMany;

// ---------------------------------------------------------------------------
// Models
// ---------------------------------------------------------------------------

class AgUser extends Model
{
    protected static string $table = 'ag_users';

    public function orders(): HasMany
    {
        return $this->hasMany('ag_orders', 'user_id');
    }
}

// ---------------------------------------------------------------------------
// Setup
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
    $db->query("CREATE TABLE ag_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");
    $db->query("CREATE TABLE ag_orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, amount REAL, score INTEGER)");

    // Alice: 3 orders — amounts 10, 20, 30 | scores 1, 2, 3
    // Bob:   1 order  — amount  50         | score  5
    $db->query("INSERT INTO ag_users (name) VALUES ('Alice'), ('Bob')");
    $db->query("INSERT INTO ag_orders (user_id, amount, score) VALUES (1,10,1),(1,20,2),(1,30,3),(2,50,5)");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('withAvg()', function () {
    it('returns the average of the related column', function () {
        $users = AgUser::query()->withAvg('orders', 'amount')->orderBy('id')->get();

        expect($users[0]->orders_avg_amount)->toBe(20.0);   // (10+20+30)/3
        expect($users[1]->orders_avg_amount)->toBe(50.0);   // 50/1
    });

    it('returns null for users with no orders', function () {
        LazyMePHP::DB_CONNECTION()->query("INSERT INTO ag_users (name) VALUES ('Charlie')");
        Model::clearSchemaCache();

        $users = AgUser::query()->withAvg('orders', 'amount')->orderBy('id')->get();
        expect($users[2]->orders_avg_amount)->toBeNull();
    });
});

describe('withSum()', function () {
    it('returns the sum of the related column', function () {
        $users = AgUser::query()->withSum('orders', 'amount')->orderBy('id')->get();

        expect($users[0]->orders_sum_amount)->toBe(60.0);   // 10+20+30
        expect($users[1]->orders_sum_amount)->toBe(50.0);
    });
});

describe('withMin()', function () {
    it('returns the minimum of the related column', function () {
        $users = AgUser::query()->withMin('orders', 'amount')->orderBy('id')->get();

        expect($users[0]->orders_min_amount)->toBe(10.0);
        expect($users[1]->orders_min_amount)->toBe(50.0);
    });
});

describe('withMax()', function () {
    it('returns the maximum of the related column', function () {
        $users = AgUser::query()->withMax('orders', 'amount')->orderBy('id')->get();

        expect($users[0]->orders_max_amount)->toBe(30.0);
        expect($users[1]->orders_max_amount)->toBe(50.0);
    });
});

describe('combined aggregates', function () {
    it('can chain multiple aggregate methods on a single query', function () {
        $users = AgUser::query()
            ->withSum('orders', 'amount')
            ->withAvg('orders', 'score')
            ->orderBy('id')
            ->get();

        expect($users[0]->orders_sum_amount)->toBe(60.0);
        expect($users[0]->orders_avg_score)->toBe(2.0);  // (1+2+3)/3
    });

    it('can combine withCount and withSum on the same query', function () {
        $users = AgUser::query()
            ->withCount('orders')
            ->withSum('orders', 'amount')
            ->orderBy('id')
            ->get();

        expect($users[0]->orders_count)->toBe(3);
        expect($users[0]->orders_sum_amount)->toBe(60.0);
    });

    it('alias naming follows relation_fn_column pattern', function () {
        $users = AgUser::query()->withMin('orders', 'score')->get();

        expect(property_exists($users[0], 'orders_min_score') || isset($users[0]->orders_min_score))->toBeTrue();
    });
});
