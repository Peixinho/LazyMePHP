<?php

declare(strict_types=1);

namespace Core;

/**
 * Fluent proxy returned by the str() global helper.
 *
 *   str('hello world')->slug()    // "hello-world"
 *   str('hello_world')->camel()   // "helloWorld"
 *   str()->uuid()                 // "4b3f…"
 *
 * @method static string camel()
 * @method static string studly()
 * @method static string snake(string $delimiter = '_')
 * @method static string kebab()
 * @method static string slug(string $separator = '-')
 * @method static string title()
 * @method static string upper()
 * @method static string lower()
 * @method static string headline()
 * @method static string ascii()
 */
class StrProxy
{
    public function __construct(private readonly ?string $value) {}

    /**
     * Delegate method calls to Str static methods.
     * When $value is null, the first argument becomes the subject (for str()->uuid() style).
     */
    public function __call(string $method, array $args): mixed
    {
        if ($this->value !== null) {
            return Str::{$method}($this->value, ...$args);
        }
        return Str::{$method}(...$args);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return Str::{$method}(...$args);
    }

    public function __toString(): string
    {
        return $this->value ?? '';
    }
}
