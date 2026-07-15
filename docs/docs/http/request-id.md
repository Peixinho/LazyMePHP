---
id: request-id
title: Request ID Tracing
sidebar_position: 4
---

# Request ID Tracing

Every response includes an `X-Request-ID` header. This lets you correlate a client-side error with a server-side log entry.

## Behaviour

- If the incoming request already has a valid `X-Request-ID` header (alphanumeric + hyphens, max 36 chars), that value is echoed back.
- Otherwise a new UUID-shaped value is generated for the request.

## Accessing the current ID

```php
use Core\Http\RequestId;

$id = RequestId::current();
// e.g. '550e8400-e29b-41d4-a716-446655440000'
```

Use this in log entries, error responses, or audit records to tie every event to a single request:

```php
error_log('[' . RequestId::current() . '] Payment failed for order ' . $orderId);
```

## Client-side usage

Pass `X-Request-ID` from the client to correlate the full round-trip:

```http
POST /api/orders
X-Request-ID: my-client-trace-id-abc123
```

The same value comes back on the response:

```http
HTTP/1.1 201 Created
X-Request-ID: my-client-trace-id-abc123
```
