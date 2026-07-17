<?php

declare(strict_types=1);

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Http;

use Pecee\Http\Request;

/**
 * CorsMiddleware — exact-origin allowlist for cross-origin API clients.
 *
 * Wire up in Kernel::loadRoutes() alongside SecurityHeadersMiddleware/CsrfMiddleware
 * (already done by default). Config (.env):
 *
 *   APP_CORS_ORIGIN=http://localhost:5173
 *
 * Unset/empty means no CORS headers are sent at all — same-origin only, matching
 * the framework's default of everything (Blade views, Batman, the API) living on
 * one origin. `/graphql` and `/auth/*` are the routes meant to be called
 * cross-origin (they're Bearer-token authenticated, not cookie/session based —
 * see Core\Security\CsrfMiddleware's exemption for the same two prefixes).
 *
 * Wildcards are rejected outright, matching docs/docs/security.md ("Exact-origin
 * allowlist via APP_CORS_ORIGIN; wildcard blocked") — reflecting every origin back
 * would defeat the point of an allowlist for token-authenticated endpoints.
 *
 * This is unrelated to Core\Http\Middleware\CorsMiddleware, an example middleware
 * for the standalone Core\Http\Middleware\Pipeline utility (see MiddlewarePipelineTest) —
 * that one is never invoked by the real request flow. This is the one that runs.
 *
 * IMPORTANT: Pecee's router only runs a route's middleware once it has matched
 * both the path AND the HTTP method (Router::routeRequest() `continue`s past a
 * method mismatch before ever calling loadMiddleware() — see Route/RouteUrl.php).
 * A route registered only as SimpleRouter::post(...) never matches an OPTIONS
 * preflight, so this middleware never runs for it and the browser sees no
 * Access-Control-Allow-Origin header at all. Every cross-origin-callable route
 * (/graphql, each /auth/* route) must also explicitly register OPTIONS —
 * see LazyMePHP::boot() and AuthEndpoint::register().
 */
class CorsMiddleware implements \Pecee\Http\Middleware\IMiddleware
{
    public function handle(Request $request): void
    {
        $isPreflight   = strtoupper($request->getMethod() ?? '') === 'OPTIONS';
        $allowedOrigin = trim($_ENV['APP_CORS_ORIGIN'] ?? '');
        $requestOrigin = (string) $request->getHeader('origin');

        if ($allowedOrigin !== '' && $allowedOrigin !== '*' && $requestOrigin === $allowedOrigin) {
            header("Access-Control-Allow-Origin: {$allowedOrigin}");
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 86400');
        }

        // An OPTIONS request only ever exists to ask "can I do this?" — never run
        // route business logic for it, matched origin or not (an unmatched origin
        // just gets a plain 204 with no CORS headers, which is what makes the
        // browser reject the follow-up request — the door stays effectively locked).
        if ($isPreflight) {
            http_response_code(204);
            exit;
        }
    }
}
