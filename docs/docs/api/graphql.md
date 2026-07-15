---
id: graphql
title: GraphQL API
sidebar_position: 1
---

# GraphQL API

A full GraphQL API is auto-generated from the live database schema. No resolvers or type definitions to write.

## Endpoint

```
POST /graphql
Content-Type: application/json
```

## Queries

### List with pagination

```graphql
{
    usersList(page: 1, limit: 20) {
        id
        name
        email
        created_at
    }
}
```

### Single record by ID

```graphql
{
    users(id: 1) {
        id
        name
        email
    }
}
```

## Mutations

### Create

```graphql
mutation {
    createUsers(input: {
        name: "Alice"
        email: "alice@example.com"
    }) {
        id
        name
    }
}
```

### Update

```graphql
mutation {
    updateUsers(id: 1, input: { name: "Alice Smith" }) {
        id
        name
    }
}
```

### Delete

```graphql
mutation {
    deleteUsers(id: 1)
}
```

## Security

| Measure | Value |
|---|---|
| Query depth limit | 7 |
| Query complexity limit | 200 |
| Introspection | Disabled unless `APP_ENV=development` |
| Stack traces | Stripped unless `APP_ENV=development` |

## Restricting field exposure

Override `exposedFields()` in a `CrudController` subclass to control which columns are visible via GraphQL:

```php
class Users extends \Core\CrudController {
    protected static string $table = 'users';

    public function exposedFields(): array {
        return ['id', 'name', 'email', 'created_at'];
        // password and other sensitive columns are excluded
    }
}
```

Tables without a controller use all columns by default. System tables (prefixed with `__`) are never exposed.
