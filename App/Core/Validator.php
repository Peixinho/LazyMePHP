<?php

declare(strict_types=1);

namespace Core;

use Core\Http\FormRequest;

/**
 * Standalone validator — no subclassing required.
 *
 *   $v = Validator::make($_POST, [
 *       'name'  => 'required|min:3|max:100',
 *       'email' => 'required|email',
 *       'age'   => 'integer|min:18',
 *   ]);
 *
 *   if ($v->fails()) {
 *       return json_encode(['errors' => $v->errors()]);
 *   }
 *   $data = $v->validated();
 *
 *   // Throw on failure (throws \Core\Http\ValidationException):
 *   $data = Validator::make($input, $rules)->validate();
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $customMessages;
    private array $errorBag = [];
    private bool $ran = false;

    private function __construct(array $data, array $rules, array $customMessages = [])
    {
        $this->data           = $data;
        $this->rules          = $rules;
        $this->customMessages = $customMessages;
    }

    public static function make(array $data, array $rules, array $messages = []): static
    {
        return new static($data, $rules, $messages);
    }

    public function passes(): bool
    {
        $this->run();
        return empty($this->errorBag);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** Return errors array, keyed by field. */
    public function errors(): array
    {
        $this->run();
        return $this->errorBag;
    }

    /** Return only the validated fields (those that appear in $rules). */
    public function validated(): array
    {
        $this->run();
        return array_intersect_key($this->data, $this->rules);
    }

    /**
     * Run validation and throw ValidationException on failure.
     *
     * @throws \Core\Http\ValidationException
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new \Core\Http\ValidationException($this->errorBag);
        }
        return $this->validated();
    }

    /** Add a custom rule for a field after construction. */
    public function sometimes(string $field, array|string $rules, callable $condition): static
    {
        if ($condition($this->data)) {
            $this->rules[$field] = implode('|', array_merge(
                explode('|', (string)($this->rules[$field] ?? '')),
                (array)$rules,
            ));
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // Internals — delegates to FormRequest's private logic via anonymous subclass
    // -------------------------------------------------------------------------

    private function run(): void
    {
        if ($this->ran) return;
        $this->ran = true;

        $data           = $this->data;
        $rules          = $this->rules;
        $customMessages = $this->customMessages;

        // Create an anonymous FormRequest subclass so we reuse all the rule logic
        $request = new class($data, $rules, $customMessages) extends FormRequest {
            public function __construct(
                private array $dataInput,
                private array $dataRules,
                private array $dataMessages,
            ) {
                parent::__construct($dataInput);
            }

            public function rules(): array
            {
                return $this->dataRules;
            }

            public function customMessages(): array
            {
                return $this->dataMessages;
            }
        };

        $request->passes();
        $this->errorBag = $request->errors();
    }
}
