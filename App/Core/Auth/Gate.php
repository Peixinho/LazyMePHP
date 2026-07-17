<?php

declare(strict_types=1);

/**
 * LazyMePHP Gate
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Auth;

use Core\CrudController;
use Core\Model;

/**
 * Single enforcement point for CrudController::requiredRoles*()/authorizeRecord().
 *
 * Both Core\GraphQL\SchemaBuilder and Core\AutoRouter call this — a table's
 * access rules are declared once, on its controller, and apply identically
 * to GraphQL and the auto-wired web CRUD routes. There is no separate
 * "web roles" vs "API roles" concept; if the two surfaces need to differ,
 * that's what App/Routes/{table}.php (replacing the auto-wired routes
 * entirely) or a hand-rolled resolver override is for — not a second config.
 */
class Gate
{
    /** @throws AuthorizationException When unauthenticated or missing a required role. */
    public static function checkRoles(array $requiredRoles, string $context): void
    {
        if (empty($requiredRoles)) {
            return;
        }

        if (RBAC::currentUserId() === null) {
            throw new AuthorizationException("Unauthorized: $context requires authentication.", 401);
        }

        foreach ($requiredRoles as $role) {
            if (RBAC::is($role)) {
                return;
            }
        }

        throw new AuthorizationException(
            "Forbidden: $context requires one of these roles: " . implode(', ', $requiredRoles),
            403
        );
    }

    /** @throws AuthorizationException When authorizeRecord() returns false. */
    public static function checkRecord(CrudController $controller, string $operation, Model $record, string $context): void
    {
        if (!$controller->authorizeRecord($operation, $record)) {
            throw new AuthorizationException("Forbidden: you may not $operation this $context record", 403);
        }
    }
}
