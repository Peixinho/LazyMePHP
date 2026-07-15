<?php

declare(strict_types=1);

namespace Core\Http\Middleware;

use Core\Http\Request;

/**
 * Adds CORS headers and handles preflight OPTIONS requests.
 *
 * Config (via $_ENV / .env):
 *   CORS_ORIGINS=*                (comma-separated or * for all)
 *   CORS_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
 *   CORS_HEADERS=Content-Type,Authorization,X-Requested-With
 *   CORS_MAX_AGE=86400
 *   CORS_CREDENTIALS=false
 */
class CorsMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $origins     = $_ENV['CORS_ORIGINS']     ?? '*';
        $methods     = $_ENV['CORS_METHODS']     ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS';
        $headers     = $_ENV['CORS_HEADERS']     ?? 'Content-Type,Authorization,X-Requested-With';
        $maxAge      = $_ENV['CORS_MAX_AGE']     ?? '86400';
        $credentials = $_ENV['CORS_CREDENTIALS'] ?? 'false';

        $origin = (string)$request->header('origin', '');

        if ($origins === '*') {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== '') {
            $allowed = array_map('trim', explode(',', $origins));
            if (in_array($origin, $allowed, true)) {
                header("Access-Control-Allow-Origin: {$origin}");
                header('Vary: Origin');
            }
        }

        header("Access-Control-Allow-Methods: {$methods}");
        header("Access-Control-Allow-Headers: {$headers}");
        header("Access-Control-Max-Age: {$maxAge}");

        if ($credentials === 'true') {
            header('Access-Control-Allow-Credentials: true');
        }

        if ($request->isMethod('OPTIONS')) {
            http_response_code(204);
            exit;
        }

        return $next($request);
    }
}
