<?php

declare(strict_types=1);

namespace Core\Features;

/**
 * Feature flags — gate code behind named flags.
 *
 * Resolution order (first wins):
 *   1. Programmatic definition via Feature::define()
 *   2. .env variable  APP_FEATURE_{UPPER_SNAKE_NAME}=true/false
 *   3. Default: disabled
 *
 *   Feature::define('dark-mode', true);
 *   Feature::define('new-billing', fn() => $user->isBetaTester());
 *
 *   if (Feature::enabled('dark-mode')) { ... }
 *   Feature::when('new-billing', fn() => redirect('/new-billing'));
 *   Feature::unless('maintenance', fn() => $this->handleRequest());
 */
class Feature
{
    /** @var array<string, bool|callable> */
    private static array $definitions = [];

    /** Define a feature programmatically. */
    public static function define(string $name, bool|callable $value): void
    {
        self::$definitions[$name] = $value;
    }

    /** Is the feature enabled? */
    public static function enabled(string $name): bool
    {
        // Programmatic definition
        if (array_key_exists($name, self::$definitions)) {
            $val = self::$definitions[$name];
            return is_callable($val) ? (bool)$val() : $val;
        }

        // .env: APP_FEATURE_DARK_MODE, APP_FEATURE_NEW_BILLING, etc.
        $envKey = 'APP_FEATURE_' . strtoupper(str_replace(['-', '.'], '_', $name));
        if (isset($_ENV[$envKey])) {
            return filter_var($_ENV[$envKey], FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    public static function disabled(string $name): bool
    {
        return !self::enabled($name);
    }

    /**
     * Execute a callback if the feature is enabled.
     * Returns the callback's return value, or null if disabled.
     */
    public static function when(string $name, callable $callback): mixed
    {
        return self::enabled($name) ? $callback() : null;
    }

    /**
     * Execute a callback if the feature is disabled.
     */
    public static function unless(string $name, callable $callback): mixed
    {
        return self::disabled($name) ? $callback() : null;
    }

    /** Remove a programmatic definition. */
    public static function forget(string $name): void
    {
        unset(self::$definitions[$name]);
    }

    public static function reset(): void
    {
        self::$definitions = [];
    }

    /** Return all defined feature names + their resolved state. */
    public static function all(): array
    {
        $result = [];
        foreach (self::$definitions as $name => $_) {
            $result[$name] = self::enabled($name);
        }
        return $result;
    }
}
