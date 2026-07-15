<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Standalone request validator.
 *
 * Subclass and override rules() and optionally authorize():
 *
 *   class CreatePostRequest extends FormRequest {
 *       public function rules(): array {
 *           return [
 *               'title' => 'required|min:3|max:255',
 *               'body'  => 'required',
 *               'slug'  => 'required|regex:/^[a-z0-9-]+$/',
 *           ];
 *       }
 *
 *       public function authorize(): bool {
 *           return Auth::check();
 *       }
 *   }
 *
 *   $req = new CreatePostRequest();
 *   if (!$req->passes()) {
 *       return json_encode(['errors' => $req->errors()]);
 *   }
 *   $data = $req->validated();
 */
abstract class FormRequest
{
    private array $input;
    private array $errorBag = [];

    public function __construct(?array $input = null)
    {
        if ($input !== null) {
            $this->input = $input;
        } else {
            $this->input = $this->resolveInput();
        }
    }

    abstract public function rules(): array;

    public function authorize(): bool
    {
        return true;
    }

    /** Returns false if any rule fails or authorize() returns false. */
    public function passes(): bool
    {
        if (!$this->authorize()) {
            $this->errorBag['_authorize'] = ['Unauthorized.'];
            return false;
        }

        $this->errorBag = [];
        $this->runRules();
        return empty($this->errorBag);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errorBag;
    }

    /** Only the fields listed in rules(), after passing. */
    public function validated(): array
    {
        return array_intersect_key($this->input, $this->rules());
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->input;
    }

    private function resolveInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $body = file_get_contents('php://input');
            $decoded = json_decode($body ?: '', true);
            return is_array($decoded) ? $decoded : [];
        }

        return array_merge($_GET, $_POST);
    }

    private function runRules(): void
    {
        foreach ($this->rules() as $field => $ruleString) {
            $value = $this->input[$field] ?? null;
            $rules = explode('|', $ruleString);

            foreach ($rules as $rule) {
                [$ruleName, $param] = $this->parseRule($rule);

                if ($ruleName === 'required') {
                    if ($value === null || $value === '') {
                        $this->addError($field, "The {$field} field is required.");
                        break;
                    }
                    continue;
                }

                if ($value === null || $value === '') continue;

                $error = $this->applyRule($ruleName, $param, $field, $value);
                if ($error !== null) {
                    $this->addError($field, $error);
                }
            }
        }
    }

    private function parseRule(string $rule): array
    {
        if (!str_contains($rule, ':')) return [$rule, null];
        [$name, $param] = explode(':', $rule, 2);
        return [$name, $param];
    }

    private function applyRule(string $name, ?string $param, string $field, mixed $value): ?string
    {
        return match ($name) {
            'email'   => filter_var($value, FILTER_VALIDATE_EMAIL) ? null
                          : "The {$field} must be a valid email address.",
            'url'     => filter_var($value, FILTER_VALIDATE_URL) ? null
                          : "The {$field} must be a valid URL.",
            'integer' => (is_numeric($value) && floor((float)$value) == (float)$value) ? null
                          : "The {$field} must be an integer.",
            'numeric' => is_numeric($value) ? null
                          : "The {$field} must be a number.",
            'boolean' => in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true) ? null
                          : "The {$field} must be true or false.",
            'min'     => $this->applyMin($field, $value, (int)$param),
            'max'     => $this->applyMax($field, $value, (int)$param),
            'in'      => in_array($value, explode(',', $param ?? ''), true) ? null
                          : "The {$field} must be one of: {$param}.",
            'regex'   => @preg_match($param ?? '//', $value) ? null
                          : "The {$field} format is invalid.",
            'min_digits' => (is_numeric($value) && strlen((string)(int)$value) >= (int)$param) ? null
                          : "The {$field} must have at least {$param} digits.",
            'max_digits' => (is_numeric($value) && strlen((string)(int)$value) <= (int)$param) ? null
                          : "The {$field} must not have more than {$param} digits.",
            default   => null,
        };
    }

    private function applyMin(string $field, mixed $value, int $param): ?string
    {
        if (is_numeric($value)) {
            return (float)$value >= $param ? null : "The {$field} must be at least {$param}.";
        }
        return strlen((string)$value) >= $param ? null
            : "The {$field} must be at least {$param} characters.";
    }

    private function applyMax(string $field, mixed $value, int $param): ?string
    {
        if (is_numeric($value)) {
            return (float)$value <= $param ? null : "The {$field} must not be greater than {$param}.";
        }
        return strlen((string)$value) <= $param ? null
            : "The {$field} must not be more than {$param} characters.";
    }

    private function addError(string $field, string $message): void
    {
        $this->errorBag[$field][] = $message;
    }
}
