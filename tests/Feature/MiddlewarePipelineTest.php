<?php

declare(strict_types=1);

use Core\Http\Request;
use Core\Http\Middleware\Pipeline;
use Core\Http\Middleware\Middleware;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

class AddHeaderMiddleware implements Middleware
{
    public function __construct(private string $key, private string $value) {}

    public function handle(Request $request, callable $next): mixed
    {
        $response = $next($request);
        if (is_array($response)) {
            $response['headers'][$this->key] = $this->value;
        }
        return $response;
    }
}

class ShortCircuitMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        return ['short' => true];
    }
}

class AppendMiddleware implements Middleware
{
    public function __construct(private string $name) {}

    public function handle(Request $request, callable $next): mixed
    {
        $result = $next($request);
        $result['log'][] = $this->name;
        return $result;
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test('pipeline passes request through to destination', function () {
    $req    = Request::create('/test', 'GET');
    $result = Pipeline::send($req)
        ->through([])
        ->then(fn($r) => ['path' => $r->path()]);

    expect($result['path'])->toBe('/test');
});

test('pipeline runs middleware in order', function () {
    $req = Request::create('/', 'GET');
    $result = Pipeline::send($req)
        ->through([
            new AppendMiddleware('first'),
            new AppendMiddleware('second'),
        ])
        ->then(fn($r) => ['log' => []]);

    // Middleware runs outer-first; first runs last (wraps innermost)
    expect($result['log'])->toBe(['second', 'first']);
});

test('middleware can short-circuit and skip destination', function () {
    $req    = Request::create('/', 'GET');
    $called = false;
    $result = Pipeline::send($req)
        ->through([new ShortCircuitMiddleware()])
        ->then(function ($r) use (&$called) {
            $called = true;
            return ['reached' => true];
        });

    expect($called)->toBeFalse();
    expect($result)->toBe(['short' => true]);
});

test('middleware class names are instantiated automatically', function () {
    $req = Request::create('/', 'GET');
    $result = Pipeline::send($req)
        ->through([ShortCircuitMiddleware::class])
        ->then(fn($r) => ['reached' => true]);

    expect($result)->toBe(['short' => true]);
});

test('multiple middleware stack in correct order', function () {
    $req = Request::create('/', 'GET');
    $result = Pipeline::send($req)
        ->through([
            new AppendMiddleware('a'),
            new AppendMiddleware('b'),
            new AppendMiddleware('c'),
        ])
        ->then(fn($r) => ['log' => []]);

    // Executed from inside out: c, b, a
    expect($result['log'])->toBe(['c', 'b', 'a']);
});

test('request object is passed unmodified through pipeline', function () {
    $req = Request::create('/hello', 'POST', ['key' => 'val']);
    $captured = null;
    Pipeline::send($req)
        ->through([])
        ->then(function ($r) use (&$captured) {
            $captured = $r;
            return null;
        });

    expect($captured)->toBe($req);
    expect($captured->path())->toBe('/hello');
});
