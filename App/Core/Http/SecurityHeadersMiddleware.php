<?php

declare(strict_types=1);

namespace Core\Http;

use Pecee\Http\Request;

/**
 * SecurityHeadersMiddleware — sets defensive HTTP response headers on every request.
 *
 * Wire up in public/index.php alongside CsrfMiddleware:
 *
 *   SimpleRouter::group([
 *       'middleware' => [SecurityHeadersMiddleware::class, CsrfMiddleware::class],
 *   ], function () use ($blade) { ... });
 *
 * Content-Security-Policy is intentionally omitted from the default set because
 * a wrong CSP silently breaks the application. Add your own via $extraHeaders:
 *
 *   SecurityHeadersMiddleware::$extraHeaders['Content-Security-Policy'] =
 *       "default-src 'self'; script-src 'self'; style-src 'self'; " .
 *       "img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self';";
 */
class SecurityHeadersMiddleware implements \Pecee\Http\Middleware\IMiddleware
{
    /**
     * Override or extend the default header set from bootstrap.php / a service provider.
     * Values set here are merged on top of the defaults and can replace them by key.
     *
     * @var array<string,string>
     */
    public static array $extraHeaders = [];

    private const DEFAULTS = [
        // Prevent the page from being embedded in an iframe (clickjacking)
        'X-Frame-Options'           => 'SAMEORIGIN',
        // Stop browsers from MIME-sniffing the content type
        'X-Content-Type-Options'    => 'nosniff',
        // Tell browsers to use HTTPS for 1 year (enable once HTTPS is confirmed)
        // 'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        // Control how much referrer info is sent with requests
        'Referrer-Policy'           => 'strict-origin-when-cross-origin',
        // Restrict access to powerful browser features
        'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=(), payment=()',
        // Remove the X-Powered-By header added by PHP (belt-and-suspenders — php.ini is better)
        'X-Powered-By'              => '',
    ];

    public function handle(Request $request): void
    {
        $headers = array_merge(self::DEFAULTS, self::$extraHeaders);

        foreach ($headers as $name => $value) {
            if ($value === '') {
                header_remove($name);
            } else {
                header("$name: $value");
            }
        }
    }
}
