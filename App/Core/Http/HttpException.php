<?php

declare(strict_types=1);

namespace Core\Http;

class HttpException extends \RuntimeException
{
    public function __construct(int $statusCode, string $body = '')
    {
        parent::__construct("HTTP {$statusCode}: " . substr($body, 0, 200), $statusCode);
    }
}
