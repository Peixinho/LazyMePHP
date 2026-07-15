---
id: form-request
title: FormRequest Validation
sidebar_position: 2
---

# FormRequest Validation

`Core\Http\FormRequest` validates incoming HTTP input (POST fields, GET params, or JSON body) at the controller level, independently of any model.

## Creating a request class

```bash
php LazyMePHP make:request CreatePostRequest
# scaffolds App/Requests/CreatePostRequest.php
```

```php
// App/Requests/CreatePostRequest.php

class CreatePostRequest extends \Core\Http\FormRequest {

    public function rules(): array {
        return [
            'title' => 'required|min:3|max:255',
            'body'  => 'required',
            'email' => 'required|email',
            'role'  => 'in:admin,editor',
            'site'  => 'url',
            'age'   => 'integer|min:0|max:120',
        ];
    }

    public function authorize(): bool {
        return \Core\Auth\Auth::check();
    }
}
```

## Using in a controller

```php
$req = new CreatePostRequest();   // reads $_POST / JSON body automatically

if (!$req->authorize()) {
    http_response_code(403);
    return;
}

if ($req->fails()) {
    echo json_encode(['errors' => $req->errors()]);
    return;
}

$data = $req->validated();   // only fields listed in rules(), safe to use directly
```

## All methods

```php
$req->passes()      // true when all rules pass
$req->fails()       // true when any rule fails
$req->errors()      // ['field' => ['message', ...], ...]
$req->validated()   // array of validated fields only
$req->input('key')  // get a single input value
$req->input('key', 'default')
$req->all()         // all raw input
```

## Available rules

| Rule | Description |
|---|---|
| `required` | Must be present and non-empty |
| `email` | Must be a valid email address |
| `url` | Must be a valid URL |
| `integer` | Must be an integer |
| `numeric` | Must be numeric (int or float) |
| `boolean` | `true`, `false`, `1`, or `0` |
| `min:N` | String: min length N. Number: min value N |
| `max:N` | String: max length N. Number: max value N |
| `in:a,b,c` | Must be one of the listed values |
| `regex:/pattern/` | Must match the regular expression |

Rules are pipe-separated: `'required\|email\|max:255'`.
