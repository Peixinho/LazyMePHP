---
sidebar_position: 11
---

# Validation

LazyMePHP provides two ways to validate data: a standalone `Validator` class for inline use, and `FormRequest` subclasses for reusable, class-based validation.

---

## Standalone `Validator`

No subclassing required. Pass data + rules, call `passes()` or `validate()`.

```php
use Core\Validator;

$v = Validator::make($request->all(), [
    'name'     => 'required|min:2|max:100',
    'email'    => 'required|email',
    'password' => 'required|min:8|confirmed',
    'age'      => 'integer|min:18',
]);

if ($v->fails()) {
    return Response::json(['errors' => $v->errors()], 422);
}

$data = $v->validated(); // only the declared fields, no extras
```

### `validate()` — throw on failure

```php
try {
    $data = Validator::make($input, $rules)->validate();
} catch (\Core\Http\ValidationException $e) {
    $e->errors();      // ['field' => ['message', ...]]
    $e->firstError();  // first error string across all fields
    $e->getCode();     // 422
}
```

### `sometimes()` — conditional rules

Add rules that only apply when a condition is true:

```php
$v = Validator::make($data, ['role' => 'required'])
    ->sometimes('admin_code', 'required|min:8', fn($d) => $d['role'] === 'admin');
```

---

## Available rules

| Rule | Description |
|------|-------------|
| `required` | Field must be present and non-empty |
| `email` | Valid email address |
| `url` | Valid URL |
| `integer` | Whole number (string or int) |
| `numeric` | Any number (int or float) |
| `boolean` | Accepts `true`, `false`, `1`, `0`, `'1'`, `'0'`, `'true'`, `'false'` |
| `alpha` | Letters only |
| `alpha_num` | Letters and digits only |
| `min:N` | String: minimum N characters; Number: minimum value N |
| `max:N` | String: maximum N characters; Number: maximum value N |
| `min_digits:N` | Must have at least N digits |
| `max_digits:N` | Must not have more than N digits |
| `in:a,b,c` | Value must be in the comma-separated list |
| `not_in:a,b` | Value must NOT be in the list |
| `confirmed` | Field must match `{field}_confirmation` input |
| `regex:/pattern/` | Must match the regex pattern |

Non-`required` fields that are empty (`''` or `null`) skip all other rules.

---

## FormRequest (class-based)

For reusable validation in controllers, subclass `FormRequest`:

```php
// App/Requests/CreatePostRequest.php
use Core\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|min:3|max:255',
            'body'  => 'required',
            'slug'  => 'required|regex:/^[a-z0-9-]+$/',
        ];
    }

    public function authorize(): bool
    {
        return true; // add auth logic here
    }
}

// In a controller:
$req = new CreatePostRequest();
if ($req->fails()) {
    return Response::json(['errors' => $req->errors()], 422);
}
$data = $req->validated();
```

Scaffold with:
```bash
php LazyMePHP make:request CreatePostRequest
```

### Custom messages

Override `customMessages()` to provide field-specific or rule-specific strings:

```php
public function customMessages(): array
{
    return [
        'name.required' => 'Please tell us your name.',
        'email'         => 'A valid email address is required.',
    ];
}
```

Keys can be `'field.rule'` (most specific) or just `'rule'` (applies to all fields).

---

## i18n validation messages

Validation errors are resolved through the Translator. The default English strings live in `lang/en/validation.php`. To override them for another locale:

```php
// lang/pt/validation.php
return [
    'required' => 'O campo :field é obrigatório.',
    'email'    => 'O campo :field deve ser um e-mail válido.',
    // ...
];
```

Set the locale before validating:

```php
\Core\Translation\Translator::setLocale('pt');
```

---

## `ValidationException`

Thrown by `Validator::validate()`. Always carries the full errors bag:

```php
try {
    $data = Validator::make($input, $rules)->validate();
} catch (\Core\Http\ValidationException $e) {
    echo $e->firstError();       // "The name field is required."
    print_r($e->errors());       // ['name' => ['The name field is required.']]
    http_response_code($e->getCode()); // 422
}
```

---

## Repopulating forms after failure

Flash old input and errors to the session before redirecting back:

```php
if ($v->fails()) {
    Session::flash('__errors', $v->errors());
    Session::flash('__old',    $request->all());
    back();
}
```

In the Blade view:

```blade
<input name="email" value="{{ old('email') }}">
@if(errors('email'))
    <p class="error">{{ errors('email') }}</p>
@endif
```
