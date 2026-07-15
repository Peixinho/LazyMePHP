---
id: openapi
title: OpenAPI Spec
sidebar_position: 2
---

# OpenAPI 3.0 Spec

A full OpenAPI 3.0 specification is auto-generated from the live schema and served as JSON.

## Endpoint

```
GET /openapi.json
```

The spec includes:

- CRUD paths for every non-system table (tables without `__` prefix)
- Auth endpoints when `AUTH_TABLE` is configured
- Request/response schemas derived from the live column types
- Bearer token security scheme

## Disabling

```env
OPENAPI_ENABLED=false
```

## Using with Swagger UI

Point any Swagger UI instance at `/openapi.json`:

```html
<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css" />
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
<script>
  SwaggerUIBundle({
    url: '/openapi.json',
    dom_id: '#swagger-ui',
  });
</script>
```

## Using with Postman or Insomnia

Import the URL `http://localhost:8080/openapi.json` directly into Postman or Insomnia to get a full collection generated from your schema.
