<?php

declare(strict_types=1);

use Core\Http\Http;
use Core\Http\HttpResponse;
use Core\Http\HttpException;

beforeEach(function () {
    Http::resetFake();
});

afterEach(function () {
    Http::resetFake();
});

describe('Http::fake()', function () {
    it('returns a stub response for an exact URL match', function () {
        Http::fake([
            'https://api.example.com/users' => Http::response(['id' => 1, 'name' => 'Alice'], 200),
        ]);

        $response = Http::get('https://api.example.com/users');

        expect($response->status())->toBe(200);
        expect($response->json())->toMatchArray(['id' => 1, 'name' => 'Alice']);
    });

    it('returns a stub response for a wildcard URL pattern', function () {
        Http::fake([
            'https://api.example.com/users/*' => Http::response(['error' => 'not found'], 404),
        ]);

        $response = Http::get('https://api.example.com/users/99');

        expect($response->status())->toBe(404);
    });

    it('returns default 200 {} for unmatched URLs', function () {
        Http::fake();

        $response = Http::get('https://unregistered.example.com/anything');

        expect($response->status())->toBe(200);
        expect($response->ok())->toBeTrue();
    });

    it('records the request URL and method', function () {
        Http::fake();

        Http::get('https://api.example.com/ping');

        Http::assertSent(fn (array $req) => $req['url'] === 'https://api.example.com/ping'
                                         && $req['method'] === 'GET');
        expect(true)->toBeTrue();
    });

    it('records POST requests', function () {
        Http::fake();

        Http::post('https://api.example.com/users', ['name' => 'Bob']);

        Http::assertSent(fn (array $req) => $req['method'] === 'POST');
        expect(true)->toBeTrue();
    });

    it('assertNothingSent passes when no requests were made', function () {
        Http::fake();

        Http::assertNothingSent();
        expect(true)->toBeTrue();
    });

    it('assertNothingSent fails when a request was made', function () {
        Http::fake();

        Http::get('https://api.example.com/ping');

        expect(fn () => Http::assertNothingSent())
            ->toThrow(\RuntimeException::class);
    });

    it('assertSentCount matches exact count', function () {
        Http::fake();

        Http::get('https://api.example.com/a');
        Http::get('https://api.example.com/b');
        Http::get('https://api.example.com/c');

        Http::assertSentCount(3);
        expect(true)->toBeTrue();
    });

    it('assertSentCount fails on wrong count', function () {
        Http::fake();

        Http::get('https://api.example.com/a');

        expect(fn () => Http::assertSentCount(5))
            ->toThrow(\RuntimeException::class);
    });

    it('assertSent fails when no matching request exists', function () {
        Http::fake();

        Http::get('https://api.example.com/ping');

        expect(fn () => Http::assertSent(fn ($r) => $r['url'] === 'https://totally-different.com'))
            ->toThrow(\RuntimeException::class);
    });

    it('recorded() returns all recorded requests', function () {
        Http::fake();

        Http::get('https://api.example.com/a');
        Http::post('https://api.example.com/b', []);

        expect(Http::recorded())->toHaveCount(2);
    });

    it('Http::response() builds a valid HttpResponse', function () {
        $r = Http::response(['ok' => true], 201);

        expect($r)->toBeInstanceOf(HttpResponse::class);
        expect($r->status())->toBe(201);
        expect($r->json())->toMatchArray(['ok' => true]);
    });

    it('fake mode does not make real HTTP calls', function () {
        // If a real call to a non-existent host were made this test would hang/error.
        Http::fake([
            'https://no-such-host.test/*' => Http::response(['ok' => true], 200),
        ]);

        $response = Http::get('https://no-such-host.test/api/data');

        expect($response->ok())->toBeTrue();
    });

    it('resetFake clears recorded requests and stubs', function () {
        Http::fake([
            'https://api.example.com/users' => Http::response(['id' => 1], 200),
        ]);
        Http::get('https://api.example.com/users');

        Http::resetFake();
        Http::fake();

        Http::assertNothingSent();
        expect(true)->toBeTrue();
    });
});
