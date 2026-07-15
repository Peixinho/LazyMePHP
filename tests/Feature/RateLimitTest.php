<?php

declare(strict_types=1);

use Core\Cache\Cache;
use Core\Cache\ArrayStore;
use Core\Http\RateLimit;

beforeEach(function () {
    Cache::swap(new ArrayStore());
});

afterEach(function () {
    Cache::reset();
});

describe('RateLimit middleware', function () {
    it('increments the hit counter on each call', function () {
        $key = 'rl:test-key';
        Cache::increment($key, 1, 60);
        Cache::increment($key, 1, 60);
        expect(Cache::get($key))->toBe(2);
    });

    it('counter starts at 1 and grows', function () {
        $v1 = Cache::increment('rl:new', 1, 60);
        $v2 = Cache::increment('rl:new', 1, 60);
        $v3 = Cache::increment('rl:new', 1, 60);
        expect($v1)->toBe(1);
        expect($v2)->toBe(2);
        expect($v3)->toBe(3);
    });

    it('different keys track independently', function () {
        Cache::increment('rl:a', 1, 60);
        Cache::increment('rl:a', 1, 60);
        Cache::increment('rl:b', 1, 60);
        expect(Cache::get('rl:a'))->toBe(2);
        expect(Cache::get('rl:b'))->toBe(1);
    });

    it('RateLimit class can be instantiated', function () {
        $rl = new RateLimit(10, 60);
        expect($rl)->toBeInstanceOf(RateLimit::class);
    });
});
