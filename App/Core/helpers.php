<?php

declare(strict_types=1);

// -------------------------------------------------------------------------
// Config helpers
// -------------------------------------------------------------------------

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

// -------------------------------------------------------------------------
// Container helper
// -------------------------------------------------------------------------

if (!function_exists('app')) {
    /**
     * Resolve a class from the service container, or return the container itself.
     *
     *   app()                           // returns the Container
     *   app(\App\Services\Mailer::class) // returns an instance
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $container = \Core\Container\Container::getInstance();
        if ($abstract === null) return $container;
        return $container->make($abstract, $parameters);
    }
}

// -------------------------------------------------------------------------
// HTTP helpers
// -------------------------------------------------------------------------

if (!function_exists('abort')) {
    /**
     * Throw an HTTP exception with the given status code.
     *
     *   abort(404);
     *   abort(403, 'You do not have permission.');
     */
    function abort(int $code, string $message = ''): never
    {
        throw new \Core\Http\HttpException($code, $message);
    }
}

if (!function_exists('back')) {
    /**
     * Redirect to the previous URL (HTTP_REFERER), or '/' if absent.
     *
     *   back();
     *   back(301);
     */
    function back(int $status = 302): never
    {
        $url = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $url, true, $status);
        exit;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }
}

if (!function_exists('url')) {
    /**
     * Generate a URL for the application.
     *
     *   url()           // https://example.com
     *   url('/users')   // https://example.com/users
     */
    function url(?string $path = null): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host;

        if ($path === null) return $base;

        return $base . '/' . ltrim($path, '/');
    }
}

// -------------------------------------------------------------------------
// Date / time helper
// -------------------------------------------------------------------------

if (!function_exists('now')) {
    /**
     * Return the current date/time as an immutable DateTime.
     *
     *   now()->format('Y-m-d')
     *   now('America/New_York')
     */
    function now(?string $timezone = null): \DateTimeImmutable
    {
        $tz = $timezone !== null ? new \DateTimeZone($timezone) : null;
        return new \DateTimeImmutable('now', $tz);
    }
}

// -------------------------------------------------------------------------
// Form helpers (repopulate fields after redirect-on-failure)
// -------------------------------------------------------------------------

if (!function_exists('old')) {
    /**
     * Retrieve a value that was flashed to the session under '__old'.
     *
     *   <!-- Blade / PHP view -->
     *   <input name="email" value="<?= old('email') ?>">
     *
     * Flash old input before redirecting:
     *   Session::flash('__old', $request->all());
     *   back();
     */
    function old(string $key, mixed $default = null): mixed
    {
        if (!class_exists(\Core\Session\Session::class)) return $default;
        $all = \Core\Session\Session::getInstance()->get('__old', []);
        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }
}

if (!function_exists('errors')) {
    /**
     * Retrieve validation errors flashed to the session.
     *
     *   errors()         // all errors: ['field' => ['msg1']]
     *   errors('email')  // first error for a field, or null
     *
     * Flash errors before redirecting:
     *   Session::flash('__errors', $validator->errors());
     *   back();
     */
    function errors(?string $field = null): mixed
    {
        if (!class_exists(\Core\Session\Session::class)) {
            return $field !== null ? null : [];
        }
        $all = \Core\Session\Session::getInstance()->get('__errors', []);
        if (!is_array($all)) return $field !== null ? null : [];
        if ($field === null) return $all;
        $fieldErrors = $all[$field] ?? null;
        if (is_array($fieldErrors)) return $fieldErrors[0] ?? null;
        return $fieldErrors;
    }
}

// -------------------------------------------------------------------------
// String / array fluent access (shortcuts to Str and Arr statics)
// -------------------------------------------------------------------------

if (!function_exists('str')) {
    /**
     * Access Str helper methods via a short alias.
     *
     *   str('hello world')->slug()   // "hello-world"
     *   str()->uuid()                // "4b3f…"
     */
    function str(?string $value = null): \Core\StrProxy
    {
        return new \Core\StrProxy($value);
    }
}

if (!function_exists('arr')) {
    /**
     * Access Arr helper methods via a short alias.
     *
     *   arr(['a.b' => 1])->undot()   // ['a' => ['b' => 1]]
     */
    function arr(array $array): \Core\ArrProxy
    {
        return new \Core\ArrProxy($array);
    }
}
