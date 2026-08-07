<?php

declare(strict_types=1);

/**
 * LazyMePHP migration blueprint
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Migration;

use Core\DB\Ident;
use Core\LazyMePHP;

/**
 * Portable table builder used by Schema::create() / Schema::table().
 * Emits driver-specific DDL at runtime so the same migration runs on
 * SQLite, MySQL, and MSSQL.
 */
class Blueprint
{
    /** @var list<array<string,mixed>> */
    private array $columns = [];

    /** @var list<string> */
    private array $dropColumns = [];

    public function __construct(
        private string $table,
        private string $mode, // 'create' | 'alter'
    ) {}

    // -------------------------------------------------------------------------
    // Column types
    // -------------------------------------------------------------------------

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->add($name, 'id');
    }

    public function increments(string $name = 'id'): ColumnDefinition
    {
        return $this->id($name);
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->add($name, 'string', ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->add($name, 'text');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->add($name, 'integer');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->add($name, 'bigInteger');
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->add($name, 'boolean');
    }

    public function float(string $name): ColumnDefinition
    {
        return $this->add($name, 'float');
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->add($name, 'decimal', ['precision' => $precision, 'scale' => $scale]);
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->add($name, 'datetime');
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->add($name, 'timestamp');
    }

    /** Adds created_at + updated_at nullable datetime columns. */
    public function timestamps(): void
    {
        $this->dateTime('created_at')->nullable();
        $this->dateTime('updated_at')->nullable();
    }

    /** Integer foreign-key style column (no FK constraint — add that in raw SQL if needed). */
    public function foreignId(string $name): ColumnDefinition
    {
        return $this->integer($name);
    }

    /** @param string|list<string> $columns */
    public function dropColumn(string|array $columns): void
    {
        foreach ((array) $columns as $col) {
            $this->dropColumns[] = $col;
        }
    }

    /** @internal */
    public function modifyColumn(string $name, string $key, mixed $value): void
    {
        foreach ($this->columns as &$col) {
            if ($col['name'] === $name) {
                $col[$key] = $value;
                return;
            }
        }
    }

    // -------------------------------------------------------------------------
    // SQL emission
    // -------------------------------------------------------------------------

    /** Single CREATE TABLE statement. */
    public function toSql(): string
    {
        $defs = [];
        foreach ($this->columns as $col) {
            $defs[] = $this->columnSql($col);
        }
        $body = implode(",\n    ", $defs);
        return 'CREATE TABLE ' . Ident::quote($this->table) . " (\n    {$body}\n)";
    }

    /** One or more ALTER TABLE statements. */
    public function toSqlStatements(): array
    {
        $statements = [];
        $qTable = Ident::quote($this->table);

        foreach ($this->columns as $col) {
            $statements[] = "ALTER TABLE {$qTable} ADD COLUMN " . $this->columnSql($col, forAlter: true);
        }

        foreach ($this->dropColumns as $col) {
            $statements[] = "ALTER TABLE {$qTable} DROP COLUMN " . Ident::quote($col);
        }

        return $statements;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function add(string $name, string $type, array $extra = []): ColumnDefinition
    {
        $this->columns[] = array_merge([
            'name'     => $name,
            'type'     => $type,
            'nullable' => false,
            'unique'   => false,
            'default'  => null,
        ], $extra);
        return new ColumnDefinition($this, $name);
    }

    private function columnSql(array $col, bool $forAlter = false): string
    {
        $dbType = strtolower((string) (LazyMePHP::DB_TYPE() ?? 'mysql'));
        $name   = Ident::quote($col['name']);
        $type   = $this->mapType($dbType, $col);

        // id / increments already includes PRIMARY KEY — no null/unique modifiers
        if ($col['type'] === 'id') {
            return "{$name} {$type}";
        }

        $sql = "{$name} {$type}";

        if (!($col['nullable'] ?? false)) {
            $sql .= ' NOT NULL';
        }

        if (array_key_exists('default', $col) && $col['default'] !== null) {
            $sql .= ' DEFAULT ' . $this->formatDefault($col['default']);
        }

        if (($col['unique'] ?? false) && !$forAlter) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    private function mapType(string $dbType, array $col): string
    {
        $length    = (int) ($col['length'] ?? 255);
        $precision = (int) ($col['precision'] ?? 8);
        $scale     = (int) ($col['scale'] ?? 2);

        return match ($col['type']) {
            'id' => match ($dbType) {
                'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
                'mssql' => 'INT IDENTITY(1,1) PRIMARY KEY',
                default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            },
            'string' => match ($dbType) {
                'mysql' => "VARCHAR({$length})",
                'mssql' => "NVARCHAR({$length})",
                default => 'TEXT',
            },
            'text' => match ($dbType) {
                'mssql' => 'NVARCHAR(MAX)',
                default => 'TEXT',
            },
            'integer' => match ($dbType) {
                'mysql', 'mssql' => 'INT',
                default => 'INTEGER',
            },
            'bigInteger' => match ($dbType) {
                'mysql', 'mssql' => 'BIGINT',
                default => 'INTEGER',
            },
            'boolean' => match ($dbType) {
                'mysql' => 'TINYINT(1)',
                'mssql' => 'BIT',
                default => 'INTEGER',
            },
            'float' => match ($dbType) {
                'mysql' => 'DOUBLE',
                'mssql' => 'FLOAT',
                default => 'REAL',
            },
            'decimal' => match ($dbType) {
                'sqlite' => 'REAL',
                default  => "DECIMAL({$precision},{$scale})",
            },
            'datetime' => match ($dbType) {
                'sqlite' => 'TEXT',
                default  => 'DATETIME',
            },
            'timestamp' => match ($dbType) {
                'mysql'  => 'TIMESTAMP',
                'mssql'  => 'DATETIME',
                default  => 'TEXT',
            },
            default => throw new \InvalidArgumentException("Unknown column type: {$col['type']}"),
        };
    }

    private function formatDefault(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value === null) {
            return 'NULL';
        }
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
