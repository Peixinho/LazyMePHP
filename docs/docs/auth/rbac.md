---
id: rbac
title: Role-Based Access Control
sidebar_position: 2
---

# Role-Based Access Control (RBAC)

RBAC lets you assign roles to users and grant fine-grained permissions to each role. Run `php LazyMePHP migrate` to create the three RBAC tables: `__AUTH_ROLES`, `__AUTH_ROLE_PERMISSIONS`, and `__AUTH_USER_ROLES`.

## Setup

```php
use Core\Auth\RBAC;

// Create roles
RBAC::createRole('admin');
RBAC::createRole('editor');

// Grant permissions to a role
RBAC::grantPermission('editor', 'posts.create');
RBAC::grantPermission('editor', 'posts.update');
RBAC::grantPermission('admin',  'posts.delete');

// Assign a role to a user
RBAC::assignRole($userId, 'editor');
```

## Checks

```php
RBAC::can($userId, 'posts.create');   // true — editor has this permission
RBAC::can($userId, 'posts.delete');   // false — editor does not

RBAC::is($userId, 'editor');          // true
RBAC::is($userId, 'admin');           // false

RBAC::rolesFor($userId);              // ['editor']
RBAC::permissionsFor($userId);        // ['posts.create', 'posts.update']
```

## Route middleware

```php
use Core\Auth\RequiresPermission;
use Core\Auth\RequiresRole;

// Require a specific permission
$router->post('/posts', [PostController::class, 'store'])
       ->addMiddleware(new RequiresPermission('posts.create'));

// Require any one of the listed roles
$router->get('/admin', [AdminController::class, 'index'])
       ->addMiddleware(new RequiresRole('admin', 'superuser'));

// Require ALL listed roles
$router->delete('/nuke', [AdminController::class, 'nuke'])
       ->addMiddleware((new RequiresRole('admin', 'superuser'))->all());
```

Both middleware return `401` when the request is unauthenticated, `403` when the role/permission check fails.

## Management

```php
RBAC::revokePermission('editor', 'posts.update');
RBAC::removeRole($userId, 'editor');
RBAC::deleteRole('editor');
```
