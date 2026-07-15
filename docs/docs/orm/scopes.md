---
id: scopes
title: Scopes
sidebar_position: 9
---

# Scopes

## Global scopes

Apply automatic query constraints to every query on a model — useful for tenant isolation, soft-delete, or status filtering.

```php
class ActiveUser extends Model {
    protected static string $table = 'users';
    protected static array $globalScopes = [];
}

// Register once (e.g. in a service provider or boot file):
ActiveUser::addGlobalScope('active', fn($q) => $q->where('active', 1));

// Every query now silently adds WHERE active = 1:
ActiveUser::query()->get();

// Bypass all scopes for this query:
ActiveUser::query()->withoutGlobalScopes()->get();

// Remove a scope permanently:
ActiveUser::removeGlobalScope('active');
```

Global scopes are applied to `get()`, `count()`, `first()`, `paginate()`, `update()`, and `bulkDelete()` — so scoped models can't accidentally leak data through bulk operations.

## Local scopes

Define reusable query constraints as methods on the model class.

```php
class Product extends Model {
    protected static string $table = 'products';

    public function scopeActive(ModelQuery $q): void {
        $q->where('active', 1);
    }

    public function scopeInStock(ModelQuery $q): void {
        $q->where('stock', 0, '>');
    }

    public function scopePricedBelow(ModelQuery $q, float $max): void {
        $q->where('price', $max, '<');
    }
}

// Fluent chaining:
Product::query()
    ->active()
    ->inStock()
    ->pricedBelow(50)
    ->orderBy('price')
    ->get();

// Or via scope():
Product::query()
    ->scope('active')
    ->scope('pricedBelow', 50)
    ->get();
```

Scope methods are prefixed with `scope` in the class but called without it on the query.
