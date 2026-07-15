---
sidebar_position: 16
---

# Collection

`Core\Collection` is a fluent, immutable-ish wrapper around PHP arrays. It implements `Countable`, `IteratorAggregate`, `ArrayAccess`, and `JsonSerializable`.

## Creating a collection

```php
use Core\Collection;

$c = Collection::make([1, 2, 3]);
$c = new Collection(['a' => 1, 'b' => 2]);
```

## From a query

```php
$users = User::query()->where('active', 1)->collect(); // returns Collection
```

## Transformations

```php
// Map
$names = $users->map(fn($u) => $u->name);

// Filter
$adults = $users->filter(fn($u) => $u->age >= 18);

// Reject falsy
$filled = Collection::make([0, 1, '', 'hello', null])->filter();

// Flat map
$tags = $posts->flatMap(fn($p) => $p->tags);

// Reverse, take, skip, chunk
$first5  = $users->take(5);
$page2   = $users->skip(20)->take(20);
$batches = $users->chunk(100);
```

## Sorting

```php
$sorted   = $users->sortBy('name');
$youngest = $users->sortBy('age');
$oldest   = $users->sortByDesc('age');

// Custom comparator
$sorted = $users->sortBy(fn($u) => strlen($u->name));
```

## Searching

```php
$first = $users->first();
$last  = $users->last();

// With condition
$admin = $users->first(fn($u) => $u->role === 'admin');

// Find by key (associative)
$value = $collection->get('key', 'default');

$exists = $users->contains(fn($u) => $u->id === 42);
```

## Filtering shortcuts

```php
$active  = $users->where('status', 'active');
$senior  = $users->where('age', '>=', 65);
$in      = $users->whereIn('role', ['admin', 'moderator']);
$notIn   = $users->whereNotIn('status', ['banned', 'suspended']);
```

## Grouping and indexing

```php
// Group by a key
$byRole = $users->groupBy('role');
// $byRole->get('admin') → Collection of admin users

// Key by a unique field
$byId = $users->keyBy('id');
// $byId->get(42) → single user

// Extract a column
$names   = $users->pluck('name');            // Collection of names
$nameMap = $users->pluck('name', 'id');      // id → name

// Count per group
$counts = $users->countBy('role');
// ['admin' => 3, 'user' => 97]
```

## Aggregates

```php
$total   = $orders->sum('amount');
$average = $orders->avg('amount');
$lowest  = $orders->min('amount');
$highest = $orders->max('amount');

// On a flat collection
$sum = Collection::make([1, 2, 3])->sum(); // 6
```

## Utilities

```php
$csv = $users->implode(', ', 'name');

// Tap (side-effect, returns self)
$users->tap(fn($c) => logger()->info("Processing {$c->count()} users"))
      ->each(fn($u) => $u->notify());

// Pipe (transform to any type)
$result = $users->pipe(fn($c) => $c->sum('points'));

// Unique values
$unique = $tags->unique();
$unique = $users->unique('email');

// Merge
$all = $admins->merge($moderators);

// Push (immutable — returns new collection)
$withNew = $users->push($newUser);

// Zip two collections together
$pairs = $keys->zip($values->all());
```

## Iteration and serialization

```php
// foreach works natively
foreach ($users as $user) { ... }

// Array access
$first = $users[0];
$count = count($users);

// JSON
json_encode($users);     // ["...", "..."]
(string) $users;         // same as json_encode

// Back to plain array
$array = $users->all();
$array = $users->toArray();
```
