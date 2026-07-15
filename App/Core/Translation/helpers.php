<?php

declare(strict_types=1);

if (!function_exists('__')) {
    /**
     * Translate a key using the Translator.
     *
     *   __('auth.failed')
     *   __('messages.welcome', ['name' => 'Alice'])
     *   __('validation.required', ['field' => 'email'], 'pt')
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return \Core\Translation\Translator::trans($key, $replace, $locale);
    }
}

if (!function_exists('trans')) {
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return \Core\Translation\Translator::trans($key, $replace, $locale);
    }
}
