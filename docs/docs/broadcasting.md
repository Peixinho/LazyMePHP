---
sidebar_position: 17
---

# Broadcasting (SSE)

LazyMePHP supports real-time event broadcasting via **Server-Sent Events (SSE)**. Messages are stored in a `__broadcast_messages` database table and streamed to connected clients.

## Publishing events

```php
use Core\Broadcasting\Broadcast;

// Named channel
Broadcast::channel('orders')->send('order.created', ['id' => 42, 'total' => 99.90]);

// All users
Broadcast::toAll()->send('system.notice', ['message' => 'Maintenance at 3am']);

// Specific user
Broadcast::toUser($user->id)->send('notification', ['text' => 'You have a new message']);
```

## Listening (SSE endpoint)

Create a dedicated route for each channel:

```php
// App/Routes/Routes.php
Router::get('/events/orders', function () {
    Broadcast::channel('orders')->listen();
});
```

The endpoint streams `text/event-stream` responses and keeps the connection alive with periodic heartbeats.

## Authentication

### Static token (env-based)

```ini
# .env
BROADCAST_TOKEN=your-secret-token
```

Clients must send `Authorization: Bearer your-secret-token`. Unauthorized connections receive 401.

### Custom auth callable

```php
Router::get('/events/orders', function () {
    Broadcast::channel('orders')->listen(
        auth: function (?string $token): bool {
            return Auth::validateToken($token);
        }
    );
});
```

## Rate limiting

Limit concurrent SSE connections per IP channel (requires APCu):

```ini
# .env
BROADCAST_MAX_CONNECTIONS=10   # max concurrent connections per IP per channel
BROADCAST_RATE_WINDOW=60       # window in seconds
```

Excess connections receive 429 with a `Retry-After` header.

## JavaScript client

```javascript
const es = new EventSource('/events/orders', {
    headers: { Authorization: 'Bearer your-secret-token' }
});

es.addEventListener('order.created', (e) => {
    const order = JSON.parse(e.data);
    console.log('New order:', order);
});

es.addEventListener('error', () => {
    console.error('SSE connection lost — will retry automatically');
});
```

## Configuration

| Parameter | Default | Description |
|---|---|---|
| `pollIntervalMs` | `1000` | How often to poll the DB (milliseconds) |
| `maxSeconds` | `0` (unlimited) | Auto-close connection after N seconds |
| `auth` | `null` | Custom auth callable |

```php
Broadcast::channel('chat')->listen(
    pollIntervalMs: 500,
    maxSeconds: 3600,
);
```

## How it works

1. `send()` inserts a row into `__broadcast_messages` (auto-created on first use)
2. `listen()` polls for rows with `id > lastSeenId` on each tick
3. Messages older than 5 minutes are pruned automatically
4. The `id:` field in SSE lets clients resume from where they left off after reconnects
