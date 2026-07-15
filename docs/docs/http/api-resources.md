---
id: api-resources
title: API Resources
sidebar_position: 3
---

# API Resources

`Core\Http\ApiResource` shapes model data for API responses — hiding sensitive fields, renaming keys, and adding computed properties.

## Creating a resource

```bash
php LazyMePHP make:resource UserResource
# scaffolds App/Resources/UserResource.php
```

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

## In pagination responses

Pair with `paginate()`:

```php
$page = User::query()->where('active', 1)->paginate(15, $currentPage);

UserResource::collection($page['data'])
    ->withMeta([
        'total'        => $page['total'],
        'current_page' => $page['current_page'],
        'last_page'    => $page['last_page'],
    ])
    ->respond();
```
