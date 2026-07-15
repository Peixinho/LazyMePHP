---
id: basic-crud
title: Basic CRUD
sidebar_position: 1
---

# Basic CRUD

`Core\Model` introspects the database schema at runtime and provides full CRUD without any code generation. There are no generated model files to maintain.

## Creating records

```php
use Core\Model;

$user = new Model('users');
$user->name  = 'Alice';
$user->email = 'alice@example.com';
$user->age   = 30;
$user->Save();

echo $user->getPrimaryKey(); // auto-populated after insert
```

## Reading records

```php
// By primary key
$user = new Model('users', 1);
echo $user->name;  // Alice

// Returns null-like model if not found — check ->exists
$user = Model::find('users', 999);  // null if not found
```

## Updating records

```php
$user = new Model('users', 1);
$user->name = 'Alice Smith';
$user->Save();  // issues UPDATE
```

## Deleting records

```php
$user = new Model('users', 1);
$user->Delete();
```

## Serialising

```php
$user->toArray();        // ['id' => 1, 'name' => 'Alice', ...]
$user->only(['id', 'name']); // subset
```

## Subclassing (optional)

You don't have to subclass anything, but you can for cleaner call sites:

```php
namespace Models;
use Core\Model;

class User extends Model {
    protected static string $table = 'users';
}

$user  = new User(1);
$users = User::query()->where('active', 1)->get();
```

## Transactions

```php
Model::transaction(function () {
    $order = new Model('orders');
    $order->user_id = 1;
    $order->Save();

    $item = new Model('order_items');
    $item->order_id  = $order->getPrimaryKey();
    $item->product_id = 42;
    $item->Save();
});
// Automatically rolled back on any exception.
```

## Bulk insert

```php
Model::insertMany('tags', [
    ['name' => 'php'],
    ['name' => 'framework'],
    ['name' => 'orm'],
]);
// Returns the number of rows inserted.
```
