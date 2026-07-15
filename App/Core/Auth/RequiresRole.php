<?php

declare(strict_types=1);

namespace Core\Auth;

use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;

/**
 * Route middleware that enforces RBAC role checks.
 *
 * Usage (single role):
 *
 *   SimpleRouter::get('/admin', fn() => ...)
 *       ->addMiddleware(new \Core\Auth\RequiresRole('admin'));
 *
 * Usage (any of several roles — user must hold AT LEAST ONE):
 *
 *   SimpleRouter::group([
 *       'middleware' => new \Core\Auth\RequiresRole('admin', 'moderator'),
 *   ], function () { ... });
 *
 * Returns 401 when no valid JWT is present, 403 when authenticated but lacking the role.
 */
class RequiresRole implements IMiddleware
{
    /** @var list<string> */
    private array $roles;
    private bool $requireAll;

    /**
     * @param string ...$roles    Role names to check.
     */
    public function __construct(string ...$roles)
    {
        $this->roles      = $roles;
        $this->requireAll = false;
    }

    /** Require the user to hold ALL listed roles (default: any one suffices). */
    public function all(): static
    {
        $this->requireAll = true;
        return $this;
    }

    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            $this->abort(401, 'Unauthorized', 'A valid Bearer token is required.');
        }

        if ($this->requireAll) {
            $check = count(array_filter($this->roles, fn($r) => RBAC::is($r))) === count($this->roles);
        } else {
            $check = count(array_filter($this->roles, fn($r) => RBAC::is($r))) > 0;
        }

        if (!$check) {
            $list = implode(', ', $this->roles);
            $this->abort(403, 'Forbidden', "Required role(s): {$list}");
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
