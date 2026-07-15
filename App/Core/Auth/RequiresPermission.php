<?php

declare(strict_types=1);

namespace Core\Auth;

use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;

/**
 * Route middleware that enforces RBAC permission checks.
 *
 * Usage (single permission):
 *
 *   SimpleRouter::delete('/posts/{id}', fn($id) => ...)
 *       ->addMiddleware(new \Core\Auth\RequiresPermission('posts:delete'));
 *
 * Usage (multiple — user must hold ALL listed permissions):
 *
 *   SimpleRouter::group([
 *       'middleware' => new \Core\Auth\RequiresPermission('orders:read', 'reports:read'),
 *   ], function () { ... });
 *
 * Returns 401 when no valid JWT is present, 403 when authenticated but lacking permission.
 */
class RequiresPermission implements IMiddleware
{
    /** @var list<string> */
    private array $permissions;

    public function __construct(string ...$permissions)
    {
        $this->permissions = $permissions;
    }

    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            $this->abort(401, 'Unauthorized', 'A valid Bearer token is required.');
        }

        foreach ($this->permissions as $permission) {
            if (!RBAC::can($permission)) {
                $this->abort(403, 'Forbidden', "Missing required permission: {$permission}");
            }
        }
    }

    private function abort(int $code, string $error, string $message): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $error, 'message' => $message]);
        exit;
    }
}
