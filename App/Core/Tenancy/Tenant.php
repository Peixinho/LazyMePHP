<?php

declare(strict_types=1);

namespace Core\Tenancy;

/**
 * Holds the current tenant for the duration of the request.
 *
 * Resolved by TenantMiddleware and accessible anywhere:
 *
 *   Tenant::current();       // ['id' => 1, 'slug' => 'acme', ...]
 *   Tenant::id();            // 1
 *   Tenant::slug();          // 'acme'
 *   Tenant::isResolved();    // true after middleware ran
 */
class Tenant
{
    private static ?array $current = null;

    public static function set(array $data): void
    {
        self::$current = $data;
    }

    public static function current(): ?array
    {
        return self::$current;
    }

    public static function id(): mixed
    {
        return self::$current['id'] ?? null;
    }

    public static function slug(): ?string
    {
        return self::$current['slug'] ?? null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$current[$key] ?? $default;
    }

    public static function isResolved(): bool
    {
        return self::$current !== null;
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
