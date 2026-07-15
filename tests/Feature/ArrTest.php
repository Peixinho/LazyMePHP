<?php

declare(strict_types=1);

use Core\Arr;

// ---------------------------------------------------------------------------
// Access
// ---------------------------------------------------------------------------

test('get returns value by simple key', function () {
    expect(Arr::get(['a' => 1], 'a'))->toBe(1);
    expect(Arr::get(['a' => 1], 'b', 99))->toBe(99);
});

test('get uses dot notation', function () {
    $arr = ['user' => ['name' => 'Alice', 'age' => 30]];
    expect(Arr::get($arr, 'user.name'))->toBe('Alice');
    expect(Arr::get($arr, 'user.missing', 'default'))->toBe('default');
});

test('set adds value via dot notation', function () {
    $arr = [];
    Arr::set($arr, 'user.name', 'Bob');
    expect($arr['user']['name'])->toBe('Bob');
});

test('has checks key existence with dot notation', function () {
    $arr = ['a' => ['b' => 1]];
    expect(Arr::has($arr, 'a.b'))->toBeTrue();
    expect(Arr::has($arr, 'a.c'))->toBeFalse();
    expect(Arr::has($arr, ['a.b', 'a']))->toBeTrue();
});

test('forget removes key by dot notation', function () {
    $arr = ['a' => ['b' => 1, 'c' => 2]];
    Arr::forget($arr, 'a.b');
    expect($arr)->toBe(['a' => ['c' => 2]]);
});

test('pull removes and returns a value', function () {
    $arr = ['a' => 1, 'b' => 2];
    expect(Arr::pull($arr, 'a'))->toBe(1);
    expect($arr)->toBe(['b' => 2]);
});

test('add only adds if key does not exist', function () {
    $arr = Arr::add(['a' => 1], 'b', 2);
    expect($arr)->toBe(['a' => 1, 'b' => 2]);

    $arr = Arr::add(['a' => 1], 'a', 99);
    expect($arr['a'])->toBe(1);
});

// ---------------------------------------------------------------------------
// Wrapping
// ---------------------------------------------------------------------------

test('wrap wraps scalars', function () {
    expect(Arr::wrap('hello'))->toBe(['hello']);
    expect(Arr::wrap(null))->toBe([]);
    expect(Arr::wrap([1, 2]))->toBe([1, 2]);
});

test('collapse flattens one level', function () {
    expect(Arr::collapse([[1, 2], [3, 4]]))->toBe([1, 2, 3, 4]);
});

test('prepend adds item to front', function () {
    expect(Arr::prepend([2, 3], 1))->toBe([1, 2, 3]);
    expect(Arr::prepend(['b' => 2], 1, 'a'))->toBe(['a' => 1, 'b' => 2]);
});

// ---------------------------------------------------------------------------
// Extraction
// ---------------------------------------------------------------------------

test('only keeps listed keys', function () {
    $arr = ['a' => 1, 'b' => 2, 'c' => 3];
    expect(Arr::only($arr, ['a', 'c']))->toBe(['a' => 1, 'c' => 3]);
});

test('except removes listed keys', function () {
    $arr = ['a' => 1, 'b' => 2, 'c' => 3];
    expect(Arr::except($arr, ['b']))->toBe(['a' => 1, 'c' => 3]);
});

test('pluck extracts a column', function () {
    $users = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
    expect(Arr::pluck($users, 'name'))->toBe(['Alice', 'Bob']);
});

test('first returns first matching item', function () {
    expect(Arr::first([1, 2, 3], fn($v) => $v > 1))->toBe(2);
    expect(Arr::first([]))->toBeNull();
    expect(Arr::first([], null, 'default'))->toBe('default');
});

test('last returns last matching item', function () {
    expect(Arr::last([1, 2, 3], fn($v) => $v < 3))->toBe(2);
    expect(Arr::last([1, 2, 3]))->toBe(3);
});

// ---------------------------------------------------------------------------
// Transformation
// ---------------------------------------------------------------------------

test('map applies callback preserving keys', function () {
    $result = Arr::map(['a' => 1, 'b' => 2], fn($v) => $v * 2);
    expect($result)->toBe(['a' => 2, 'b' => 4]);
});

test('mapWithKeys rebuilds keys', function () {
    $result = Arr::mapWithKeys([1, 2], fn($v) => ["key_{$v}" => $v * 10]);
    expect($result)->toBe(['key_1' => 10, 'key_2' => 20]);
});

test('flatMap maps and flattens', function () {
    $result = Arr::flatMap([1, 2], fn($v) => [$v, $v * 2]);
    expect($result)->toBe([1, 2, 2, 4]);
});

test('flatten flattens nested arrays', function () {
    expect(Arr::flatten([1, [2, [3, 4]]]))->toBe([1, 2, 3, 4]);
    expect(Arr::flatten([1, [2, [3, 4]]], 1))->toBe([1, 2, [3, 4]]);
});

test('keyBy keys array by a field', function () {
    $users = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
    $result = Arr::keyBy($users, 'id');
    expect(array_keys($result))->toBe([1, 2]);
});

test('groupBy groups items', function () {
    $items = [['type' => 'a', 'v' => 1], ['type' => 'b', 'v' => 2], ['type' => 'a', 'v' => 3]];
    $result = Arr::groupBy($items, 'type');
    expect(count($result['a']))->toBe(2);
    expect(count($result['b']))->toBe(1);
});

// ---------------------------------------------------------------------------
// Filtering
// ---------------------------------------------------------------------------

test('filter removes falsy values by default', function () {
    expect(array_values(Arr::filter([0, 1, '', 'a', null, false, true])))->toBe([1, 'a', true]);
});

test('where filters with callback', function () {
    $result = Arr::where([1, 2, 3, 4], fn($v) => $v > 2);
    expect(array_values($result))->toBe([3, 4]);
});

test('whereNotNull removes null values', function () {
    expect(array_values(Arr::whereNotNull([1, null, 2, null, 3])))->toBe([1, 2, 3]);
});

test('reject is the inverse of filter', function () {
    $result = Arr::reject([1, 2, 3], fn($v) => $v > 2);
    expect(array_values($result))->toBe([1, 2]);
});

// ---------------------------------------------------------------------------
// Sorting
// ---------------------------------------------------------------------------

test('sort sorts ascending', function () {
    expect(array_values(Arr::sort([3, 1, 2])))->toBe([1, 2, 3]);
});

test('sortDesc sorts descending', function () {
    expect(array_values(Arr::sortDesc([1, 3, 2])))->toBe([3, 2, 1]);
});

// ---------------------------------------------------------------------------
// Aggregation
// ---------------------------------------------------------------------------

test('reduce accumulates result', function () {
    expect(Arr::reduce([1, 2, 3], fn($carry, $item) => $carry + $item, 0))->toBe(6);
});

test('sum computes total', function () {
    expect(Arr::sum([1, 2, 3]))->toBe(6);
});

test('avg computes average', function () {
    expect(Arr::avg([2, 4, 6]))->toBe(4.0);
});

test('min and max', function () {
    expect(Arr::min([3, 1, 4]))->toBe(1);
    expect(Arr::max([3, 1, 4]))->toBe(4);
});

test('count returns element count', function () {
    expect(Arr::count([1, 2, 3]))->toBe(3);
});

test('countBy groups by computed key', function () {
    $result = Arr::countBy(['a', 'b', 'a', 'c', 'b', 'a']);
    expect($result['a'])->toBe(3);
    expect($result['b'])->toBe(2);
    expect($result['c'])->toBe(1);
});

test('some returns true if any passes', function () {
    expect(Arr::some([1, 2, 3], fn($v) => $v > 2))->toBeTrue();
    expect(Arr::some([1, 2, 3], fn($v) => $v > 5))->toBeFalse();
});

test('every returns true if all pass', function () {
    expect(Arr::every([2, 4, 6], fn($v) => $v % 2 === 0))->toBeTrue();
    expect(Arr::every([2, 3, 6], fn($v) => $v % 2 === 0))->toBeFalse();
});

test('none returns true if none pass', function () {
    expect(Arr::none([1, 2, 3], fn($v) => $v > 5))->toBeTrue();
    expect(Arr::none([1, 2, 3], fn($v) => $v > 2))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Joining
// ---------------------------------------------------------------------------

test('join concatenates with final glue', function () {
    expect(Arr::join(['a', 'b', 'c'], ', ', ' and '))->toBe('a, b and c');
    expect(Arr::join(['a', 'b', 'c'], ', '))->toBe('a, b, c');
});

// ---------------------------------------------------------------------------
// Slicing / chunking
// ---------------------------------------------------------------------------

test('chunk splits into smaller arrays', function () {
    expect(Arr::chunk([1, 2, 3, 4, 5], 2))->toBe([[1, 2], [3, 4], [5]]);
});

test('take returns first N items', function () {
    expect(Arr::take([1, 2, 3, 4], 2))->toBe([1, 2]);
    expect(Arr::take([1, 2, 3, 4], -2))->toBe([3, 4]);
});

test('skip skips N items', function () {
    expect(Arr::skip([1, 2, 3, 4], 2))->toBe([3, 4]);
});

test('nth returns every Nth element', function () {
    expect(Arr::nth([1, 2, 3, 4, 5, 6], 2))->toBe([1, 3, 5]);
});

// ---------------------------------------------------------------------------
// Type checks
// ---------------------------------------------------------------------------

test('isAssoc detects associative arrays', function () {
    expect(Arr::isAssoc(['a' => 1, 'b' => 2]))->toBeTrue();
    expect(Arr::isAssoc([1, 2, 3]))->toBeFalse();
    expect(Arr::isAssoc([]))->toBeFalse();
});

test('isList detects sequential arrays', function () {
    expect(Arr::isList([1, 2, 3]))->toBeTrue();
    expect(Arr::isList(['a' => 1]))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Dot notation
// ---------------------------------------------------------------------------

test('dot flattens to dot notation', function () {
    expect(Arr::dot(['a' => ['b' => 1, 'c' => 2]]))->toBe(['a.b' => 1, 'a.c' => 2]);
});

test('undot expands dot notation', function () {
    expect(Arr::undot(['a.b' => 1, 'a.c' => 2]))->toBe(['a' => ['b' => 1, 'c' => 2]]);
});

// ---------------------------------------------------------------------------
// Set operations
// ---------------------------------------------------------------------------

test('unique removes duplicates', function () {
    expect(Arr::unique([1, 2, 2, 3, 1]))->toBe([0 => 1, 1 => 2, 3 => 3]);
});

test('values reindexes array', function () {
    expect(Arr::values(['a' => 1, 'b' => 2]))->toBe([1, 2]);
});

test('keys returns all keys', function () {
    expect(Arr::keys(['a' => 1, 'b' => 2]))->toBe(['a', 'b']);
});

test('divide splits into keys and values', function () {
    [$keys, $values] = Arr::divide(['a' => 1, 'b' => 2]);
    expect($keys)->toBe(['a', 'b']);
    expect($values)->toBe([1, 2]);
});

test('crossJoin produces cartesian product', function () {
    $result = Arr::crossJoin([1, 2], ['a', 'b']);
    expect($result)->toBe([[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']]);
});

test('zip combines arrays element-wise', function () {
    expect(Arr::zip([1, 2], ['a', 'b']))->toBe([[1, 'a'], [2, 'b']]);
});

test('reverse reverses array', function () {
    expect(Arr::reverse([1, 2, 3]))->toBe([3, 2, 1]);
});

// ---------------------------------------------------------------------------
// Conversion
// ---------------------------------------------------------------------------

test('fromPairs builds array from pairs', function () {
    expect(Arr::fromPairs([['a', 1], ['b', 2]]))->toBe(['a' => 1, 'b' => 2]);
});

test('combine builds from keys and values', function () {
    expect(Arr::combine(['a', 'b'], [1, 2]))->toBe(['a' => 1, 'b' => 2]);
});

test('pad fills array to size', function () {
    expect(Arr::pad([1, 2], 5, 0))->toBe([1, 2, 0, 0, 0]);
});

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

test('contains finds item', function () {
    expect(Arr::contains([1, 2, 3], 2))->toBeTrue();
    expect(Arr::contains([1, 2, 3], 5))->toBeFalse();
});

test('search returns index of item', function () {
    expect(Arr::search([10, 20, 30], 20))->toBe(1);
    expect(Arr::search([10, 20, 30], 99))->toBeFalse();
});
