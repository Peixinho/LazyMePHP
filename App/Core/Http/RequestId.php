<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Generates or propagates a per-request X-Request-ID header.
 *
 * Call RequestId::emit() early in the request lifecycle (in boot()).
 * The ID is available via RequestId::current() anywhere during the request.
 */
class RequestId
{
    private static ?string $current = null;

    public static function emit(): void
    {
        $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        // Accept the client's ID only if it looks safe (alphanumeric, hyphens, 36 chars max)
        if ($incoming !== '' && preg_match('/^[a-zA-Z0-9\-]{1,36}$/', $incoming)) {
            self::$current = $incoming;
        } else {
            self::$current = self::generate();
        }

        if (!headers_sent()) {
            header('X-Request-ID: ' . self::$current);
        }
    }

    public static function current(): ?string
    {
        return self::$current;
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    private static function generate(): string
    {
        $hex = bin2hex(random_bytes(16));
        // Format as UUID v4 shape for readability
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
