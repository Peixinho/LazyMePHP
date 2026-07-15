---
id: events
title: Model Events
sidebar_position: 8
---

# Model Events

Hook into the model lifecycle with event listeners or observer classes. Returning `false` from a `creating`, `updating`, or `deleting` listener cancels the operation.

## Listeners

```php
use Core\Events\ModelEvents;

// Run after every create on 'orders'
ModelEvents::listen('orders', 'created', function (Model $order) {
    // send confirmation email
});

// Cancel a delete
ModelEvents::listen('orders', 'deleting', function (Model $order) {
    if ($order->status === 'completed') return false; // vetoed
});
```

## Observer class

```php
class OrderObserver {
    public function creating(Model $m): void {
        $m->created_at = date('Y-m-d H:i:s');
    }

    public function updated(Model $m): void {
        Cache::delete("order:{$m->getPrimaryKey()}");
    }

    public function deleting(Model $m): bool {
        // return false to veto
        return $m->status !== 'completed';
    }
}

ModelEvents::registerObserver('orders', new OrderObserver());
// or, on the model class:
Order::observe('orders', new OrderObserver());
```

## All events

| Event | Fired |
|---|---|
| `creating` | Before INSERT — return `false` to cancel |
| `created` | After INSERT |
| `updating` | Before UPDATE — return `false` to cancel |
| `updated` | After UPDATE |
| `saving` | Before INSERT or UPDATE — return `false` to cancel |
| `saved` | After INSERT or UPDATE |
| `deleting` | Before DELETE — return `false` to cancel |
| `deleted` | After DELETE |
