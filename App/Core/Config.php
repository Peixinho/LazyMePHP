<?php

declare(strict_types=1);

namespace Core;

/**
 * Config — centralized access to environment/configuration values.
 *
 * Reads from $_ENV using dot-notation mapped to UPPER_SNAKE env keys.
 * Dot notation: Config::get('mail.host') → reads $_ENV['MAIL_HOST']
 *
 * Usage:
 *   Config::get('app.env', 'production')    // APP_ENV
 *   Config::get('db.name')                  // DB_NAME
 *   Config::get('mail.smtp.host')           // MAIL_SMTP_HOST
 *   Config::set('app.debug', 'true')        // set at runtime
 *   Config::has('redis.password')           // check existence
 *   Config::bool('app.debug')               // coerce to bool
 *   Config::int('mail.port', 587)           // coerce to int
 */
class Config
{
    /** Runtime overrides (take precedence over $_ENV). */
    private static array $overrides = [];

    /** Convert dot-notation key to the corresponding env variable name. */
    private static function envKey(string $key): string
    {
        return strtoupper(str_replace('.', '_', $key));
    }

    /**
     * Retrieve a config value.
     *
     * @param string $key     Dot-notation key, e.g. 'mail.host'
     * @param mixed  $default Returned when the key is not set
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$overrides)) {
            return self::$overrides[$key];
        }

        $envKey = self::envKey($key);
        return $_ENV[$envKey] ?? $default;
    }

    /** Set a runtime override (does not write to $_ENV). */
    public static function set(string $key, mixed $value): void
    {
        self::$overrides[$key] = $value;
    }

    /** Return true if the key is present in overrides or $_ENV. */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$overrides)
            || array_key_exists(self::envKey($key), $_ENV);
    }

    /** Get a value coerced to bool. Recognises 'true'/'false'/'1'/'0'/'yes'/'no'. */
    public static function bool(string $key, bool $default = false): bool
    {
        $val = self::get($key);
        if ($val === null) return $default;
        if (is_bool($val)) return $val;
        return in_array(strtolower((string)$val), ['true', '1', 'yes', 'on'], true);
    }

    /** Get a value coerced to int. */
    public static function int(string $key, int $default = 0): int
    {
        $val = self::get($key);
        return $val !== null ? (int)$val : $default;
    }

    /** Get a value coerced to float. */
    public static function float(string $key, float $default = 0.0): float
    {
        $val = self::get($key);
        return $val !== null ? (float)$val : $default;
    }

    /** Return all $_ENV values merged with runtime overrides. */
    public static function all(): array
    {
        return array_merge($_ENV, self::$overrides);
    }

    /** Clear runtime overrides (useful in tests). */
    public static function flush(): void
    {
        self::$overrides = [];
    }
}
