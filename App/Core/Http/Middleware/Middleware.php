<?php

declare(strict_types=1);

namespace Core\Http\Middleware;

use Core\Http\Request;

/**
 * A middleware receives a Request and a $next callable.
 * Call $next($request) to pass through, or return early to short-circuit.
 *
 *   class AuthMiddleware implements Middleware
 *   {
 *       public function handle(Request $request, callable $next): mixed
 *       {
 *           if (!$request->bearerToken()) {
 *               http_response_code(401);
 *               return ['error' => 'Unauthenticated'];
 *           }
 *           return $next($request);
 *       }
 *   }
 */
interface Middleware
{
    public function handle(Request $request, callable $next): mixed;
}
