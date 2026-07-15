<?php

declare(strict_types=1);

namespace Core\Http\Middleware;

use Core\Http\Request;

/**
 * Simple in-memory rate limiter (per process / per request).
 *
 * For production use, swap the APCu counter for a Redis or database backend.
 *
 * Config:
 *   THROTTLE_MAX_ATTEMPTS=60      (requests per window)
 *   THROTTLE_DECAY_SECONDS=60     (window duration in seconds)
 *
 * Constructor overrides:
 *   new ThrottleMiddleware(maxAttempts: 10, decaySeconds: 30)
 */
class ThrottleMiddleware implements Middleware
{
    private int $maxAttempts;
    private int $decaySeconds;

    public function __construct(int $maxAttempts = 0, int $decaySeconds = 0)
    {
        $this->maxAttempts  = $maxAttempts  ?: (int)($_ENV['THROTTLE_MAX_ATTEMPTS']  ?? 60);
        $this->decaySeconds = $decaySeconds ?: (int)($_ENV['THROTTLE_DECAY_SECONDS'] ?? 60);
    }

    public function handle(Request $request, callable $next): mixed
    {
        $key = 'throttle:' . $request->ip();

        if (function_exists('apcu_fetch')) {
            [$count, $reset] = $this->apcuIncrement($key);
        } else {
            [$count, $reset] = $this->staticIncrement($key);
        }

        header("X-RateLimit-Limit: {$this->maxAttempts}");
        header("X-RateLimit-Remaining: " . max(0, $this->maxAttempts - $count));
        header("X-RateLimit-Reset: {$reset}");

        if ($count > $this->maxAttempts) {
            http_response_code(429);
            header('Content-Type: application/json');
            header("Retry-After: {$this->decaySeconds}");
            echo json_encode(['error' => 'Too Many Requests']);
            exit;
        }

        return $next($request);
    }

    /** @return array{int,int} [count, resetTimestamp] */
    private function apcuIncrement(string $key): array
    {
        $resetKey = $key . ':reset';
        $reset    = (int)apcu_fetch($resetKey);

        if ($reset === 0 || time() > $reset) {
            $reset = time() + $this->decaySeconds;
            apcu_store($key, 0, $this->decaySeconds);
            apcu_store($resetKey, $reset, $this->decaySeconds);
        }

        $count = (int)apcu_inc($key, 1);
        return [$count, $reset];
    }

    /** @var array<string,array{int,int}> */
    private static array $staticCounters = [];

    /** @return array{int,int} [count, resetTimestamp] */
    private function staticIncrement(string $key): array
    {
        [$count, $reset] = self::$staticCounters[$key] ?? [0, time() + $this->decaySeconds];

        if (time() > $reset) {
            $count = 0;
            $reset = time() + $this->decaySeconds;
        }

        $count++;
        self::$staticCounters[$key] = [$count, $reset];
        return [$count, $reset];
    }
}
