---
sidebar_position: 3
title: Multiple Connections
---

# Multiple Database Connections

By default, LazyMePHP maintains a single "default" connection configured through your `.env` file.
When your application needs to talk to a second database (reporting database, legacy system, analytics store, etc.)
you can register named connections using the `DB` facade.

## Registering a Named Connection

Register additional connections early in your bootstrap or in `App/bootstrap.php`:

```php
use Core\DB\DB;

DB::addConnection('analytics', [
    'driver'   => 'mysql',      // 'mysql' | 'mssql' | 'sqlite'
    'database' => 'analytics',
    'username' => 'analyst',
    'password' => 'secret',
    'host'     => 'analytics-db.internal',
]);
```

Config keys:

| Key        | Required | Default     | Notes                                      |
|------------|----------|-------------|--------------------------------------------|
| `driver`   | No       | `mysql`     | `mysql`, `mssql`, or `sqlite`              |
| `database` | Yes      | —           | DB name, or file path / `:memory:` (SQLite)|
| `username` | No       | `''`        | Not used for SQLite                        |
| `password` | No       | `''`        | Not used for SQLite                        |
| `host`     | No       | `localhost` | Not used for SQLite                        |

## Using a Named Connection

```php
use Core\DB\DB;

// Default connection — same as LazyMePHP::DB_CONNECTION()
$db = DB::connection();

// Named connection
$analytics = DB::connection('analytics');

$result = $analytics->query('SELECT * FROM events WHERE date > ?', ['2024-01-01']);
while ($row = $result->fetchArray()) {
    // ...
}
```

## One-Off Connections

Create a connection without registering it under a name:

```php
use Core\DB\DB;

$temp = DB::connect([
    'driver'   => 'sqlite',
    'database' => '/tmp/import.sqlite',
]);

$temp->query('SELECT * FROM imported_data');
$temp->close();
```

## Checking and Removing Connections

```php
DB::has('analytics');       // bool
DB::remove('analytics');    // closes & deregisters
DB::reset();                // close & remove all named connections
```

## Using Raw Connections with Model::hydrate()

Named connections are regular `ISQL` instances, so you can use them with
[`Model::hydrate()`](../orm/raw-queries.md) to turn raw results into Model objects:

```php
use Core\Model;
use Core\DB\DB;

$rows    = DB::connection('analytics')->query('SELECT * FROM events')->fetchAll();
$models  = Model::hydrate('events', $rows);
```

## Important Notes

- **Each named connection is independent** — it never conflicts with the default singleton or with other named connections.
- **The default connection** (`DB::connection()` / `LazyMePHP::DB_CONNECTION()`) is still a singleton per process; all existing code that calls `LazyMePHP::DB_CONNECTION()` directly continues to work without changes.
- Named connections are **not** injected into `ModelQuery`. To query a second database through the ORM, use a raw query + `Model::hydrate()`.
- Connections are opened lazily — the underlying PDO socket is not established until the first query.
