<?php

declare(strict_types=1);

use Core\Str;

// ---------------------------------------------------------------------------
// Case conversion
// ---------------------------------------------------------------------------

test('camel converts snake_case', function () {
    expect(Str::camel('hello_world'))->toBe('helloWorld');
    expect(Str::camel('foo_bar_baz'))->toBe('fooBarBaz');
});

test('studly converts snake_case', function () {
    expect(Str::studly('hello_world'))->toBe('HelloWorld');
    expect(Str::studly('foo-bar'))->toBe('FooBar');
});

test('snake converts camelCase', function () {
    expect(Str::snake('helloWorld'))->toBe('hello_world');
    expect(Str::snake('FooBarBaz'))->toBe('foo_bar_baz');
});

test('kebab converts camelCase to kebab-case', function () {
    expect(Str::kebab('helloWorld'))->toBe('hello-world');
});

test('title capitalises each word', function () {
    expect(Str::title('hello world'))->toBe('Hello World');
});

test('upper and lower', function () {
    expect(Str::upper('hello'))->toBe('HELLO');
    expect(Str::lower('HELLO'))->toBe('hello');
});

test('headline converts underscored/camel to spaced title', function () {
    expect(Str::headline('hello_world'))->toBe('Hello World');
    expect(Str::headline('fooBarBaz'))->toBe('FooBarBaz');
});

// ---------------------------------------------------------------------------
// Slug
// ---------------------------------------------------------------------------

test('slug converts string to URL-friendly slug', function () {
    expect(Str::slug('Hello World'))->toBe('hello-world');
    expect(Str::slug('Hello World', '_'))->toBe('hello_world');
    expect(Str::slug('foo  bar--baz'))->toBe('foo-bar-baz');
});

// ---------------------------------------------------------------------------
// Length / substring
// ---------------------------------------------------------------------------

test('length returns character count', function () {
    expect(Str::length('hello'))->toBe(5);
    expect(Str::length(''))->toBe(0);
});

test('substr returns a portion', function () {
    expect(Str::substr('hello world', 6))->toBe('world');
    expect(Str::substr('hello world', 0, 5))->toBe('hello');
});

test('substrCount counts occurrences', function () {
    expect(Str::substrCount('hello world hello', 'hello'))->toBe(2);
});

test('charAt returns a single character', function () {
    expect(Str::charAt('hello', 1))->toBe('e');
});

test('limit truncates string with ellipsis', function () {
    expect(Str::limit('hello world', 5))->toBe('hello...');
    expect(Str::limit('hi', 10))->toBe('hi');
});

test('words truncates at word boundary', function () {
    $result = Str::words('one two three four', 2);
    expect($result)->toBe('one two...');
});

test('take takes N characters from start or end', function () {
    expect(Str::take('hello', 3))->toBe('hel');
    expect(Str::take('hello', -2))->toBe('lo');
});

// ---------------------------------------------------------------------------
// Search / test
// ---------------------------------------------------------------------------

test('contains checks substring', function () {
    expect(Str::contains('hello world', 'world'))->toBeTrue();
    expect(Str::contains('hello world', 'xyz'))->toBeFalse();
    expect(Str::contains('hello world', ['xyz', 'hello']))->toBeTrue();
});

test('containsAll checks all needles', function () {
    expect(Str::containsAll('hello world', ['hello', 'world']))->toBeTrue();
    expect(Str::containsAll('hello world', ['hello', 'xyz']))->toBeFalse();
});

test('startsWith checks prefix', function () {
    expect(Str::startsWith('hello world', 'hello'))->toBeTrue();
    expect(Str::startsWith('hello world', 'world'))->toBeFalse();
    expect(Str::startsWith('hello', ['foo', 'hel']))->toBeTrue();
});

test('endsWith checks suffix', function () {
    expect(Str::endsWith('hello world', 'world'))->toBeTrue();
    expect(Str::endsWith('hello world', 'hello'))->toBeFalse();
});

test('is performs wildcard matching', function () {
    expect(Str::is('foo*', 'foobar'))->toBeTrue();
    expect(Str::is('foo*', 'barfoo'))->toBeFalse();
    expect(Str::is('*.php', 'index.php'))->toBeTrue();
});

test('isJson detects valid JSON', function () {
    expect(Str::isJson('{"a":1}'))->toBeTrue();
    expect(Str::isJson('not json'))->toBeFalse();
    expect(Str::isJson(''))->toBeFalse();
});

test('isUrl validates URLs', function () {
    expect(Str::isUrl('https://example.com'))->toBeTrue();
    expect(Str::isUrl('not a url'))->toBeFalse();
});

test('isEmail validates email addresses', function () {
    expect(Str::isEmail('user@example.com'))->toBeTrue();
    expect(Str::isEmail('not-an-email'))->toBeFalse();
});

test('isUuid validates UUID format', function () {
    expect(Str::isUuid('550e8400-e29b-41d4-a716-446655440000'))->toBeTrue();
    expect(Str::isUuid('not-a-uuid'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Manipulation
// ---------------------------------------------------------------------------

test('before returns substring before first occurrence', function () {
    expect(Str::before('hello world hello', 'world'))->toBe('hello ');
});

test('beforeLast returns substring before last occurrence', function () {
    expect(Str::beforeLast('hello.world.php', '.'))->toBe('hello.world');
});

test('after returns substring after first occurrence', function () {
    expect(Str::after('hello world', 'hello '))->toBe('world');
});

test('afterLast returns substring after last occurrence', function () {
    expect(Str::afterLast('hello.world.php', '.'))->toBe('php');
});

test('between extracts substring between delimiters', function () {
    expect(Str::between('[hello]', '[', ']'))->toBe('hello');
});

test('replace substitutes substrings', function () {
    expect(Str::replace('world', 'PHP', 'hello world'))->toBe('hello PHP');
});

test('replaceFirst replaces only first occurrence', function () {
    expect(Str::replaceFirst('a', 'b', 'aaa'))->toBe('baa');
});

test('replaceLast replaces only last occurrence', function () {
    expect(Str::replaceLast('a', 'b', 'aaa'))->toBe('aab');
});

test('remove strips characters', function () {
    expect(Str::remove(['a', 'b'], 'aabbcc'))->toBe('cc');
});

test('finish appends suffix if not already present', function () {
    expect(Str::finish('hello', '!'))->toBe('hello!');
    expect(Str::finish('hello!', '!'))->toBe('hello!');
});

test('start prepends prefix if not already present', function () {
    expect(Str::start('hello', '/'))->toBe('/hello');
    expect(Str::start('/hello', '/'))->toBe('/hello');
});

test('wrap surrounds string', function () {
    expect(Str::wrap('hello', '"'))->toBe('"hello"');
    expect(Str::wrap('hello', '<b>', '</b>'))->toBe('<b>hello</b>');
});

test('reverse reverses a string', function () {
    expect(Str::reverse('hello'))->toBe('olleh');
});

test('repeat repeats string N times', function () {
    expect(Str::repeat('ab', 3))->toBe('ababab');
});

test('squish collapses whitespace', function () {
    expect(Str::squish('  hello   world  '))->toBe('hello world');
});

test('mask replaces characters with mask char', function () {
    expect(Str::mask('hello', '*', 1, 3))->toBe('h***o');
});

// ---------------------------------------------------------------------------
// Padding / trimming
// ---------------------------------------------------------------------------

test('padLeft pads left', function () {
    expect(Str::padLeft('5', 3, '0'))->toBe('005');
});

test('padRight pads right', function () {
    expect(Str::padRight('hi', 5))->toBe('hi   ');
});

test('trim removes whitespace', function () {
    expect(Str::trim('  hello  '))->toBe('hello');
    expect(Str::trim('--hello--', '-'))->toBe('hello');
});

// ---------------------------------------------------------------------------
// Random / UUID
// ---------------------------------------------------------------------------

test('random generates a string of the requested length', function () {
    $r = Str::random(16);
    expect(strlen($r))->toBe(16);
    expect(Str::random(8))->not->toBe(Str::random(8));
});

test('uuid generates a valid v4 UUID', function () {
    $uuid = Str::uuid();
    expect(Str::isUuid($uuid))->toBeTrue();
    expect(Str::uuid())->not->toBe(Str::uuid());
});

// ---------------------------------------------------------------------------
// Pluralisation
// ---------------------------------------------------------------------------

test('plural handles regular words', function () {
    expect(Str::plural('cat'))->toBe('cats');
    expect(Str::plural('dish'))->toBe('dishes');
    expect(Str::plural('city'))->toBe('cities');
    expect(Str::plural('leaf'))->toBe('leaves');
});

test('plural returns singular for count=1', function () {
    expect(Str::plural('cat', 1))->toBe('cat');
});

test('plural handles irregulars', function () {
    expect(Str::plural('person'))->toBe('people');
    expect(Str::plural('child'))->toBe('children');
    expect(Str::plural('mouse'))->toBe('mice');
});

test('singular reverses pluralization', function () {
    expect(Str::singular('cats'))->toBe('cat');
    expect(Str::singular('cities'))->toBe('city');
    expect(Str::singular('people'))->toBe('person');
});
