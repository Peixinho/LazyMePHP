---
sidebar_position: 13
---

# Testing

LazyMePHP is built for testability. All tests use [Pest](https://pestphp.com/) and SQLite `:memory:` — no external services needed.

---

## `RefreshDatabase` — clean DB per test

Include this trait to get a fresh SQLite `:memory:` database automatically before every test.

```php
uses(\Core\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->setUpDatabase();                    // boots SQLite :memory:
    $this->createTable("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL
        )
    ");
});

test('can insert a user', function () {
    $user = new Model('users');
    $user->name  = 'Alice';
    $user->email = 'alice@example.com';
    $user->Save();

    expect($user->getPrimaryKey())->toBeGreaterThan(0);
});
```

`setUp()` / `tearDown()` hooks fire automatically when using `uses()`.

### `setUpDatabase()` — manual control

Call it explicitly if you need to run it at a specific point:

```php
$conn = $this->setUpDatabase(); // returns Core\DB\ISQL
```

### `createTable()`

Shorthand to execute DDL on the in-memory connection:

```php
$this->createTable("CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT)");
```

---

## `MakesHttpRequests` — HTTP testing

Test controllers and handlers without a real server. Handlers receive a `Request` and return a `Response`, array, or string.

```php
uses(\Core\Testing\MakesHttpRequests::class);

function usersHandler(Request $req): Response
{
    return Response::json([['id' => 1, 'name' => 'Alice']]);
}

test('GET /users returns a list', function () {
    $this->get('usersHandler', '/users')
         ->assertOk()
         ->assertJsonCount(1)
         ->assertJsonFragment(['name' => 'Alice']);
});
```

### Available HTTP methods

```php
$this->get($handler, $uri, $queryParams)
$this->post($handler, $uri, $data)
$this->put($handler, $uri, $data)
$this->patch($handler, $uri, $data)
$this->delete($handler, $uri, $data)

// JSON variants (sets Content-Type: application/json)
$this->getJson($handler, $uri)
$this->postJson($handler, $uri, $data)
// …
```

### Authentication

```php
// Bearer token
$this->withToken('my-jwt-token')->get('handler', '/me');

// Acting as a user (reads the 'token' field by default)
$user = ['id' => 1, 'token' => 'user-jwt'];
$this->actingAs($user)->get('handler', '/profile');

// Custom token field
$this->actingAs($admin, 'api_token')->get('handler', '/admin');
```

### Custom headers

```php
$this->withHeader('X-Tenant', 'acme')->post('handler', '/api/data', $payload);
```

---

## `TestResponse` — response assertions

Every call to `get()`, `post()`, etc. returns a `TestResponse` with a rich set of fluent assertions.

### Status

```php
->assertStatus(200)
->assertOk()           // 200
->assertCreated()      // 201
->assertAccepted()     // 202
->assertNoContent()    // 204
->assertNotFound()     // 404
->assertForbidden()    // 403
->assertUnauthorized() // 401
->assertUnprocessable() // 422
->assertSuccessful()   // 2xx
->assertRedirect('/dashboard')  // 3xx + optional Location check
```

### JSON

```php
->assertJson(['id' => 1, 'name' => 'Alice'])        // exact match
->assertJsonFragment(['name' => 'Alice'])            // subset match
->assertJsonPath('user.name', 'Alice')              // dot-notation key
->assertJsonCount(3)                                // root array length
->assertJsonCount(3, 'items')                       // nested array length
->assertJsonStructure(['id', 'name', 'email'])      // key presence
->assertJsonMissing(['password'])                   // key must be absent
```

### Body

```php
->assertSee('Hello World')           // substring present
->assertDontSee('Error')             // substring absent
->assertBodyIs('<h1>Hello</h1>')     // exact match
->assertEmpty()                      // empty body
```

### Headers

```php
->assertHeader('Content-Type', 'application/json')
->assertHeader('X-Request-Id')      // presence check
->assertHeaderMissing('X-Debug')
```

### Accessors

```php
$response->json()          // decoded array
$response->json('user.name') // dot-notation key
$response->body()          // raw string
$response->status()        // int
$response->header('X-Foo') // string|null (case-insensitive)
$response->headers()       // all headers
```

---

## Running tests

```bash
php vendor/bin/pest               # all tests
php vendor/bin/pest tests/Feature/UserTest.php  # single file
php vendor/bin/pest --filter "creates a user"   # by name
php vendor/bin/pest --no-coverage               # skip coverage
```

### Static analysis

```bash
php vendor/bin/phpstan analyse --memory-limit=512M
```

Both must pass before merging.

---

## Scaffold a test

```bash
php LazyMePHP make:test CreateUser
# → tests/Feature/CreateUserTest.php
```
