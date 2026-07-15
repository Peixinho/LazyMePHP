<?php

declare(strict_types=1);

use Core\Broadcasting\Broadcast;
use Core\Broadcasting\BroadcastChannel;
use Core\LazyMePHP;
use Core\Model;

// ---------------------------------------------------------------------------
// Setup — fresh SQLite :memory: per test
// ---------------------------------------------------------------------------

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function broadcastTableExists(): bool
{
    $db     = LazyMePHP::DB_CONNECTION();
    $result = $db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='__broadcast_messages'"
    );
    return (bool)$result->fetchArray();
}

/** @return array<int,array<string,mixed>> */
function broadcastRows(string $channel): array
{
    $db     = LazyMePHP::DB_CONNECTION();
    $result = $db->query(
        'SELECT * FROM __broadcast_messages WHERE channel = ? ORDER BY id ASC',
        [$channel]
    );
    $rows = [];
    while ($row = $result->fetchArray()) {
        $rows[] = $row;
    }
    return $rows;
}

// ---------------------------------------------------------------------------
// send() — auto-creates table
// ---------------------------------------------------------------------------

test('send() creates __broadcast_messages table on first call', function () {
    Broadcast::channel('test')->send('ping', []);
    expect(broadcastTableExists())->toBeTrue();
});

test('send() inserts a row with correct channel and event', function () {
    Broadcast::channel('orders')->send('order.created', ['id' => 42]);

    $rows = broadcastRows('orders');
    expect($rows)->toHaveCount(1);
    expect($rows[0]['event'])->toBe('order.created');
    expect($rows[0]['channel'])->toBe('orders');
});

test('send() JSON-encodes array data', function () {
    Broadcast::channel('chat')->send('message', ['text' => 'Hello']);

    $rows = broadcastRows('chat');
    expect($rows)->toHaveCount(1);
    $decoded = json_decode($rows[0]['data'], true);
    expect($decoded['text'])->toBe('Hello');
});

test('send() stores scalar data as JSON', function () {
    Broadcast::channel('alerts')->send('count', 5);

    $rows = broadcastRows('alerts');
    expect(json_decode($rows[0]['data']))->toBe(5);
});

test('send() records created_at timestamp', function () {
    Broadcast::channel('test')->send('ping', null);

    $rows = broadcastRows('test');
    expect($rows[0]['created_at'])->toBeString();
    expect(strlen($rows[0]['created_at']))->toBeGreaterThan(10);
});

// ---------------------------------------------------------------------------
// Multiple messages
// ---------------------------------------------------------------------------

test('multiple send() calls produce multiple rows', function () {
    $ch = Broadcast::channel('news');
    $ch->send('article.published', ['id' => 1]);
    $ch->send('article.published', ['id' => 2]);
    $ch->send('article.deleted', ['id' => 3]);

    expect(broadcastRows('news'))->toHaveCount(3);
});

test('rows for different channels are isolated', function () {
    Broadcast::channel('a')->send('ev', []);
    Broadcast::channel('b')->send('ev', []);
    Broadcast::channel('b')->send('ev', []);

    expect(broadcastRows('a'))->toHaveCount(1);
    expect(broadcastRows('b'))->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// Broadcast facade helpers
// ---------------------------------------------------------------------------

test('Broadcast::toAll() publishes to global channel', function () {
    Broadcast::toAll()->send('system.notice', ['msg' => 'maintenance']);

    $rows = broadcastRows('global');
    expect($rows)->toHaveCount(1);
});

test('Broadcast::toUser() publishes to user-scoped channel', function () {
    Broadcast::toUser(99)->send('notification', ['text' => 'Hi']);

    $rows = broadcastRows('user:99');
    expect($rows)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Each BroadcastChannel instance creates its own ensured state
// ---------------------------------------------------------------------------

test('new channel instance re-creates table when DB is fresh', function () {
    // First channel call creates the table
    Broadcast::channel('ch1')->send('e', []);
    expect(broadcastRows('ch1'))->toHaveCount(1);

    // Reset DB (simulate new request with new in-memory DB)
    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    // A new channel instance should auto-recreate the table
    Broadcast::channel('ch2')->send('e', []);
    expect(broadcastTableExists())->toBeTrue();
    expect(broadcastRows('ch2'))->toHaveCount(1);
});
