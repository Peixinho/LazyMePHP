<?php

declare(strict_types=1);

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return \Core\Config::get($key, $default);
    }
}

if (!function_exists('config_set')) {
    function config_set(string $key, mixed $value): void
    {
        \Core\Config::set($key, (string)$value);
    }
}
