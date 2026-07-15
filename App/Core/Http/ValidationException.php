<?php

declare(strict_types=1);

namespace Core\Http;

class ValidationException extends \RuntimeException
{
    public function __construct(
        private readonly array $errors,
        string $message = 'The given data was invalid.',
        int $code = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return is_array($fieldErrors) ? $fieldErrors[0] : (string)$fieldErrors;
        }
        return null;
    }
}
