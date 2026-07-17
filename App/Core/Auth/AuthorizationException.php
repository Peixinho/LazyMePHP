<?php

declare(strict_types=1);

namespace Core\Auth;

/**
 * Thrown by Gate::checkRoles()/checkRecord() — carries an HTTP status (401/403)
 * so each transport (GraphQL resolver, web route) can render it appropriately
 * without duplicating the "which status for which failure" decision.
 */
class AuthorizationException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }
}
