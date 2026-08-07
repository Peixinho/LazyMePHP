<?php

declare(strict_types=1);

namespace Core;

use Core\DB\Ident;

/**
 * Soft-delete support for Model subclasses.
 *
 * Usage:
 *
 *   class Post extends \Core\Model {
 *       use \Core\SoftDeletes;
 *   }
 *
 * Behaviour:
 *   - $post->Delete()  → sets deleted_at timestamp instead of removing the row
 *   - $post->restore() → clears deleted_at
 *   - Model::query()   → automatically excludes soft-deleted rows
 *   - ->withTrashed()  → includes soft-deleted rows
 *   - ->onlyTrashed()  → returns only soft-deleted rows
 *
 * The column name defaults to `deleted_at`. Override with:
 *   protected static string $deletedAtColumn = 'removed_at';
 */
trait SoftDeletes
{
    /** Column that stores the soft-delete timestamp. Override to customise. */
    protected static string $deletedAtColumn = 'deleted_at';

    /** Marks this model as soft-deleted (sets deleted_at, does NOT DELETE the row). */
    public function Delete(): bool
    {
        $col   = static::$deletedAtColumn;
        $pk    = $this->getPrimaryKey();
        $pkCol = $this->getPrimaryKeyColumn();
        if ($pk === null || $pkCol === null) return false;

        LazyMePHP::DB_CONNECTION()->query(
            'UPDATE ' . Ident::quote($this->getTable()) . ' SET ' . Ident::quote($col) . ' = ? WHERE ' . Ident::quote($pkCol) . ' = ?',
            [date('Y-m-d H:i:s'), $pk]
        );
        $this->$col = date('Y-m-d H:i:s');
        return true;
    }

    /** Restore a soft-deleted model (clears deleted_at). */
    public function restore(): bool
    {
        $col   = static::$deletedAtColumn;
        $pk    = $this->getPrimaryKey();
        $pkCol = $this->getPrimaryKeyColumn();
        if ($pk === null || $pkCol === null) return false;

        LazyMePHP::DB_CONNECTION()->query(
            'UPDATE ' . Ident::quote($this->getTable()) . ' SET ' . Ident::quote($col) . ' = NULL WHERE ' . Ident::quote($pkCol) . ' = ?',
            [$pk]
        );
        $this->$col = null;
        return true;
    }

    /** True when this record has been soft-deleted. */
    public function isTrashed(): bool
    {
        $col = static::$deletedAtColumn;
        return $this->$col !== null;
    }

    /** Called by ModelQuery::get() to determine the soft-delete column for this model class. */
    public static function softDeleteColumn(): string
    {
        return static::$deletedAtColumn;
    }
}
