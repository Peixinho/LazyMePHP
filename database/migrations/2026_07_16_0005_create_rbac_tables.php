<?php

// Used by Core\Auth\RBAC — role/permission assignment.
// Schema documented at the top of App/Core/Auth/RBAC.php:
//   __AUTH_ROLES            (id, name, description)
//   __AUTH_ROLE_PERMISSIONS (role_id, permission)
//   __AUTH_USER_ROLES       (user_id, role_id)

return [
    'up' => function ($db): void {
        $type = strtolower(\Core\LazyMePHP::DB_TYPE() ?? 'sqlite');

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__AUTH_ROLES` (
                `id`          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name`        VARCHAR(100) NOT NULL UNIQUE,
                `description` VARCHAR(255) NOT NULL DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__AUTH_ROLES')
                CREATE TABLE [__AUTH_ROLES] (
                    [id]          INT IDENTITY(1,1) PRIMARY KEY,
                    [name]        NVARCHAR(100) NOT NULL UNIQUE,
                    [description] NVARCHAR(255) NOT NULL DEFAULT ''
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__AUTH_ROLES\" (
                \"id\"          INTEGER PRIMARY KEY AUTOINCREMENT,
                \"name\"        TEXT NOT NULL UNIQUE,
                \"description\" TEXT NOT NULL DEFAULT ''
            )",
        });

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__AUTH_ROLE_PERMISSIONS` (
                `role_id`    INT          NOT NULL,
                `permission` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`role_id`, `permission`),
                FOREIGN KEY (`role_id`) REFERENCES `__AUTH_ROLES`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__AUTH_ROLE_PERMISSIONS')
                CREATE TABLE [__AUTH_ROLE_PERMISSIONS] (
                    [role_id]    INT           NOT NULL,
                    [permission] NVARCHAR(255) NOT NULL,
                    PRIMARY KEY ([role_id], [permission]),
                    FOREIGN KEY ([role_id]) REFERENCES [__AUTH_ROLES]([id]) ON DELETE CASCADE
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__AUTH_ROLE_PERMISSIONS\" (
                \"role_id\"    INTEGER NOT NULL,
                \"permission\" TEXT    NOT NULL,
                PRIMARY KEY (\"role_id\", \"permission\"),
                FOREIGN KEY (\"role_id\") REFERENCES \"__AUTH_ROLES\"(\"id\") ON DELETE CASCADE
            )",
        });

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__AUTH_USER_ROLES` (
                `user_id` VARCHAR(255) NOT NULL,
                `role_id` INT          NOT NULL,
                PRIMARY KEY (`user_id`, `role_id`),
                FOREIGN KEY (`role_id`) REFERENCES `__AUTH_ROLES`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__AUTH_USER_ROLES')
                CREATE TABLE [__AUTH_USER_ROLES] (
                    [user_id] NVARCHAR(255) NOT NULL,
                    [role_id] INT           NOT NULL,
                    PRIMARY KEY ([user_id], [role_id]),
                    FOREIGN KEY ([role_id]) REFERENCES [__AUTH_ROLES]([id]) ON DELETE CASCADE
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__AUTH_USER_ROLES\" (
                \"user_id\" TEXT    NOT NULL,
                \"role_id\" INTEGER NOT NULL,
                PRIMARY KEY (\"user_id\", \"role_id\"),
                FOREIGN KEY (\"role_id\") REFERENCES \"__AUTH_ROLES\"(\"id\") ON DELETE CASCADE
            )",
        });
    },

    'down' => function ($db): void {
        $db->query('DROP TABLE IF EXISTS "__AUTH_USER_ROLES"');
        $db->query('DROP TABLE IF EXISTS "__AUTH_ROLE_PERMISSIONS"');
        $db->query('DROP TABLE IF EXISTS "__AUTH_ROLES"');
    },
];
