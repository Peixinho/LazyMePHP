---
id: api-resources
title: API Resources
sidebar_position: 3
---

# API Resources

`Core\Http\ApiResource` shapes model data for API responses — hiding sensitive fields, renaming keys, and adding computed properties.

## Creating a resource

Create a class in `App/Resources/` (or anywhere on the autoload path) that extends `ApiResource` and implement `toArray()`:

```php
use Core\Http\ApiResource;

class UserResource extends ApiResource {
    public function toArray(): array {
        return [
            'id'         => $this->model->id,
            'name'       => $this->model->name,
            'email'      => $this->model->email,
            'member_since' => date('Y', strtotime($this->model->created_at)),
            // password is omitted
        ];
    }
}
```

## Single resource

```php
// Output JSON response and set Content-Type header
UserResource::make($user)->respond();

// Or get the JSON string without responding
$json = UserResource::make($user)->toJson();

// Or get the array
$data = UserResource::make($user)->toArray();
```

Response shape:

```json
{
    "data": {
        "id": 1,
        "name": "Alice",
        "email": "alice@example.com",
        "member_since": "2024"
    }
}
```

## Collections

```php
UserResource::collection($users)->respond();
```

```json
{
    "data": [
        { "id": 1, "name": "Alice", ... },
        { "id": 2, "name": "Bob", ... }
    ]
}
```

## With metadata

```php
UserResource::collection($users)
    ->withMeta(['total' => 120, 'page' => 2])
    ->respond();
```

```json
{
    "data": [...],
    "meta": { "total": 120, "page": 2 }
}
```

## Paginated responses

`fromPaginator()` accepts the array returned by `ModelQuery::paginate()` and attaches all pagination metadata automatically:

```php
$page = Model::query('users')->where('active', 1)->paginate(15, $currentPage);

UserResource::fromPaginator($page)->respond();
```

Response shape:

```json
{
    "data": [
        { "id": 1, "name": "Alice", "member_since": "2024" },
        { "id": 2, "name": "Bob",   "member_since": "2023" }
    ],
    "meta": {
        "total":        120,
        "per_page":      15,
        "current_page":   1,
        "last_page":      8,
        "from":           1,
        "to":            15
    }
}
```

You can still add extra keys on top with `withMeta()`:

```php
UserResource::fromPaginator($page)
    ->withMeta(['total' => $page['total'], 'links' => $links])
    ->respond();
```

Note: `withMeta()` replaces the entire meta block. Call `fromPaginator()` first, then chain `withMeta()` only when you need to override pagination meta entirely.
