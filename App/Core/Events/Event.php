<?php

declare(strict_types=1);

namespace Core\Events;

/**
 * General-purpose event dispatcher (separate from ModelEvents which handles model lifecycle).
 *
 *   // Register a listener
 *   Event::listen('user.registered', fn($payload) => Mail::dispatch(new WelcomeMail($payload['email'])));
 *
 *   // Dispatch
 *   Event::dispatch('user.registered', ['email' => 'alice@example.com', 'name' => 'Alice']);
 *
 *   // Object-style events (class name is the event key)
 *   Event::dispatch(new UserRegistered($user));
 *   Event::listen(UserRegistered::class, fn(UserRegistered $e) => ...);
 *
 *   // Wildcard listeners
 *   Event::listen('user.*', fn($event, $payload) => Log::debug("user event: $event"));
 */
class Event
{
    /** @var array<string, list<callable>> */
    private static array $listeners = [];

    /** Register a listener for an event name or object class. */
    public static function listen(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    /**
     * Dispatch an event.
     *
     * @param string|object $event  Event name or an object (its class name is used as event key).
     * @param mixed         $payload Arbitrary data passed to listeners. Ignored when $event is an object.
     */
    public static function dispatch(string|object $event, mixed $payload = null): void
    {
        if (is_object($event)) {
            $key     = get_class($event);
            $payload = $event;
        } else {
            $key = $event;
        }

        // Exact listeners
        foreach (self::$listeners[$key] ?? [] as $listener) {
            $listener($payload, $key);
        }

        // Wildcard listeners (e.g. 'user.*' matches 'user.registered')
        foreach (self::$listeners as $pattern => $listeners) {
            if (str_contains($pattern, '*') && self::matches($pattern, $key)) {
                foreach ($listeners as $listener) {
                    $listener($payload, $key);
                }
            }
        }

        // Profiler span
        if (class_exists(\Core\Debug\Profiler::class)) {
            \Core\Debug\Profiler::start('event', $key);
            \Core\Debug\Profiler::stop();
        }
    }

    /** Remove all listeners for the given event, or all events. */
    public static function forget(?string $event = null): void
    {
        if ($event === null) {
            self::$listeners = [];
        } else {
            unset(self::$listeners[$event]);
        }
    }

    public static function hasListeners(string $event): bool
    {
        return !empty(self::$listeners[$event]);
    }

    /** Return all registered listeners. */
    public static function all(): array
    {
        return self::$listeners;
    }

    private static function matches(string $pattern, string $key): bool
    {
        $regex = '#^' . str_replace('\*', '[^.]*', preg_quote($pattern, '#')) . '$#';
        return (bool)preg_match($regex, $key);
    }
}
