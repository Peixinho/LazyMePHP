---
sidebar_position: 3
---

# OpenAPI PHP 8 Attributes

Annotate your controller methods with PHP 8 attributes to include custom routes in the auto-generated OpenAPI spec.

## Setup

The `Generator` scans `App/Controllers/` automatically when `/openapi.json` is requested. No extra configuration needed.

## Available attributes

| Attribute | Target | Description |
|---|---|---|
| `#[Get(path)]` | method | HTTP GET route |
| `#[Post(path)]` | method | HTTP POST route |
| `#[Put(path)]` | method | HTTP PUT route |
| `#[Patch(path)]` | method | HTTP PATCH route |
| `#[Delete(path)]` | method | HTTP DELETE route |
| `#[Summary(text)]` | method | Short description (shown in Swagger UI) |
| `#[Description(text)]` | method | Long description |
| `#[Tag(name)]` | method | Group tag (repeatable) |
| `#[Response(status, description, ref?)]` | method | Response definition (repeatable) |
| `#[Body(contentType, schema, required)]` | method | Request body schema |
| `#[Param(name, in, type, required, description)]` | method | Path/query/header parameter (repeatable) |
| `#[ApiController(prefix, tag)]` | class | Applies prefix and tag to all methods |

## Example controller

```php
use Core\OpenAPI\Attributes\{ApiController, Get, Post, Summary, Tag, Response, Body, Param};

#[ApiController(prefix: '/api/v2', tag: 'Products')]
class ProductController
{
    #[Get('/products')]
    #[Summary('List all products')]
    #[Response(200, 'Paginated product list', ref: 'products')]
    public function index(): void
    {
        // ...
    }

    #[Get('/products/{id}')]
    #[Summary('Get one product')]
    #[Param('id', in: 'path', type: 'integer')]
    #[Response(200, 'Product object', ref: 'products')]
    #[Response(404, 'Not found')]
    public function show(int $id): void
    {
        // ...
    }

    #[Post('/products')]
    #[Summary('Create a product')]
    #[Body('application/json', schema: ['name' => 'string', 'price' => 'number'])]
    #[Response(201, 'Created')]
    #[Response(422, 'Validation error')]
    public function store(): void
    {
        // ...
    }
}
```

The generated `operationId` is `{controller}_{method}`, e.g. `productcontroller_index`.

## Scanning programmatically

```php
use Core\OpenAPI\Generator;

// Scan a custom directory
$paths = Generator::scanControllerAttributes('/path/to/controllers');

// Merge into a custom spec
$spec = Generator::generate();
```

## DB-generated vs attribute routes

The `Generator::generate()` method first builds CRUD routes from the live DB schema, then merges in attribute-annotated routes. **Attribute routes take precedence** — if both define `GET /api/products`, the attribute version wins.

## Viewing the spec

```
GET /openapi.json
```

Enabled by default when `OPENAPI_ENABLED` is not `false`. View in Swagger UI or Redoc:

```ini
# .env
OPENAPI_ENABLED=true
```
