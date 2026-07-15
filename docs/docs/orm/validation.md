---
id: validation
title: Model Validation
sidebar_position: 7
---

# Model Validation

Define `$rules` on a model subclass to enforce constraints before saving. Validation runs automatically on `Save()`, or you can call it manually.

## Defining rules

```php
use Core\Model;

class User extends Model {
    protected static string $table = 'users';

    protected static array $rules = [
        'name'  => 'required|min:2|max:100',
        'email' => 'required|email',
        'age'   => 'integer|min:0|max:150',
        'role'  => 'in:admin,editor,viewer',
        'site'  => 'url',
    ];
}
```

## Checking validity

```php
$user = new User();
$user->name  = '';
$user->email = 'not-an-email';

if (!$user->passes()) {
    print_r($user->errors());
    // [
    //   'name'  => ['The name field is required.'],
    //   'email' => ['The email field must be a valid email address.'],
    // ]
}

// Or get all errors in one call:
$errors = $user->validate();  // empty array = valid
```

## Available rules

| Rule | Description |
|---|---|
| `required` | Field must be present and non-empty |
| `email` | Must be a valid email address |
| `url` | Must be a valid URL |
| `integer` | Must be an integer |
| `numeric` | Must be numeric (int or float) |
| `boolean` | Must be `true`, `false`, `1`, or `0` |
| `min:N` | String: minimum length N. Number: minimum value N |
| `max:N` | String: maximum length N. Number: maximum value N |
| `in:a,b,c` | Must be one of the listed values |
| `confirmed` | Must match a sibling field named `{field}_confirmation` |
| `unique:table,column` | Value must not exist in `table.column` |
| `unique:table,column,exceptId` | Same, but ignores the row with `id = exceptId` (useful on update) |
| `exists:table,column` | Value must already exist in `table.column` |

Rules are pipe-separated: `'required\|email\|max:255'`.

### `confirmed`

```php
protected static array $rules = [
    'password' => 'required|min:8|confirmed',
];

// Model must also have a password_confirmation field set:
$user->password              = 'secret123';
$user->password_confirmation = 'secret123';
$user->passes(); // true
```

### `unique` and `exists`

```php
protected static array $rules = [
    'email'   => 'required|email|unique:users,email',
    'role_id' => 'required|exists:roles,id',
];

// Exclude the current row on update:
protected static array $rules = [
    'email' => 'required|email|unique:users,email,' . self::$primaryKey,
];
```

These rules hit the database — they run during `validate()` / `Save()`, so the connection must be active.
