<?php

declare(strict_types=1);

/**
 * LazyMePHP driver-aware SQL identifier quoting
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\DB;

use Core\LazyMePHP;

/**
 * Quotes SQL identifiers (tables, columns) for the active DB driver.
 *
 * MySQL uses backticks, MSSQL uses brackets, SQLite uses double quotes.
 * Never hardcode quotes in ORM/DML — always go through Ident.
 */
final class Ident
{
    /** Quote a single identifier (table or column name). */
    public static function quote(string $name): string
    {
        // Strip any existing quotes so callers can pass already-quoted names safely
        $name = trim($name, " \t\n\r\0\x0B\"`[]");

        return match (strtolower((string) (LazyMePHP::DB_TYPE() ?? 'mysql'))) {
            'mysql' => '`' . str_replace('`', '``', $name) . '`',
            'mssql' => '[' . str_replace(']', ']]', $name) . ']',
            default => '"' . str_replace('"', '""', $name) . '"', // sqlite
        };
    }

    /**
     * Quote a bare column or a `table.column` path.
     * Does not touch expressions, aliases, or raw SQL fragments.
     */
    public static function quotePath(string $path): string
    {
        if (str_contains($path, '.')) {
            [$table, $column] = explode('.', $path, 2);
            return self::quote($table) . '.' . self::quote($column);
        }
        return self::quote($path);
    }

    /** Quote each name and join with commas. */
    public static function quoteList(array $names): string
    {
        return implode(', ', array_map([self::class, 'quote'], $names));
    }
}
