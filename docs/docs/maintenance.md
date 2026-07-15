---
sidebar_position: 12
---

# Maintenance Mode

Put the application offline for deployments, migrations, or emergencies — without touching your web server config.

---

## CLI commands

```bash
# Take the app down
php LazyMePHP down

# With a custom message
php LazyMePHP down --message="We're upgrading the database. Back in 5 minutes."

# Allow specific IPs to bypass maintenance (e.g. your office IP)
php LazyMePHP down --allow=203.0.113.5 --allow=198.51.100.0

# Take the app back online
php LazyMePHP up
```

When down, all requests return **503 Service Unavailable** with a `Retry-After: 60` header and an HTML page showing the message. Requests from allowed IPs go straight through.

A `.maintenance` file is created/deleted in the project root. It is gitignored automatically.

---

## `MaintenanceMiddleware`

Register it at the top of your middleware pipeline so it runs before any route logic:

```php
use Core\Http\Middleware\MaintenanceMiddleware;
use Core\Http\Middleware\Pipeline;

$response = Pipeline::send($request)
    ->through([
        MaintenanceMiddleware::class,
        \Core\Http\Middleware\CorsMiddleware::class,
        // ...
    ])
    ->then($handler);
```

### Checking status programmatically

```php
use Core\Http\Middleware\MaintenanceMiddleware;

if (MaintenanceMiddleware::isDown()) {
    // app is in maintenance mode
}

$config = MaintenanceMiddleware::config();
// ['message' => '...', 'allow' => ['1.2.3.4']]
```

---

## `.maintenance` file format

The file is valid JSON:

```json
{
    "message": "We are performing scheduled maintenance. Back soon.",
    "allow": ["127.0.0.1", "203.0.113.5"]
}
```

You can write it manually or from a deploy script if you prefer not to use the CLI.

---

## Typical deploy workflow

```bash
php LazyMePHP down --message="Deploying v2.1 — back in 2 minutes."
git pull origin main
composer install --no-dev --optimize-autoloader
php LazyMePHP migrate
php LazyMePHP optimize
php LazyMePHP up
```
