<?php

declare(strict_types=1);

/**
 * LazyMePHP portable schema builder
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Migration;

use Core\DB\Ident;
use Core\LazyMePHP;

/**
 * Driver-aware DDL helpers for migrations.
 *
 * Prefer Schema over raw `$db->query("CREATE TABLE ...")` so the same
 * migration file works on SQLite, MySQL, and MSSQL:
 *
 *   return [
 *       'up' => function (): void {
 *           Schema::create('posts', function (Blueprint $t) {
 *               $t->id();
 *               $t->string('title');
 *               $t->text('body')->nullable();
 *               $t->timestamps();
 *           });
 *       },
 *       'down' => function (): void {
 *           Schema::dropIfExists('posts');
 *       },
 *   ];
 */
class Schema
{
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'create');
        $callback($blueprint);
        LazyMePHP::DB_CONNECTION()->query($blueprint->toSql());
    }

    /**
     * Alter an existing table (add / drop columns).
     *
     *   Schema::table('users', function (Blueprint $t) {
     *       $t->string('avatar')->nullable();
     *   });
     */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'alter');
        $callback($blueprint);
        $db = LazyMePHP::DB_CONNECTION();
        foreach ($blueprint->toSqlStatements() as $sql) {
            $db->query($sql);
        }
    }

    public static function dropIfExists(string $table): void
    {
        LazyMePHP::DB_CONNECTION()->query('DROP TABLE IF EXISTS ' . Ident::quote($table));
    }

    public static function rename(string $from, string $to): void
    {
        $dbType = strtolower((string) (LazyMePHP::DB_TYPE() ?? 'mysql'));
        $sql = match ($dbType) {
            'mysql' => 'RENAME TABLE ' . Ident::quote($from) . ' TO ' . Ident::quote($to),
            default => 'ALTER TABLE ' . Ident::quote($from) . ' RENAME TO ' . Ident::quote($to),
        };
        LazyMePHP::DB_CONNECTION()->query($sql);
    }
}
