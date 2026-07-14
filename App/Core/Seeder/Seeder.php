<?php

declare(strict_types=1);

namespace Core\Seeder;

use Core\LazyMePHP;

/**
 * Base class for all seeders.
 *
 * Create a seeder in App/Seeders/:
 *
 *   class UsersSeeder extends \Core\Seeder\Seeder {
 *       public function run(): void {
 *           $this->insert('users', ['name' => 'Alice', 'email' => 'alice@example.com']);
 *       }
 *   }
 *
 * Run with: php lazymephp db:seed
 */
abstract class Seeder
{
    abstract public function run(): void;

    protected function insert(string $table, array $row): void
    {
        $db   = LazyMePHP::DB_CONNECTION();
        $cols = implode(', ', array_map(fn($k) => "\"$k\"", array_keys($row)));
        $ph   = implode(', ', array_fill(0, count($row), '?'));
        $db->query("INSERT INTO \"$table\" ($cols) VALUES ($ph)", array_values($row));
    }

    protected function db(): \Core\DB\ISQL
    {
        return LazyMePHP::DB_CONNECTION();
    }
}
