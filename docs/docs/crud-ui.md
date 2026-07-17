---
id: crud-ui
title: CRUD Web UI
sidebar_position: 12
---

# CRUD Web UI

Every table in the database gets a full web UI automatically. No code generation required.

## Auto-generated routes

| Method | Path | Action |
|---|---|---|
| GET | `/{table}` | Paginated list with filters |
| GET | `/{table}/new` | New record form |
| GET | `/{table}/{id}/edit` | Edit form |
| POST | `/{table}` | Create |
| POST | `/{table}/{id}` | Update |
| POST | `/{table}/{id}/delete` | Delete |

These 6 routes are fixed — `CrudController` hooks change what happens *inside* them, not the route set itself. To add, drop, or reshape routes for a table entirely, create `App/Routes/{table}.php` (or scaffold one with `php LazyMePHP make:router <table>`); its presence fully replaces the standard 6 for that table. See [Routing](./routing#overriding-the-auto-wired-routes).

## Views

Generic Blade templates live in `App/Views/_Crud/`. To override for a specific table, create table-specific files — the controller resolves to these first:

```
App/Views/{TableName}/index.blade.php   → list page
App/Views/{TableName}/edit.blade.php    → create / edit page
```

All schema variables are passed automatically:

| Variable | Description |
|---|---|
| `$schema` | Column definitions from the live schema |
| `$record` | The current model instance (on edit) |
| `$pk` | Primary key column name |
| `$table` | Table name |
| `$foreignKeys` | FK relationships for dropdown rendering |

## Customising behaviour

Create `App/Controllers/{TableName}.php` (matching the table name, PascalCase) to extend the default behaviour — or scaffold it with `php LazyMePHP make:controller <table>` (add `--hidden` to exclude the table from auto-wiring). `php LazyMePHP make:view <table>` scaffolds the two view files above, and `make:all <table>` does both.

```php
namespace Controllers;
use Core\CrudController;
use Core\Model;

class Users extends CrudController {
    protected static string $table = 'users';

    // Columns with a real FK constraint render as dropdowns automatically.
    // Only declare foreignKeys() to override the target table or add a
    // relationship that has no DB-level constraint:
    protected function foreignKeys(): array {
        return ['role_id' => 'roles'];
    }

    // Add extra validation on top of model rules
    protected function extraValidationRules(): array {
        return [
            'username' => [
                'validations' => [\Core\Validations\ValidationsMethod::STRING],
                'required'    => true,
            ],
        ];
    }

    // Called before every save (create or update)
    protected function beforeSave(Model $obj, array &$data, bool $isUpdate): void {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    protected function afterSave(Model $obj, bool $isUpdate): void {}
    protected function beforeDelete(Model $obj): void {}

    // Restrict which columns the GraphQL API exposes for this table
    public function exposedFields(): array {
        return ['id', 'name', 'email', 'role_id', 'created_at'];
    }

    // Restrict this table's queries/mutations AND its web CRUD routes to
    // specific roles — one declaration governs both surfaces (Core\Auth\Gate
    // enforces it for GraphQL and for Core\AutoRouter alike). Empty (default)
    // means no restriction beyond authentication. Applies to both reading and
    // writing; override requiredRolesForRead()/requiredRolesForWrite()
    // instead when they need to differ. See Security > GraphQL & web CRUD UI
    // authorization for how this is enforced.
    public function requiredRoles(): array {
        return ['admin'];
    }

    // Restrict access to a *specific* record, e.g. users editing only their
    // own — requiredRoles() can't express this, it never sees which record
    // is targeted. Checked for the single-record query/edit page, update,
    // and delete; not called for the list query/page or create.
    public function authorizeRecord(string $operation, Model $record): bool {
        return (string) \Core\Auth\Auth::id() === (string) $record->getPrimaryKey();
    }

    // Set to true to exclude this table from the auto-wired UI
    // public static bool $hidden = true;
}
```
