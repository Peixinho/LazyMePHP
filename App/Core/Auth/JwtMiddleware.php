<?php

declare(strict_types=1);

/**
 * LazyMePHP JwtMiddleware
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Auth;

use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;

/**
 * Rejects requests without a valid Bearer JWT with 401.
 *
 * Attach to any route that requires authentication:
 *
 *   SimpleRouter::post('/graphql', fn() => Endpoint::handle($tables))
 *       ->addMiddleware(\Core\Auth\JwtMiddleware::class);
 *
 *   SimpleRouter::group(['middleware' => \Core\Auth\JwtMiddleware::class], function () {
 *       SimpleRouter::get('/admin', fn() => ...);
 *   });
 */
class JwtMiddleware implements IMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'error'   => 'Unauthorized',
                'message' => 'A valid Bearer token is required.',
            ]);
            exit;
        }
    }
}
