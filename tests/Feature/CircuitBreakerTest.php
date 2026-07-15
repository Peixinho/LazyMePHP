<?php

declare(strict_types=1);

use Core\Http\CircuitBreaker;
use Core\Http\CircuitOpenException;

afterEach(fn() => CircuitBreaker::resetAll());

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

test('successful call returns result and stays CLOSED', function () {
    $cb = CircuitBreaker::for('svc-ok')->threshold(3)->timeout(60);
    $result = $cb->call(fn() => 'hello');
    expect($result)->toBe('hello');
    expect($cb->isClosed())->toBeTrue();
    expect($cb->getFailures())->toBe(0);
});

// ---------------------------------------------------------------------------
// CLOSED → OPEN transition
// ---------------------------------------------------------------------------

test('circuit opens after threshold failures', function () {
    $cb = CircuitBreaker::for('svc-fail')->threshold(3)->timeout(60);

    for ($i = 0; $i < 3; $i++) {
        try {
            $cb->call(fn() => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
        }
    }

    expect($cb->isOpen())->toBeTrue();
});

test('open circuit rejects calls with CircuitOpenException', function () {
    $cb = CircuitBreaker::for('svc-open')->threshold(1)->timeout(60);

    try {
        $cb->call(fn() => throw new \RuntimeException('fail'));
    } catch (\RuntimeException) {
    }

    expect($cb->isOpen())->toBeTrue();
    expect(fn() => $cb->call(fn() => 'x'))->toThrow(CircuitOpenException::class);
});

// ---------------------------------------------------------------------------
// onOpen callback
// ---------------------------------------------------------------------------

test('onOpen callback fires when circuit opens', function () {
    $fired   = false;
    $service = '';

    $cb = CircuitBreaker::for('svc-cb')
        ->threshold(2)
        ->timeout(60)
        ->onOpen(function (string $svc, int $failures) use (&$fired, &$service) {
            $fired   = true;
            $service = $svc;
        });

    for ($i = 0; $i < 2; $i++) {
        try {
            $cb->call(fn() => throw new \RuntimeException('x'));
        } catch (\RuntimeException) {
        }
    }

    expect($fired)->toBeTrue();
    expect($service)->toBe('svc-cb');
});

// ---------------------------------------------------------------------------
// OPEN → HALF_OPEN → CLOSED
// ---------------------------------------------------------------------------

test('circuit transitions to HALF_OPEN after timeout', function () {
    $cb = CircuitBreaker::for('svc-half')->threshold(1)->timeout(1);

    try {
        $cb->call(fn() => throw new \RuntimeException('x'));
    } catch (\RuntimeException) {
    }

    expect($cb->isOpen())->toBeTrue();

    // Simulate timeout elapsed by sleep(1) — use a small delay
    usleep(1_100_000); // 1.1 seconds

    // Next call should attempt (HALF_OPEN); success closes it
    $cb->call(fn() => null);
    expect($cb->isClosed())->toBeTrue();
});

test('failure in HALF_OPEN re-opens circuit', function () {
    $cb = CircuitBreaker::for('svc-reopen')->threshold(1)->timeout(1);

    try {
        $cb->call(fn() => throw new \RuntimeException('x'));
    } catch (\RuntimeException) {
    }

    usleep(1_100_000);

    try {
        $cb->call(fn() => throw new \RuntimeException('y'));
    } catch (\RuntimeException) {
    }

    expect($cb->isOpen())->toBeTrue();
});

// ---------------------------------------------------------------------------
// singleton per service
// ---------------------------------------------------------------------------

test('for() returns same instance for same service name', function () {
    $a = CircuitBreaker::for('shared');
    $b = CircuitBreaker::for('shared');
    expect($a)->toBe($b);
});

test('for() returns different instance for different service names', function () {
    $a = CircuitBreaker::for('alpha');
    $b = CircuitBreaker::for('beta');
    expect($a)->not->toBe($b);
});

// ---------------------------------------------------------------------------
// reset
// ---------------------------------------------------------------------------

test('reset() clears the circuit breaker instance', function () {
    $cb = CircuitBreaker::for('resettable')->threshold(1)->timeout(60);
    try {
        $cb->call(fn() => throw new \RuntimeException('x'));
    } catch (\RuntimeException) {
    }
    expect($cb->isOpen())->toBeTrue();

    CircuitBreaker::reset('resettable');

    $fresh = CircuitBreaker::for('resettable');
    expect($fresh->isClosed())->toBeTrue();
    expect($fresh)->not->toBe($cb);
});
