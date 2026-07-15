<?php

declare(strict_types=1);

use Core\Container\Container;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

class ContainerTestService
{
    public string $value = 'default';
}

class ContainerTestServiceWithDep
{
    public function __construct(public ContainerTestService $service) {}
}

interface ContainerTestInterface {}

class ContainerTestImpl implements ContainerTestInterface
{
    public string $tag = 'impl';
}

// ---------------------------------------------------------------------------
// Binding
// ---------------------------------------------------------------------------

test('bind resolves a closure', function () {
    $c = new Container();
    $c->bind('foo', fn() => new ContainerTestService());
    expect($c->make('foo'))->toBeInstanceOf(ContainerTestService::class);
});

test('bind creates new instance each time', function () {
    $c = new Container();
    $c->bind('foo', fn() => new ContainerTestService());
    $a = $c->make('foo');
    $b = $c->make('foo');
    expect($a)->not->toBe($b);
});

test('bind with class string', function () {
    $c = new Container();
    $c->bind(ContainerTestInterface::class, ContainerTestImpl::class);
    expect($c->make(ContainerTestInterface::class))->toBeInstanceOf(ContainerTestImpl::class);
});

// ---------------------------------------------------------------------------
// Singleton
// ---------------------------------------------------------------------------

test('singleton returns same instance', function () {
    $c = new Container();
    $c->singleton('db', fn() => new ContainerTestService());
    $a = $c->make('db');
    $b = $c->make('db');
    expect($a)->toBe($b);
});

// ---------------------------------------------------------------------------
// Instance
// ---------------------------------------------------------------------------

test('instance stores pre-built object', function () {
    $c   = new Container();
    $svc = new ContainerTestService();
    $c->instance('svc', $svc);
    expect($c->make('svc'))->toBe($svc);
});

// ---------------------------------------------------------------------------
// Has
// ---------------------------------------------------------------------------

test('has returns true for bound abstract', function () {
    $c = new Container();
    $c->bind('something', fn() => 'x');
    expect($c->has('something'))->toBeTrue();
    expect($c->has('other'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Auto-wiring
// ---------------------------------------------------------------------------

test('make auto-wires constructor dependencies', function () {
    $c = new Container();
    $result = $c->make(ContainerTestServiceWithDep::class);
    expect($result)->toBeInstanceOf(ContainerTestServiceWithDep::class);
    expect($result->service)->toBeInstanceOf(ContainerTestService::class);
});

test('make with param overrides', function () {
    $c   = new Container();
    $svc = new ContainerTestService();
    $result = $c->make(ContainerTestServiceWithDep::class, ['service' => $svc]);
    expect($result->service)->toBe($svc);
});

// ---------------------------------------------------------------------------
// Call
// ---------------------------------------------------------------------------

test('call invokes closure with injected args', function () {
    $c = new Container();
    $result = $c->call(fn(ContainerTestService $s) => $s->value);
    expect($result)->toBe('default');
});

test('call invokes closure with override params', function () {
    $c = new Container();
    $result = $c->call(fn(string $name) => "hello {$name}", ['name' => 'world']);
    expect($result)->toBe('hello world');
});

test('call invokes array callable', function () {
    $c = new Container();

    $obj = new class {
        public function greet(ContainerTestService $s): string
        {
            return 'greeting:' . $s->value;
        }
    };

    $result = $c->call([$obj, 'greet']);
    expect($result)->toBe('greeting:default');
});

// ---------------------------------------------------------------------------
// Edge cases
// ---------------------------------------------------------------------------

test('make concrete class without binding', function () {
    $c = new Container();
    expect($c->make(ContainerTestService::class))->toBeInstanceOf(ContainerTestService::class);
});

test('make unknown binding throws', function () {
    $c = new Container();
    expect(fn() => $c->make('NonExistentClass'))->toThrow(\RuntimeException::class);
});
