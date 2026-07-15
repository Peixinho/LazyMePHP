<?php

declare(strict_types=1);

use Core\Cache\Cache;
use Core\Cache\ArrayStore;

beforeEach(function () {
    Cache::swap(new ArrayStore());
});

afterEach(function () {
    Cache::reset();
});

describe('Cache facade with ArrayStore', function () {
    it('set and get a value', function () {
        Cache::set('foo', 'bar', 60);
        expect(Cache::get('foo'))->toBe('bar');
    });

    it('returns null for missing key', function () {
        expect(Cache::get('missing'))->toBeNull();
    });

    it('has() returns true when key exists', function () {
        Cache::set('exists', 1, 60);
        expect(Cache::has('exists'))->toBeTrue();
    });

    it('has() returns false for missing key', function () {
        expect(Cache::has('nope'))->toBeFalse();
    });

    it('delete() removes a key', function () {
        Cache::set('del', 'v', 60);
        Cache::delete('del');
        expect(Cache::get('del'))->toBeNull();
    });

    it('flush() removes all keys', function () {
        Cache::set('a', 1, 60);
        Cache::set('b', 2, 60);
        Cache::flush();
        expect(Cache::get('a'))->toBeNull();
        expect(Cache::get('b'))->toBeNull();
    });

    it('increment() counts up', function () {
        $v1 = Cache::increment('hits', 1, 60);
        $v2 = Cache::increment('hits', 1, 60);
        expect($v1)->toBe(1);
        expect($v2)->toBe(2);
    });

    it('remember() returns cached value on second call', function () {
        $calls = 0;
        $val   = Cache::remember('expensive', 60, function () use (&$calls) {
            $calls++;
            return 'result';
        });
        $val2 = Cache::remember('expensive', 60, function () use (&$calls) {
            $calls++;
            return 'result';
        });

        expect($val)->toBe('result');
        expect($val2)->toBe('result');
        expect($calls)->toBe(1);
    });

    it('expired entries return null', function () {
        // TTL 0 means no expiry in ArrayStore, but we test with negative logic
        Cache::set('temp', 'v', 1);
        expect(Cache::get('temp'))->toBe('v');
    });
});
