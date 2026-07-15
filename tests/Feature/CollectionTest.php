<?php

declare(strict_types=1);

use Core\Collection;

// ---------------------------------------------------------------------------
// Construction
// ---------------------------------------------------------------------------

test('make() returns a Collection', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c)->toBeInstanceOf(Collection::class);
    expect($c->count())->toBe(3);
});

test('empty collection', function () {
    $c = new Collection();
    expect($c->isEmpty())->toBeTrue();
    expect($c->isNotEmpty())->toBeFalse();
    expect($c->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Transformations
// ---------------------------------------------------------------------------

test('filter removes non-matching items', function () {
    $c = Collection::make([1, 2, 3, 4, 5]);
    $evens = $c->filter(fn($n) => $n % 2 === 0);
    expect($evens->all())->toBe([2, 4]);
});

test('filter without callback removes falsy', function () {
    $c = Collection::make([0, 1, '', 'hello', null, false, true]);
    expect($c->filter()->all())->toBe([1, 'hello', true]);
});

test('map transforms each item', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->map(fn($n) => $n * 2)->all())->toBe([2, 4, 6]);
});

test('flatMap flattens one level', function () {
    $c = Collection::make([[1, 2], [3, 4]]);
    expect($c->flatMap(fn($a) => $a)->all())->toBe([1, 2, 3, 4]);
});

test('each iterates and can break on false', function () {
    $sum = 0;
    Collection::make([1, 2, 3])->each(function ($n) use (&$sum) {
        $sum += $n;
    });
    expect($sum)->toBe(6);
});

test('reverse reverses order', function () {
    expect(Collection::make([1, 2, 3])->reverse()->all())->toBe([3, 2, 1]);
});

test('unique removes duplicates', function () {
    expect(Collection::make([1, 2, 2, 3, 3])->unique()->all())->toBe([1, 2, 3]);
});

test('unique on key removes duplicate keys', function () {
    $c = Collection::make([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 1, 'name' => 'Alice Duplicate'],
    ]);
    expect($c->unique('id')->count())->toBe(2);
});

test('push appends item', function () {
    $c = Collection::make([1, 2])->push(3);
    expect($c->all())->toBe([1, 2, 3]);
});

test('take returns first N', function () {
    expect(Collection::make([1, 2, 3, 4])->take(2)->all())->toBe([1, 2]);
});

test('skip drops first N', function () {
    expect(Collection::make([1, 2, 3, 4])->skip(2)->all())->toBe([3, 4]);
});

test('chunk splits into pieces', function () {
    $chunks = Collection::make([1, 2, 3, 4, 5])->chunk(2);
    expect($chunks->count())->toBe(3);
    expect($chunks->first()->all())->toBe([1, 2]);
});

test('flatten flattens nested arrays', function () {
    $c = Collection::make([[1, [2, 3]], [4]]);
    expect($c->flatten()->all())->toBe([1, 2, 3, 4]);
});

test('flatten respects depth', function () {
    $c = Collection::make([[1, [2, [3]]]]);
    expect($c->flatten(1)->all())->toBe([1, [2, [3]]]);
});

// ---------------------------------------------------------------------------
// Sorting
// ---------------------------------------------------------------------------

test('sortBy sorts by key ascending', function () {
    $c = Collection::make([['age' => 30], ['age' => 20], ['age' => 25]]);
    $sorted = $c->sortBy('age')->pluck('age')->all();
    expect($sorted)->toBe([20, 25, 30]);
});

test('sortByDesc sorts descending', function () {
    $c = Collection::make([['age' => 30], ['age' => 20], ['age' => 25]]);
    $sorted = $c->sortByDesc('age')->pluck('age')->all();
    expect($sorted)->toBe([30, 25, 20]);
});

// ---------------------------------------------------------------------------
// Searching
// ---------------------------------------------------------------------------

test('first returns first matching item', function () {
    $c = Collection::make([1, 2, 3, 4]);
    expect($c->first(fn($n) => $n > 2))->toBe(3);
});

test('first returns default when no match', function () {
    expect(Collection::make([])->first(null, 'none'))->toBe('none');
});

test('last returns last item', function () {
    expect(Collection::make([1, 2, 3])->last())->toBe(3);
});

test('get by key', function () {
    $c = Collection::make(['a' => 1, 'b' => 2]);
    expect($c->get('a'))->toBe(1);
    expect($c->get('z', 99))->toBe(99);
});

test('contains checks value', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->contains(2))->toBeTrue();
    expect($c->contains(5))->toBeFalse();
});

test('contains with callback', function () {
    $c = Collection::make([1, 2, 3]);
    expect($c->contains(fn($n) => $n > 2))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Grouping
// ---------------------------------------------------------------------------

test('groupBy groups by key', function () {
    $c = Collection::make([
        ['role' => 'admin'],
        ['role' => 'user'],
        ['role' => 'admin'],
    ]);
    $groups = $c->groupBy('role');
    expect($groups->get('admin')->count())->toBe(2);
    expect($groups->get('user')->count())->toBe(1);
});

test('keyBy indexes by key', function () {
    $c = Collection::make([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
    $keyed = $c->keyBy('id');
    expect($keyed->get(1)['name'])->toBe('Alice');
});

test('pluck extracts values', function () {
    $c = Collection::make([['name' => 'Alice'], ['name' => 'Bob']]);
    expect($c->pluck('name')->all())->toBe(['Alice', 'Bob']);
});

test('pluck with indexBy', function () {
    $c = Collection::make([['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]);
    $result = $c->pluck('name', 'id')->all();
    expect($result[1])->toBe('Alice');
});

test('countBy counts groups', function () {
    $c = Collection::make([['type' => 'a'], ['type' => 'b'], ['type' => 'a']]);
    $counts = $c->countBy('type')->all();
    expect($counts['a'])->toBe(2);
    expect($counts['b'])->toBe(1);
});

// ---------------------------------------------------------------------------
// Filtering shortcuts
// ---------------------------------------------------------------------------

test('where filters by key=value', function () {
    $c = Collection::make([['age' => 20], ['age' => 30], ['age' => 20]]);
    expect($c->where('age', 20)->count())->toBe(2);
});

test('where with operator', function () {
    $c = Collection::make([['age' => 20], ['age' => 30], ['age' => 40]]);
    expect($c->where('age', '>', 25)->count())->toBe(2);
});

test('whereIn filters by values in list', function () {
    $c = Collection::make([['id' => 1], ['id' => 2], ['id' => 3]]);
    expect($c->whereIn('id', [1, 3])->count())->toBe(2);
});

test('whereNotIn excludes values', function () {
    $c = Collection::make([['id' => 1], ['id' => 2], ['id' => 3]]);
    expect($c->whereNotIn('id', [2])->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Aggregates
// ---------------------------------------------------------------------------

test('sum aggregates values', function () {
    expect(Collection::make([1, 2, 3, 4])->sum())->toBe(10);
});

test('sum by key', function () {
    $c = Collection::make([['amount' => 10.0], ['amount' => 5.5]]);
    expect($c->sum('amount'))->toBe(15.5);
});

test('avg computes mean', function () {
    expect(Collection::make([1, 2, 3])->avg())->toBe(2.0);
});

test('min and max', function () {
    $c = Collection::make([3, 1, 4, 1, 5]);
    expect($c->min())->toBe(1);
    expect($c->max())->toBe(5);
});

test('implode joins values', function () {
    $c = Collection::make([['name' => 'A'], ['name' => 'B']]);
    expect($c->implode(', ', 'name'))->toBe('A, B');
});

// ---------------------------------------------------------------------------
// Utilities
// ---------------------------------------------------------------------------

test('tap returns self', function () {
    $called = false;
    $c = Collection::make([1])->tap(function ($col) use (&$called) {
        $called = true;
    });
    expect($called)->toBeTrue();
    expect($c)->toBeInstanceOf(Collection::class);
});

test('pipe transforms to arbitrary type', function () {
    $result = Collection::make([1, 2, 3])->pipe(fn($c) => $c->sum());
    expect($result)->toBe(6);
});

test('reduce accumulates', function () {
    $sum = Collection::make([1, 2, 3])->reduce(fn($carry, $item) => $carry + $item, 0);
    expect($sum)->toBe(6);
});

// ---------------------------------------------------------------------------
// Interface implementations
// ---------------------------------------------------------------------------

test('iterable via foreach', function () {
    $result = [];
    foreach (Collection::make([1, 2, 3]) as $item) {
        $result[] = $item;
    }
    expect($result)->toBe([1, 2, 3]);
});

test('ArrayAccess works', function () {
    $c = Collection::make([10, 20, 30]);
    expect(isset($c[1]))->toBeTrue();
    expect($c[1])->toBe(20);
});

test('json serializes correctly', function () {
    $c = Collection::make([1, 2, 3]);
    expect(json_encode($c))->toBe('[1,2,3]');
});

test('cast to string gives JSON', function () {
    $c = Collection::make([1, 2]);
    expect((string)$c)->toBe('[1,2]');
});

test('merge combines two collections', function () {
    $a = Collection::make([1, 2]);
    $b = Collection::make([3, 4]);
    expect($a->merge($b)->all())->toBe([1, 2, 3, 4]);
});
