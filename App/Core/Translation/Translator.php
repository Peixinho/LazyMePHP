<?php

declare(strict_types=1);

namespace Core\Translation;

/**
 * Minimal i18n translator.
 *
 * Place translation files in lang/{locale}/{group}.php returning arrays:
 *
 *   // lang/en/auth.php
 *   return ['failed' => 'These credentials do not match.', 'throttle' => 'Too many attempts.'];
 *
 * Usage:
 *   __('auth.failed')                            // "These credentials do not match."
 *   __('auth.throttle')                          // via Translator::trans()
 *   __('messages.welcome', ['name' => 'Alice'])  // "Welcome, Alice!"
 *   Translator::setLocale('pt');
 *   Translator::trans('auth.failed', [], 'en');  // force English
 *
 * Nested keys:
 *   // lang/en/messages.php → ['user' => ['greeting' => 'Hello, :name!']]
 *   __('messages.user.greeting', ['name' => 'Alice'])  // "Hello, Alice!"
 */
class Translator
{
    private static string $locale = 'en';
    private static string $fallback = 'en';

    /** @var array<string, array<string, mixed>> locale → group → translations */
    private static array $loaded = [];

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function setFallback(string $locale): void
    {
        self::$fallback = $locale;
    }

    /**
     * Translate a key with optional replacements.
     *
     * @param string               $key     Format: 'group.key' or 'group.nested.key'
     * @param array<string, mixed> $replace Placeholders like :name → Alice
     * @param string|null          $locale  Override locale for this call
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= self::$locale;
        $value    = self::resolve($key, $locale);

        if ($value === null && $locale !== self::$fallback) {
            $value = self::resolve($key, self::$fallback);
        }

        if ($value === null) {
            return $key; // Return key as-is if not found
        }

        if (!empty($replace)) {
            foreach ($replace as $placeholder => $v) {
                $value = str_replace(':' . $placeholder, (string)$v, $value);
                $value = str_replace(':' . ucfirst($placeholder), ucfirst((string)$v), $value);
                $value = str_replace(':' . strtoupper($placeholder), strtoupper((string)$v), $value);
            }
        }

        return $value;
    }

    /** Check if a translation key exists. */
    public static function has(string $key, ?string $locale = null): bool
    {
        return self::resolve($key, $locale ?? self::$locale) !== null;
    }

    /** Load translations for a locale and group. */
    public static function load(string $locale, string $group): void
    {
        if (isset(self::$loaded[$locale][$group])) return;

        $path = self::langPath() . "/{$locale}/{$group}.php";
        if (file_exists($path)) {
            $translations = require $path;
            self::$loaded[$locale][$group] = is_array($translations) ? $translations : [];
        } else {
            self::$loaded[$locale][$group] = [];
        }
    }

    /** Path to the lang directory. */
    public static function langPath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH . '/lang' : dirname(__DIR__, 3) . '/lang';
    }

    /** Clear loaded translations (useful in tests). */
    public static function flush(): void
    {
        self::$loaded = [];
    }

    private static function resolve(string $key, string $locale): ?string
    {
        $parts = explode('.', $key, 2);
        if (count($parts) < 2) return null;

        $group   = $parts[0];
        $subKey  = $parts[1];

        self::load($locale, $group);

        $translations = self::$loaded[$locale][$group] ?? [];
        $value        = self::dot($translations, $subKey);

        return is_string($value) ? $value : null;
    }

    /** Traverse a nested array using dot notation. */
    private static function dot(array $array, string $key): mixed
    {
        if (isset($array[$key])) return $array[$key];

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return null;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}
