---
id: health
title: Health Check
sidebar_position: 3
---

# Health Check

```
GET /health
```

Returns `200 OK` when the database is reachable, `503 Service Unavailable` otherwise.

## Response

```json
{
    "status": "ok",
    "db": {
        "status": "ok",
        "type": "sqlite"
    },
    "php": "8.3.0",
    "memory": {
        "used": "4.2 MB",
        "peak": "5.1 MB",
        "limit": "128M"
    }
}
```

When the database is unreachable:

```json
{
    "status": "error",
    "db": {
        "status": "error",
        "message": "Connection refused"
    }
}
```

## Using with load balancers

Configure your load balancer or container orchestrator to probe `/health`. The endpoint responds without touching application logic — it only checks the database connection.

Kubernetes example:

```yaml
livenessProbe:
  httpGet:
    path: /health
    port: 8080
  initialDelaySeconds: 5
  periodSeconds: 10
```
