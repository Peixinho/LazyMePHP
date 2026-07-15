<?php

declare(strict_types=1);

namespace Core\Auth;

use Core\LazyMePHP;

/**
 * Role-Based Access Control.
 *
 * Requires three system tables (created via `php lazymephp migrate`):
 *   __AUTH_ROLES             — (id, name, description)
 *   __AUTH_ROLE_PERMISSIONS  — (role_id, permission)
 *   __AUTH_USER_ROLES        — (user_id, role_id)
 *
 * Typical usage:
 *
 *   // Setup
 *   RBAC::createRole('admin', 'Full access');
 *   RBAC::grantPermission('admin', 'posts:delete');
 *   RBAC::assignRole($userId, 'admin');
 *
 *   // Enforcement (uses Auth::id() internally)
 *   if (RBAC::can('posts:delete')) { ... }
 *   if (RBAC::is('admin')) { ... }
 */
class RBAC
{
    /** @var array<string, list<string>> roles → permissions cache, keyed by user_id */
    private static array $cache = [];

    // -------------------------------------------------------------------------
    // Query-time checks (uses current Auth::id())
    // -------------------------------------------------------------------------

    /** True when the currently authenticated user has the given permission. */
    public static function can(string $permission): bool
    {
        $userId = Auth::id();
        if ($userId === null) return false;
        return in_array($permission, self::permissionsFor($userId), true);
    }

    /** True when the currently authenticated user has the given role. */
    public static function is(string $role): bool
    {
        $userId = Auth::id();
        if ($userId === null) return false;
        return in_array($role, self::rolesFor($userId), true);
    }

    // -------------------------------------------------------------------------
    // Role management
    // -------------------------------------------------------------------------

    public static function createRole(string $name, string $description = ''): void
    {
        $db = LazyMePHP::DB_CONNECTION();
        $db->query(
            'INSERT INTO __AUTH_ROLES (name, description) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM __AUTH_ROLES WHERE name = ?)',
            [$name, $description, $name]
        );
    }

    public static function deleteRole(string $name): void
    {
        $db = LazyMePHP::DB_CONNECTION();
        $r  = $db->query('SELECT id FROM __AUTH_ROLES WHERE name = ?', [$name]);
        $row = $r->fetchArray();
        if (!$row) return;

        $roleId = $row['id'];
        $db->query('DELETE FROM __AUTH_ROLE_PERMISSIONS WHERE role_id = ?', [$roleId]);
        $db->query('DELETE FROM __AUTH_USER_ROLES WHERE role_id = ?', [$roleId]);
        $db->query('DELETE FROM __AUTH_ROLES WHERE id = ?', [$roleId]);
        self::$cache = [];
    }

    // -------------------------------------------------------------------------
    // Permission management
    // -------------------------------------------------------------------------

    public static function grantPermission(string $roleName, string $permission): void
    {
        $roleId = self::roleId($roleName);
        if ($roleId === null) {
            throw new \InvalidArgumentException("Role '$roleName' does not exist.");
        }
        $db = LazyMePHP::DB_CONNECTION();
        $db->query(
            'INSERT INTO __AUTH_ROLE_PERMISSIONS (role_id, permission) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM __AUTH_ROLE_PERMISSIONS WHERE role_id = ? AND permission = ?)',
            [$roleId, $permission, $roleId, $permission]
        );
        self::$cache = [];
    }

    public static function revokePermission(string $roleName, string $permission): void
    {
        $roleId = self::roleId($roleName);
        if ($roleId === null) return;
        LazyMePHP::DB_CONNECTION()->query(
            'DELETE FROM __AUTH_ROLE_PERMISSIONS WHERE role_id = ? AND permission = ?',
            [$roleId, $permission]
        );
        self::$cache = [];
    }

    // -------------------------------------------------------------------------
    // User ↔ Role assignment
    // -------------------------------------------------------------------------

    public static function assignRole(mixed $userId, string $roleName): void
    {
        $roleId = self::roleId($roleName);
        if ($roleId === null) {
            throw new \InvalidArgumentException("Role '$roleName' does not exist.");
        }
        $db = LazyMePHP::DB_CONNECTION();
        $db->query(
            'INSERT INTO __AUTH_USER_ROLES (user_id, role_id) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM __AUTH_USER_ROLES WHERE user_id = ? AND role_id = ?)',
            [(string)$userId, $roleId, (string)$userId, $roleId]
        );
        unset(self::$cache[(string)$userId]);
    }

    public static function removeRole(mixed $userId, string $roleName): void
    {
        $roleId = self::roleId($roleName);
        if ($roleId === null) return;
        LazyMePHP::DB_CONNECTION()->query(
            'DELETE FROM __AUTH_USER_ROLES WHERE user_id = ? AND role_id = ?',
            [(string)$userId, $roleId]
        );
        unset(self::$cache[(string)$userId]);
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /** @return list<string> role names for the given user ID */
    public static function rolesFor(mixed $userId): array
    {
        self::loadFor($userId);
        return array_keys(self::$cache[(string)$userId] ?? []);
    }

    /** @return list<string> all permissions for the given user ID (across all roles) */
    public static function permissionsFor(mixed $userId): array
    {
        self::loadFor($userId);
        $perms = [];
        foreach (self::$cache[(string)$userId] ?? [] as $rolePerms) {
            array_push($perms, ...$rolePerms);
        }
        return array_values(array_unique($perms));
    }

    /** List all defined roles. @return list<array{id:int,name:string,description:string}> */
    public static function allRoles(): array
    {
        $r    = LazyMePHP::DB_CONNECTION()->query('SELECT id, name, description FROM __AUTH_ROLES ORDER BY name');
        $rows = [];
        while ($row = $r->fetchArray()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Flush in-process permission cache — call after bulk changes in tests. */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function roleId(string $name): ?int
    {
        $r   = LazyMePHP::DB_CONNECTION()->query('SELECT id FROM __AUTH_ROLES WHERE name = ?', [$name]);
        $row = $r->fetchArray();
        return $row ? (int)$row['id'] : null;
    }

    private static function loadFor(mixed $userId): void
    {
        $key = (string)$userId;
        if (isset(self::$cache[$key])) return;

        self::$cache[$key] = [];

        try {
            $db = LazyMePHP::DB_CONNECTION();
            $r  = $db->query(
                'SELECT r.name AS role_name, p.permission
                 FROM __AUTH_USER_ROLES ur
                 JOIN __AUTH_ROLES r ON r.id = ur.role_id
                 LEFT JOIN __AUTH_ROLE_PERMISSIONS p ON p.role_id = r.id
                 WHERE ur.user_id = ?',
                [$key]
            );
            while ($row = $r->fetchArray()) {
                $role = $row['role_name'];
                if (!isset(self::$cache[$key][$role])) {
                    self::$cache[$key][$role] = [];
                }
                if ($row['permission'] !== null) {
                    self::$cache[$key][$role][] = $row['permission'];
                }
            }
        } catch (\Throwable) {
            // Tables may not exist yet — treat as no roles
        }
    }
}
