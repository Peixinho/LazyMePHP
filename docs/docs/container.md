---
sidebar_position: 15
---

# Service Container

LazyMePHP ships with a lightweight IoC container for dependency injection and auto-wiring.

## Basic binding

```php
use Core\Container\Container;

$container = new Container();

// Bind a closure
$container->bind('mailer', fn() => new SmtpMailer());

// Resolve
$mailer = $container->make('mailer');
```

## Singletons

```php
// Resolved once, then cached for the lifetime of the container
$container->singleton(CacheStore::class, fn() => new RedisCache());

$a = $container->make(CacheStore::class);
$b = $container->make(CacheStore::class);
// $a === $b  (same instance)
```

## Pre-built instances

```php
$container->instance('config', new Config());

$config = $container->make('config'); // returns the exact object you registered
```

## Interface to implementation

```php
interface MailerInterface {}
class SmtpMailer implements MailerInterface {}

$container->bind(MailerInterface::class, SmtpMailer::class);

$mailer = $container->make(MailerInterface::class); // SmtpMailer
```

## Auto-wiring

The container resolves constructor dependencies automatically via reflection:

```php
class OrderService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Cache $cache,
    ) {}
}

// Both MailerInterface and Cache must be resolvable by the container
$service = $container->make(OrderService::class);
```

## Parameter overrides

```php
$service = $container->make(OrderService::class, [
    'mailer' => new MockMailer(), // inject a specific instance
]);
```

## Calling callables with injection

```php
$result = $container->call(fn(OrderService $svc) => $svc->process($orderId));

// Array callable
$result = $container->call([$controller, 'index']);

// With overrides
$result = $container->call(fn(string $name) => "Hello {$name}", ['name' => 'world']);
```

## Checking bindings

```php
if ($container->has(MailerInterface::class)) {
    // ...
}
```
