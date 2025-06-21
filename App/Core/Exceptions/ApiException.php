<?php

declare(strict_types=1);

namespace Core\Exceptions;

/**
 * Custom API Exception for LazyMePHP Framework
 */
class ApiException extends \Exception
{
    private string $errorCode;
    private ?array $details;

    public function __construct(
        string $message = '',
        string $errorCode = 'INTERNAL_ERROR',
        int $httpCode = 500,
        ?array $details = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $httpCode, $previous);
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * Create a validation exception
     */
    public static function validationError(array $errors): self
    {
        return new self(
            'Validation failed',
            'VALIDATION_ERROR',
            422,
            ['validation_errors' => $errors]
        );
    }

    /**
     * Create a not found exception
     */
    public static function notFound(string $resource = 'Resource'): self
    {
        return new self(
            "$resource not found",
            'NOT_FOUND',
            404
        );
    }

    /**
     * Create an unauthorized exception
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self(
            $message,
            'UNAUTHORIZED',
            401
        );
    }

    /**
     * Create a forbidden exception
     */
    public static function forbidden(string $message = 'Access denied'): self
    {
        return new self(
            $message,
            'FORBIDDEN',
            403
        );
    }

    /**
     * Create a rate limit exception
     */
    public static function rateLimitExceeded(int $retryAfter = 60): self
    {
        return new self(
            'Rate limit exceeded',
            'RATE_LIMIT_EXCEEDED',
            429,
            ['retry_after' => $retryAfter]
        );
    }

    /**
     * Create a bad request exception
     */
    public static function badRequest(string $message = 'Bad request'): self
    {
        return new self(
            $message,
            'BAD_REQUEST',
            400
        );
    }

    /**
     * Create a conflict exception
     */
    public static function conflict(string $message = 'Resource conflict'): self
    {
        return new self(
            $message,
            'CONFLICT',
            409
        );
    }
} 