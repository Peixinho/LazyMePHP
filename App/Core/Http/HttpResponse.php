<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Wraps a raw HTTP response from HttpClient.
 */
class HttpResponse
{
    public function __construct(
        private readonly int    $statusCode,
        private readonly string $rawBody,
    ) {}

    public function status(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->rawBody;
    }

    public function ok(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function failed(): bool
    {
        return !$this->ok();
    }

    public function clientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function serverError(): bool
    {
        return $this->statusCode >= 500;
    }

    /** Decode JSON body. Returns null on parse failure. */
    public function json(): mixed
    {
        return json_decode($this->rawBody, true);
    }

    /** Throw an HttpException when the response is 4xx or 5xx. */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new HttpException($this->statusCode, $this->rawBody);
        }
        return $this;
    }

    /** Return a default value when the response failed. */
    public function onError(mixed $default): mixed
    {
        return $this->failed() ? $default : $this;
    }
}
