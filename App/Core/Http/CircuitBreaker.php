<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Circuit breaker for unreliable external services.
 *
 * States:
 *   CLOSED    — normal operation, failures counted
 *   OPEN      — too many failures; calls rejected immediately
 *   HALF_OPEN — one test request allowed; success closes, failure re-opens
 *
 *   $cb = CircuitBreaker::for('stripe-api')
 *             ->threshold(5)          // open after 5 failures
 *             ->timeout(30)           // stay open for 30 seconds
 *             ->onOpen(fn() => ...);  // optional callback when opened
 *
 *   $result = $cb->call(fn() => Http::post($url, $payload));
 */
class CircuitBreaker
{
    private const CLOSED    = 'closed';
    private const OPEN      = 'open';
    private const HALF_OPEN = 'half_open';

    /** @var array<string, CircuitBreaker> */
    private static array $instances = [];

    private string $state       = self::CLOSED;
    private int    $failures    = 0;
    private int    $threshold   = 5;
    private int    $timeoutSecs = 60;
    private float  $openedAt    = 0.0;
    /** @var (\Closure(string, int): void)|null */
    private ?\Closure $onOpenCallback = null;

    private function __construct(private readonly string $service) {}

    public static function for(string $service): self
    {
        return self::$instances[$service] ??= new self($service);
    }

    public static function reset(string $service): void
    {
        unset(self::$instances[$service]);
    }

    public static function resetAll(): void
    {
        self::$instances = [];
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public function threshold(int $failures): static
    {
        $this->threshold = $failures;
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeoutSecs = $seconds;
        return $this;
    }

    public function onOpen(\Closure $callback): static
    {
        $this->onOpenCallback = $callback;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Execute $callback through the circuit breaker.
     *
     * @throws CircuitOpenException if the circuit is OPEN
     * @throws \Throwable if the callback itself throws
     */
    public function call(callable $callback): mixed
    {
        $this->checkHalfOpen();

        if ($this->state === self::OPEN) {
            throw new CircuitOpenException(
                "Circuit breaker [{$this->service}] is OPEN — service unavailable.",
                $this->timeoutSecs - (int)(microtime(true) - $this->openedAt),
            );
        }

        try {
            $result       = $callback();
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }

    public function isOpen(): bool      { return $this->state === self::OPEN; }
    public function isClosed(): bool    { return $this->state === self::CLOSED; }
    public function isHalfOpen(): bool  { return $this->state === self::HALF_OPEN; }
    public function getFailures(): int  { return $this->failures; }
    public function getState(): string  { return $this->state; }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function checkHalfOpen(): void
    {
        if ($this->state === self::OPEN
            && (microtime(true) - $this->openedAt) >= $this->timeoutSecs
        ) {
            $this->state = self::HALF_OPEN;
        }
    }

    private function onSuccess(): void
    {
        $this->failures = 0;
        $this->state    = self::CLOSED;
    }

    private function onFailure(): void
    {
        $this->failures++;

        if ($this->state === self::HALF_OPEN || $this->failures >= $this->threshold) {
            $this->state    = self::OPEN;
            $this->openedAt = microtime(true);
            if ($this->onOpenCallback !== null) {
                ($this->onOpenCallback)($this->service, $this->failures);
            }
        }
    }
}

class CircuitOpenException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfterSeconds = 0)
    {
        parent::__construct($message);
    }
}
