---
sidebar_position: 10
---

# Helpers

LazyMePHP ships a collection of global helper functions and two fluent utility classes — `Str` and `Arr` — that cover the most common string and array operations.

## Global functions

### `config()` / `config_set()`

Read or write environment-backed configuration values.

```php
$debug = config('APP_DEBUG', false);
config_set('APP_DEBUG', 'true');
```

### `app()`

Resolve a class from the **service container**, or get the container itself.

```php
$container = app();                           // Container instance
$mailer    = app(\App\Services\Mailer::class); // resolved instance
```

### `route()`

Generate a URL by **named route**. The route must have been registered with `->setName()`.

```php
// In Routes.php:
SimpleRouter::get('/users/{id}', fn($id) => ...)->setName('users.show');

// Anywhere else:
echo route('users.show', ['id' => 42]);  // /users/42
echo route('users.index');               // /users
echo route('search', null, ['q' => 'php']); // /search?q=php
```

### `url()`

Absolute URL from an optional path.

```php
url()          // https://example.com
url('/users')  // https://example.com/users
```

### `now()`

Current `DateTimeImmutable`, with an optional timezone.

```php
now()->format('Y-m-d H:i:s')
now('America/New_York')->format('H:i')
```

### `abort()`

Throw an `HttpException` to stop the request immediately.

```php
abort(404);
abort(403, 'You do not have access.');
```

### `back()` / `redirect()`

Header-based redirects. Both call `exit`.

```php
back();             // redirect to HTTP_REFERER, or '/' if absent
redirect('/login'); // redirect to an explicit URL
```

### `old()`

Retrieve a previously submitted form value from the session flash.  
Flash the old input before redirecting on validation failure:

```php
// In your controller:
Session::flash('__old', $request->all());
back();

// In your Blade view:
<input name="email" value="<?= old('email') ?>">
<input name="email" value="<?= old('email', 'placeholder@example.com') ?>">
```

### `errors()`

Retrieve validation errors from the session flash.

```php
// In your controller:
Session::flash('__errors', $validator->errors());
back();

// In your Blade view:
@if(errors('email'))
    <span class="error">{{ errors('email') }}</span>
@endif

// All errors:
$all = errors(); // ['email' => 'The email field is required.', ...]
```

### `str()` / `arr()`

Fluent proxy access to `Str` and `Arr` statics (see below).

```php
str('hello world')->slug()      // "hello-world"
arr($users)->pluck('email')->all()  // ['a@…', 'b@…']
```

### `__()` / `trans()`

Translate a key via the [Translator](./translation.md).

```php
__('auth.failed')
__('messages.welcome', ['name' => 'Alice'])
```

---

## `Str` — string utilities

```php
use Core\Str;
```

### Case conversion

| Method | Example | Result |
|--------|---------|--------|
| `Str::camel($value)` | `'hello_world'` | `'helloWorld'` |
| `Str::studly($value)` | `'hello-world'` | `'HelloWorld'` |
| `Str::snake($value)` | `'helloWorld'` | `'hello_world'` |
| `Str::kebab($value)` | `'helloWorld'` | `'hello-world'` |
| `Str::title($value)` | `'hello world'` | `'Hello World'` |
| `Str::upper($value)` | `'hello'` | `'HELLO'` |
| `Str::lower($value)` | `'HELLO'` | `'hello'` |
| `Str::headline($value)` | `'hello_world'` | `'Hello World'` |

### URLs & slugs

```php
Str::slug('Hello World')           // "hello-world"
Str::slug('Hello World', '_')      // "hello_world"
Str::ascii('Ünïcödé')             // "Unicode"
```

### Searching / testing

```php
Str::contains('hello world', 'world')          // true
Str::containsAll('hello world', ['hello', 'world']) // true
Str::startsWith('hello', 'hel')               // true
Str::endsWith('hello.php', '.php')            // true
Str::is('foo.*', 'foo.bar')                   // true  (glob-style wildcard)
Str::isJson('{"a":1}')                        // true
Str::isUrl('https://example.com')             // true
Str::isEmail('user@example.com')              // true
Str::isUuid('550e8400-e29b-…')               // true
```

### Substring / length

```php
Str::length('hello')              // 5
Str::substr('hello world', 6)     // "world"
Str::limit('a long string', 5)    // "a lon..."
Str::words('one two three', 2)    // "one two..."
Str::take('hello', 3)             // "hel"
Str::take('hello', -2)            // "lo"
Str::charAt('hello', 1)           // "e"
```

### Extraction

```php
Str::before('user@example.com', '@')       // "user"
Str::after('user@example.com', '@')        // "example.com"
Str::beforeLast('foo.bar.baz', '.')        // "foo.bar"
Str::afterLast('foo.bar.baz', '.')         // "baz"
Str::between('[content]', '[', ']')        // "content"
```

### Manipulation

```php
Str::replace('foo', 'bar', 'foo baz foo')  // "bar baz bar"
Str::replaceFirst('a', 'b', 'aaa')         // "baa"
Str::replaceLast('a', 'b', 'aaa')          // "aab"
Str::remove(['a', 'e'], 'hello')           // "hllo"
Str::finish('/users', '/')                 // "/users/"  (idempotent)
Str::start('users', '/')                   // "/users"   (idempotent)
Str::wrap('hello', '"')                    // '"hello"'
Str::wrap('hello', '<b>', '</b>')          // "<b>hello</b>"
Str::reverse('hello')                      // "olleh"
Str::repeat('ab', 3)                       // "ababab"
Str::squish('  hello   world  ')           // "hello world"
Str::mask('4111111111111111', '*', 4, 8)   // "4111********1111"
```

### Padding / trimming

```php
Str::padLeft('5', 3, '0')   // "005"
Str::padRight('hi', 5)      // "hi   "
Str::trim('  hello  ')      // "hello"
Str::ltrim('--hello', '-')  // "hello"
```

### Random / UUID

```php
Str::random(16)   // "a1b2c3d4e5f60708"  (hex, 16 chars)
Str::uuid()       // "550e8400-e29b-41d4-a716-446655440000"
```

### Pluralization

```php
Str::plural('cat')        // "cats"
Str::plural('city')       // "cities"
Str::plural('person')     // "people"
Str::plural('cat', 1)     // "cat"  (returns singular for count=1)
Str::singular('cities')   // "city"
Str::singular('people')   // "person"
```

---

## `Arr` — array utilities

```php
use Core\Arr;
```

### Dot-notation access

```php
$data = ['user' => ['name' => 'Alice', 'age' => 30]];

Arr::get($data, 'user.name')           // "Alice"
Arr::get($data, 'user.missing', 'N/A') // "N/A"

Arr::set($data, 'user.email', 'alice@example.com');
// $data['user']['email'] === 'alice@example.com'

Arr::has($data, 'user.name')  // true
Arr::has($data, 'user.phone') // false

Arr::forget($data, 'user.age');
Arr::pull($data, 'user.name') // returns "Alice" and removes the key
Arr::add($data, 'user.role', 'admin')  // only sets if key is absent
```

### Dot / undot conversion

```php
Arr::dot(['a' => ['b' => 1, 'c' => 2]])
// ['a.b' => 1, 'a.c' => 2]

Arr::undot(['a.b' => 1, 'a.c' => 2])
// ['a' => ['b' => 1, 'c' => 2]]
```

### Wrapping / collapsing

```php
Arr::wrap('hello')   // ['hello']
Arr::wrap(null)      // []
Arr::wrap([1, 2])    // [1, 2]

Arr::collapse([[1, 2], [3, 4]])  // [1, 2, 3, 4]
Arr::prepend([2, 3], 1)         // [1, 2, 3]
```

### Extraction

```php
Arr::only(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'c'])
// ['a' => 1, 'c' => 3]

Arr::except(['a' => 1, 'b' => 2, 'c' => 3], ['b'])
// ['a' => 1, 'c' => 3]

$users = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
Arr::pluck($users, 'name')          // ['Alice', 'Bob']
Arr::pluck($users, 'name', 'id')    // [1 => 'Alice', 2 => 'Bob']

Arr::first([1, 2, 3], fn($v) => $v > 1)  // 2
Arr::last([1, 2, 3])                      // 3
```

### Transformation

```php
Arr::map(['a' => 1, 'b' => 2], fn($v) => $v * 2)
// ['a' => 2, 'b' => 4]

Arr::flatten([1, [2, [3, 4]]])     // [1, 2, 3, 4]
Arr::flatten([1, [2, [3]]], 1)     // [1, 2, [3]]

Arr::keyBy($users, 'id')           // [1 => [...], 2 => [...]]
Arr::groupBy($items, 'type')       // ['a' => [...], 'b' => [...]]
```

### Filtering / sorting

```php
Arr::filter([0, 1, '', 'a', false]) // [1, 'a']
Arr::where([1, 2, 3, 4], fn($v) => $v > 2) // [3, 4]
Arr::whereNotNull([1, null, 2])    // [1, 2]
Arr::reject([1, 2, 3], fn($v) => $v > 2)   // [1, 2]

Arr::sort([3, 1, 2])       // [1, 2, 3]
Arr::sortDesc([1, 3, 2])   // [3, 2, 1]
```

### Aggregation

```php
Arr::sum([1, 2, 3])        // 6
Arr::avg([2, 4, 6])        // 4.0
Arr::min([3, 1, 4])        // 1
Arr::max([3, 1, 4])        // 4
Arr::count([1, 2, 3])      // 3

Arr::some([1, 2, 3], fn($v) => $v > 2)   // true
Arr::every([2, 4, 6], fn($v) => $v % 2 === 0) // true
Arr::none([1, 2, 3], fn($v) => $v > 5)   // true

Arr::countBy(['a', 'b', 'a'])  // ['a' => 2, 'b' => 1]
Arr::join(['a', 'b', 'c'], ', ', ' and ')  // "a, b and c"
```

### Slicing / chunking

```php
Arr::chunk([1, 2, 3, 4, 5], 2)   // [[1,2],[3,4],[5]]
Arr::take([1, 2, 3, 4], 2)       // [1, 2]
Arr::take([1, 2, 3, 4], -2)      // [3, 4]
Arr::skip([1, 2, 3, 4], 2)       // [3, 4]
Arr::nth([1, 2, 3, 4, 5, 6], 2)  // [1, 3, 5]
```

### Set operations

```php
Arr::unique([1, 2, 2, 3])          // [1, 2, 3]
Arr::values(['a' => 1, 'b' => 2])  // [1, 2]
Arr::keys(['a' => 1, 'b' => 2])    // ['a', 'b']
Arr::reverse([1, 2, 3])            // [3, 2, 1]
[$keys, $values] = Arr::divide(['a' => 1, 'b' => 2]);

Arr::crossJoin([1, 2], ['a', 'b'])
// [[1,'a'],[1,'b'],[2,'a'],[2,'b']]
```

### Fluent `arr()` proxy

Chain operations without importing the class:

```php
$emails = arr($users)
    ->where(fn($u) => $u['active'])
    ->pluck('email')
    ->values()
    ->all();
```
