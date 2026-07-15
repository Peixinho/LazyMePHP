<?php

declare(strict_types=1);

namespace Core\Http\Middleware;

use Core\Http\Request;

/**
 * Runs a Request through an ordered stack of middleware and a final handler.
 *
 *   $response = Pipeline::send(Request::capture())
 *       ->through([
 *           new CorsMiddleware(),
 *           new AuthMiddleware(),
 *           new LogMiddleware(),
 *       ])
 *       ->then(fn(Request $req) => $controller->handle($req));
 *
 * Middleware can be:
 *   - An object implementing Middleware (preferred)
 *   - A class name string (auto-instantiated via new $class())
 *   - A callable fn(Request $req, callable $next): mixed
 */
class Pipeline
{
    private Request $request;
    /** @var list<Middleware|callable|string> */
    private array $stages = [];

    private function __construct() {}

    public static function send(Request $request): static
    {
        $pipe          = new static();
        $pipe->request = $request;
        return $pipe;
    }

    /** @param list<Middleware|callable|string> $middlewares */
    public function through(array $middlewares): static
    {
        $this->stages = $middlewares;
        return $this;
    }

    public function then(callable $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->stages),
            fn(callable $next, mixed $middleware) => function (Request $req) use ($middleware, $next): mixed {
                $mw = is_string($middleware) ? new $middleware() : $middleware;
                if ($mw instanceof Middleware) {
                    return $mw->handle($req, $next);
                }
                return $mw($req, $next);
            },
            $destination
        );

        return $pipeline($this->request);
    }
}
