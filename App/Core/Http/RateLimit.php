<?php

declare(strict_types=1);

namespace Core\Http;

use Core\Cache\Cache;
use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;

/**
 * General-purpose rate limiting middleware.
 *
 *   // 60 requests per minute per IP
 *   $router->post('/api/submit', [Controller::class, 'handle'])
 *          ->addMiddleware(new RateLimit(60, 60));
 *
 *   // 5 requests per minute keyed by user ID
 *   ->addMiddleware(new RateLimit(5, 60, fn() => Auth::id() ?? 'anon'))
 */
class RateLimit implements IMiddleware
{
    private int $maxRequests;
    private int $windowSeconds;
    private ?\Closure $keyResolver;

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60, ?\Closure $keyResolver = null)
    {
        $this->maxRequests   = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->keyResolver   = $keyResolver;
    }

    public function handle(Request $request): void
    {
        $key   = $this->resolveKey($request);
        $count = Cache::increment($key, 1, $this->windowSeconds);

        if ($count > $this->maxRequests) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . $this->windowSeconds);
            header('X-RateLimit-Limit: ' . $this->maxRequests);
            header('X-RateLimit-Remaining: 0');
            echo json_encode(['error' => 'Too Many Requests', 'retry_after' => $this->windowSeconds]);
            exit;
        }

        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . max(0, $this->maxRequests - $count));
    }

    private function resolveKey(Request $request): string
    {
        if ($this->keyResolver !== null) {
            return 'rl:' . ($this->keyResolver)();
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ip = explode(',', $ip)[0];
        return 'rl:ip:' . trim($ip) . ':' . $request->getUrl()->getPath();
    }
}
