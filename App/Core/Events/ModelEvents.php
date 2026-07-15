<?php

declare(strict_types=1);

namespace Core\Events;

/**
 * Lightweight model lifecycle event bus.
 *
 * Events fired (in order for a save):
 *   saving → creating|updating → created|updated → saved
 *
 * Events fired for a delete:
 *   deleting → deleted
 *
 * Register listeners:
 *   ModelEvents::listen('users', 'creating', fn($model) => ...);
 *
 * Return false from a 'creating' or 'updating' or 'deleting' listener to cancel the operation.
 *
 * Use Model::observe('users', new UserObserver()) to register an entire observer class at once.
 * Observer methods: creating, created, updating, updated, deleting, deleted, saving, saved.
 */
class ModelEvents
{
    /** @var array<string, array<string, list<callable>>> table → event → listeners */
    private static array $listeners = [];

    public static function listen(string $table, string $event, callable $listener): void
    {
        self::$listeners[$table][$event][] = $listener;
    }

    /**
     * Dispatch an event. Returns false if any listener returns false (cancellable events).
     */
    public static function fire(string $table, string $event, mixed $model): bool
    {
        foreach (self::$listeners[$table][$event] ?? [] as $listener) {
            if ($listener($model) === false) {
                return false;
            }
        }
        // Also check wildcard table listeners
        foreach (self::$listeners['*'][$event] ?? [] as $listener) {
            if ($listener($model) === false) {
                return false;
            }
        }
        return true;
    }

    /** Register all methods of an observer object for a table. */
    public static function registerObserver(string $table, object $observer): void
    {
        $events = ['creating', 'created', 'updating', 'updated', 'deleting', 'deleted', 'saving', 'saved'];
        foreach ($events as $event) {
            if (method_exists($observer, $event)) {
                self::listen($table, $event, [$observer, $event]);
            }
        }
    }

    /** Clear all listeners — intended for tests. */
    public static function clearAll(): void
    {
        self::$listeners = [];
    }

    /** Clear listeners for a specific table. */
    public static function clear(string $table): void
    {
        unset(self::$listeners[$table]);
    }
}
