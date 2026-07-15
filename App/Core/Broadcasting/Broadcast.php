<?php

declare(strict_types=1);

namespace Core\Broadcasting;

/**
 * Broadcasting facade for Server-Sent Events.
 *
 *   // Publish
 *   Broadcast::channel('orders')->send('order.created', ['id' => 42, 'total' => 99.00]);
 *   Broadcast::toAll()->send('maintenance', ['starts' => '2026-08-01T03:00:00Z']);
 *
 *   // Stream (from a dedicated route)
 *   Broadcast::channel('orders')->listen();
 */
class Broadcast
{
    public static function channel(string $name): BroadcastChannel
    {
        return new BroadcastChannel($name);
    }

    /** Broadcast to the special 'global' channel. */
    public static function toAll(): BroadcastChannel
    {
        return new BroadcastChannel('global');
    }

    /** Broadcast to a user-specific channel (user:{id}). */
    public static function toUser(int $userId): BroadcastChannel
    {
        return new BroadcastChannel("user:{$userId}");
    }
}
