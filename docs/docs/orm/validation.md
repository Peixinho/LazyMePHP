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

Rules are pipe-separated: `'required\|email\|max:255'`.
