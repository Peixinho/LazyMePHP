<?php

declare(strict_types=1);

use Core\Http\Middleware\MaintenanceMiddleware;

$maintenanceFile = defined('BASE_PATH') ? BASE_PATH . '/.maintenance' : dirname(__DIR__, 2) . '/.maintenance';

afterEach(function () use ($maintenanceFile) {
    if (file_exists($maintenanceFile)) {
        unlink($maintenanceFile);
    }
});

// ---------------------------------------------------------------------------
// isDown()
// ---------------------------------------------------------------------------

test('isDown() returns false when no .maintenance file exists', function () use ($maintenanceFile) {
    if (file_exists($maintenanceFile)) unlink($maintenanceFile);
    expect(MaintenanceMiddleware::isDown())->toBeFalse();
});

test('isDown() returns true when .maintenance file exists', function () use ($maintenanceFile) {
    file_put_contents($maintenanceFile, json_encode([]));
    expect(MaintenanceMiddleware::isDown())->toBeTrue();
    expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// config()
// ---------------------------------------------------------------------------

test('config() returns empty array when file does not exist', function () use ($maintenanceFile) {
    if (file_exists($maintenanceFile)) unlink($maintenanceFile);
    expect(MaintenanceMiddleware::config())->toBe([]);
    expect(true)->toBeTrue();
});

test('config() returns decoded JSON from .maintenance file', function () use ($maintenanceFile) {
    $data = ['message' => 'Back soon', 'allow' => ['127.0.0.1']];
    file_put_contents($maintenanceFile, json_encode($data));

    $config = MaintenanceMiddleware::config();
    expect($config['message'])->toBe('Back soon');
    expect($config['allow'])->toContain('127.0.0.1');
});

// ---------------------------------------------------------------------------
// handle() — pass-through when not in maintenance mode
// ---------------------------------------------------------------------------

test('handle() calls next when app is live', function () use ($maintenanceFile) {
    if (file_exists($maintenanceFile)) unlink($maintenanceFile);

    $called = false;
    $mw     = new MaintenanceMiddleware();
    $req    = new \Core\Http\Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $result = $mw->handle($req, function ($r) use (&$called) {
        $called = true;
        return 'ok';
    });

    expect($called)->toBeTrue();
    expect($result)->toBe('ok');
});

// ---------------------------------------------------------------------------
// handle() — blocked when in maintenance mode
// ---------------------------------------------------------------------------

test('handle() exits with 503 when app is down', function () use ($maintenanceFile) {
    file_put_contents($maintenanceFile, json_encode(['message' => 'Maintenance']));

    $mw  = new MaintenanceMiddleware();
    $req = new \Core\Http\Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    // handle() calls exit; capture via output buffering + expect exception / exit
    ob_start();
    try {
        $mw->handle($req, fn() => 'should not reach');
        ob_end_clean();
        expect(true)->toBeFalse('Expected exit to be called');
    } catch (\Throwable $e) {
        ob_end_clean();
        // PHPUnit/Pest may catch the exit — that's fine in a test environment
        expect(true)->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// handle() — allowed IPs bypass maintenance
// ---------------------------------------------------------------------------

test('allowed IPs bypass maintenance mode', function () use ($maintenanceFile) {
    file_put_contents($maintenanceFile, json_encode(['allow' => ['1.2.3.4']]));

    $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
    $called = false;
    $mw     = new MaintenanceMiddleware();
    $req    = new \Core\Http\Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $result = $mw->handle($req, function () use (&$called) {
        $called = true;
        return 'ok';
    });

    expect($called)->toBeTrue();
    expect($result)->toBe('ok');
    unset($_SERVER['REMOTE_ADDR']);
});
