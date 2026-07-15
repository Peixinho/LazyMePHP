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

    /** Override to provide custom error messages: ['field.rule' => 'My message'] */
    public function customMessages(): array
    {
        return [];
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
                        $this->addError($field, $this->resolveMessage('required', $field, $param));
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

    /**
     * Resolve a validation message, checking (in order):
     *  1. customMessages() override for 'field.rule'
     *  2. customMessages() override for 'rule'
     *  3. Translation key 'validation.rule_variant'
     *  4. Hardcoded English fallback
     */
    private function resolveMessage(string $rule, string $field, ?string $param = null, string $variant = ''): string
    {
        $custom = $this->customMessages();

        // Field-specific override: e.g. 'name.required'
        if (isset($custom["{$field}.{$rule}"])) {
            return $custom["{$field}.{$rule}"];
        }
        // Rule-wide override: e.g. 'required'
        if (isset($custom[$rule])) {
            return str_replace([':field', ':param'], [$field, (string)$param], $custom[$rule]);
        }

        // Translation lookup
        $tKey     = 'validation.' . ($variant !== '' ? "{$rule}_{$variant}" : $rule);
        $tMessage = __($tKey, ['field' => $field, 'param' => (string)$param]);

        // If the translator returned the key itself it means no translation was found
        if ($tMessage !== $tKey) return $tMessage;

        // Hardcoded English fallbacks (always present even without lang files)
        return match ($rule) {
            'required'   => "The {$field} field is required.",
            'email'      => "The {$field} must be a valid email address.",
            'url'        => "The {$field} must be a valid URL.",
            'integer'    => "The {$field} must be an integer.",
            'numeric'    => "The {$field} must be a number.",
            'boolean'    => "The {$field} must be true or false.",
            'alpha'      => "The {$field} may only contain letters.",
            'alpha_num'  => "The {$field} may only contain letters and numbers.",
            'confirmed'  => "The {$field} confirmation does not match.",
            'regex'      => "The {$field} format is invalid.",
            'in'         => "The selected {$field} is invalid.",
            'not_in'     => "The selected {$field} is invalid.",
            'min'        => $variant === 'numeric'
                              ? "The {$field} must be at least {$param}."
                              : "The {$field} must be at least {$param} characters.",
            'max'        => $variant === 'numeric'
                              ? "The {$field} must not be greater than {$param}."
                              : "The {$field} must not be more than {$param} characters.",
            'min_digits' => "The {$field} must have at least {$param} digits.",
            'max_digits' => "The {$field} must not have more than {$param} digits.",
            default      => "The {$field} is invalid.",
        };
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
            'email'      => filter_var($value, FILTER_VALIDATE_EMAIL) ? null
                              : $this->resolveMessage('email', $field, $param),
            'url'        => filter_var($value, FILTER_VALIDATE_URL) ? null
                              : $this->resolveMessage('url', $field, $param),
            'integer'    => (is_numeric($value) && floor((float)$value) == (float)$value) ? null
                              : $this->resolveMessage('integer', $field, $param),
            'numeric'    => is_numeric($value) ? null
                              : $this->resolveMessage('numeric', $field, $param),
            'boolean'    => in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true) ? null
                              : $this->resolveMessage('boolean', $field, $param),
            'alpha'      => ctype_alpha((string)$value) ? null
                              : $this->resolveMessage('alpha', $field, $param),
            'alpha_num'  => ctype_alnum((string)$value) ? null
                              : $this->resolveMessage('alpha_num', $field, $param),
            'min'        => $this->applyMin($field, $value, (int)$param),
            'max'        => $this->applyMax($field, $value, (int)$param),
            'in'         => in_array($value, array_map('trim', explode(',', $param ?? '')), true) ? null
                              : $this->resolveMessage('in', $field, $param),
            'not_in'     => !in_array($value, array_map('trim', explode(',', $param ?? '')), true) ? null
                              : $this->resolveMessage('not_in', $field, $param),
            'confirmed'  => $this->applyConfirmed($field, $value),
            'regex'      => $this->applyRegex($field, $value, $param),
            'min_digits' => (is_numeric($value) && strlen((string)(int)$value) >= (int)$param) ? null
                              : $this->resolveMessage('min_digits', $field, $param),
            'max_digits' => (is_numeric($value) && strlen((string)(int)$value) <= (int)$param) ? null
                              : $this->resolveMessage('max_digits', $field, $param),
            default      => null,
        };
    }

    private function applyRegex(string $field, mixed $value, ?string $pattern): ?string
    {
        if ($pattern === null || $pattern === '') {
            return $this->resolveMessage('regex_missing', $field);
        }
        $result = @preg_match($pattern, (string)$value);
        if ($result === false) {
            return $this->resolveMessage('regex_invalid', $field);
        }
        return $result === 1 ? null : $this->resolveMessage('regex', $field, $pattern);
    }

    private function applyConfirmed(string $field, mixed $value): ?string
    {
        $confirmation = $this->input["{$field}_confirmation"] ?? null;
        return $value === $confirmation ? null : $this->resolveMessage('confirmed', $field);
    }

    private function applyMin(string $field, mixed $value, int $param): ?string
    {
        if (is_numeric($value)) {
            return (float)$value >= $param ? null : $this->resolveMessage('min', $field, (string)$param, 'numeric');
        }
        return strlen((string)$value) >= $param ? null : $this->resolveMessage('min', $field, (string)$param, 'string');
    }

    private function applyMax(string $field, mixed $value, int $param): ?string
    {
        if (is_numeric($value)) {
            return (float)$value <= $param ? null : $this->resolveMessage('max', $field, (string)$param, 'numeric');
        }
        return strlen((string)$value) <= $param ? null : $this->resolveMessage('max', $field, (string)$param, 'string');
    }

    private function addError(string $field, string $message): void
    {
        $this->errorBag[$field][] = $message;
    }
}
