<?php

declare(strict_types=1);

/**
 * LazyMePHP Migration Runner
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Migration;

use Core\LazyMePHP;
use Core\Model;

/**
 * Runs and tracks database migrations from database/migrations/*.php.
 *
 * Each migration file must return an array:
 *
 *   return [
 *       'up'   => function ($db): void { $db->query("CREATE TABLE ..."); },
 *       'down' => function ($db): void { $db->query("DROP TABLE IF EXISTS ..."); },
 *   ];
 *
 * 'up' and 'down' may also be plain SQL strings instead of closures.
 * 'down' is optional but required for rollback.
 */
class Runner
{
    private const TABLE = '__migrations';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public static function run(): void
    {
        static::ensureTable();
        $pending = static::pending();

        if (empty($pending)) {
            echo "Nothing to migrate.\n";
            return;
        }

        $batch = (static::lastBatch() ?? 0) + 1;
        $count = 0;

        foreach ($pending as $file) {
            echo "  Migrating: $file ... ";
            $migration = require static::migrationsDir() . '/' . $file;

            if (!isset($migration['up'])) {
                echo "SKIP (no 'up' defined)\n";
                continue;
            }

            static::execute($migration['up']);
            static::record($file, $batch);
            echo "done\n";
            $count++;
        }

        Model::clearFileSchemaCache();
        echo "\nMigrated $count file(s). Schema cache cleared.\n";
    }

    public static function rollback(int $batches = 1): void
    {
        static::ensureTable();
        $rolledBack = 0;

        for ($i = 0; $i < $batches; $i++) {
            $batch = static::lastBatch();
            if ($batch === null) {
                echo "Nothing to roll back.\n";
                break;
            }

            $files = static::filesInBatch($batch);

            foreach (array_reverse($files) as $file) {
                echo "  Rolling back: $file ... ";
                $path = static::migrationsDir() . '/' . $file;

                if (!file_exists($path)) {
                    echo "SKIP (file missing)\n";
                    static::remove($file);
                    continue;
                }

                $migration = require $path;

                if (!isset($migration['down'])) {
                    echo "SKIP (no 'down' defined)\n";
                } else {
                    static::execute($migration['down']);
                    echo "done\n";
                }

                static::remove($file);
                $rolledBack++;
            }
        }

        Model::clearFileSchemaCache();
        if ($rolledBack > 0) {
            echo "\nRolled back $rolledBack file(s). Schema cache cleared.\n";
        }
    }

    public static function reset(): void
    {
        static::ensureTable();
        $total = static::lastBatch() ?? 0;

        if ($total === 0) {
            echo "Nothing to roll back.\n";
            return;
        }

        static::rollback($total);
    }

    public static function status(): void
    {
        static::ensureTable();
        $all = static::allFiles();

        if (empty($all)) {
            echo "No migration files found in database/migrations/\n";
            return;
        }

        $ran    = array_column(static::ranMigrations(), 'migration');
        $batches = array_column(static::ranMigrations(), 'batch', 'migration');

        foreach ($all as $file) {
            if (in_array($file, $ran, true)) {
                printf("  [ran]     batch:%-2d  %s\n", $batches[$file], $file);
            } else {
                echo "  [pending]           $file\n";
            }
        }
    }

    public static function scaffold(string $name): string
    {
        $dir = static::migrationsDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $date     = date('Y_m_d');
        $seq      = count(static::allFiles()) + 1;
        $slug     = preg_replace('/[^a-z0-9]+/', '_', strtolower($name));
        $filename = sprintf('%s_%04d_%s.php', $date, $seq, $slug);
        $fullPath = $dir . '/' . $filename;

        file_put_contents($fullPath, static::inferStub($name));

        return "database/migrations/$filename";
    }

    public static function fresh(): void
    {
        static::ensureTable();
        $db     = LazyMePHP::DB_CONNECTION();
        $dbType = strtolower(LazyMePHP::DB_TYPE() ?? 'mysql');

        // Drop all user tables (skip __ internal tables)
        $sql = match($dbType) {
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE '#__%' ESCAPE '#' ORDER BY name",
            'mysql'  => "SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . LazyMePHP::DB_NAME() . "' AND TABLE_NAME NOT LIKE '\\_\\_%'",
            'mssql'  => "SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME NOT LIKE '\\_\\_%'",
            default  => throw new \RuntimeException("Unsupported DB type: $dbType"),
        };

        $result = $db->query($sql);
        $tables = [];
        while ($row = $result->fetchArray()) {
            $tables[] = $row['name'] ?? $row['TABLE_NAME'] ?? '';
        }

        foreach (array_filter($tables) as $table) {
            if ($dbType === 'sqlite') {
                $db->query('DROP TABLE IF EXISTS "' . $table . '"');
            } elseif ($dbType === 'mysql') {
                $db->query('DROP TABLE IF EXISTS `' . $table . '`');
            } else {
                $db->query('DROP TABLE IF EXISTS [' . $table . ']');
            }
            echo "  Dropped: $table\n";
        }

        static::run();
    }

    protected static function inferStub(string $name): string
    {
        $raw = strtolower($name);

        if (preg_match('/^create_(.+)_table$/', $raw, $m)) {
            $table = $m[1];
            return <<<PHP
<?php

return [
    'up' => function (\$db): void {
        \$db->query("
            CREATE TABLE {$table} (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT    NOT NULL
            )
        ");
    },

    'down' => function (\$db): void {
        \$db->query("DROP TABLE IF EXISTS {$table}");
    },
];
PHP;
        }

        if (preg_match('/^add_(.+)_to_(.+)$/', $raw, $m)) {
            $column = $m[1];
            $table  = $m[2];
            return <<<PHP
<?php

return [
    'up' => function (\$db): void {
        \$db->query("ALTER TABLE {$table} ADD COLUMN {$column} TEXT");
    },

    'down' => function (\$db): void {
        // SQLite does not support DROP COLUMN before 3.35.0
        // \$db->query("ALTER TABLE {$table} DROP COLUMN {$column}");
    },
];
PHP;
        }

        if (preg_match('/^drop_(.+)_table$/', $raw, $m)) {
            $table = $m[1];
            return <<<PHP
<?php

return [
    'up' => function (\$db): void {
        \$db->query("DROP TABLE IF EXISTS {$table}");
    },

    'down' => function (\$db): void {
        // Recreate the {$table} table here if you need rollback support
    },
];
PHP;
        }

        if (preg_match('/^rename_(.+)_to_(.+)$/', $raw, $m)) {
            $oldTable = $m[1];
            $newTable = $m[2];
            return <<<PHP
<?php

return [
    'up' => function (\$db): void {
        \$db->query("ALTER TABLE {$oldTable} RENAME TO {$newTable}");
    },

    'down' => function (\$db): void {
        \$db->query("ALTER TABLE {$newTable} RENAME TO {$oldTable}");
    },
];
PHP;
        }

        // Generic stub
        return <<<PHP
<?php

return [
    'up' => function (\$db): void {
        \$db->query("
            -- Write your SQL here
        ");
    },

    'down' => function (\$db): void {
        // Reverse the 'up' operation
    },
];
PHP;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected static function migrationsDir(): string
    {
        return dirname(__DIR__, 3) . '/database/migrations';
    }

    protected static function execute(mixed $step): void
    {
        $db = LazyMePHP::DB_CONNECTION();
        if (is_string($step)) {
            $db->query($step);
        } else {
            $step($db);
        }
    }

    protected static function ensureTable(): void
    {
        $db     = LazyMePHP::DB_CONNECTION();
        $dbType = strtolower(LazyMePHP::DB_TYPE() ?? 'mysql');
        $t      = self::TABLE;

        $sql = match($dbType) {
            'sqlite' => "CREATE TABLE IF NOT EXISTS \"$t\" (
                            id        INTEGER  PRIMARY KEY AUTOINCREMENT,
                            migration TEXT     NOT NULL UNIQUE,
                            batch     INTEGER  NOT NULL,
                            ran_at    DATETIME DEFAULT CURRENT_TIMESTAMP
                        )",
            'mysql'  => "CREATE TABLE IF NOT EXISTS `$t` (
                            `id`        INT AUTO_INCREMENT PRIMARY KEY,
                            `migration` VARCHAR(255) NOT NULL UNIQUE,
                            `batch`     INT          NOT NULL,
                            `ran_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP
                        )",
            'mssql'  => "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='$t' AND xtype='U')
                         CREATE TABLE [$t] (
                            id        INT IDENTITY(1,1) PRIMARY KEY,
                            migration NVARCHAR(255) NOT NULL UNIQUE,
                            batch     INT          NOT NULL,
                            ran_at    DATETIME     DEFAULT GETDATE()
                        )",
            default  => throw new \RuntimeException("Unsupported DB type: $dbType"),
        };

        $db->query($sql);
    }

    protected static function allFiles(): array
    {
        $dir   = static::migrationsDir();
        $files = is_dir($dir) ? (glob($dir . '/*.php') ?: []) : [];
        $files = array_map('basename', $files);
        sort($files);
        return $files;
    }

    protected static function pending(): array
    {
        $ran = array_column(static::ranMigrations(), 'migration');
        return array_values(array_filter(static::allFiles(), fn($f) => !in_array($f, $ran, true)));
    }

    protected static function ranMigrations(): array
    {
        $db     = LazyMePHP::DB_CONNECTION();
        $result = $db->query('SELECT migration, batch FROM "' . self::TABLE . '" ORDER BY batch, migration');
        $rows   = [];
        while ($row = $result->fetchArray()) {
            $rows[] = $row;
        }
        return $rows;
    }

    protected static function lastBatch(): ?int
    {
        $db     = LazyMePHP::DB_CONNECTION();
        $result = $db->query('SELECT MAX(batch) AS b FROM "' . self::TABLE . '"');
        $row    = $result->fetchArray();
        return isset($row['b']) && $row['b'] !== null ? (int)$row['b'] : null;
    }

    protected static function filesInBatch(int $batch): array
    {
        $db     = LazyMePHP::DB_CONNECTION();
        $result = $db->query('SELECT migration FROM "' . self::TABLE . '" WHERE batch = ? ORDER BY migration', [$batch]);
        $rows   = [];
        while ($row = $result->fetchArray()) {
            $rows[] = $row['migration'];
        }
        return $rows;
    }

    protected static function record(string $file, int $batch): void
    {
        LazyMePHP::DB_CONNECTION()->query(
            'INSERT INTO "' . self::TABLE . '" (migration, batch) VALUES (?, ?)',
            [$file, $batch]
        );
    }

    protected static function remove(string $file): void
    {
        LazyMePHP::DB_CONNECTION()->query(
            'DELETE FROM "' . self::TABLE . '" WHERE migration = ?',
            [$file]
        );
    }
}
