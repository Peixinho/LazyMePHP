<?php

declare(strict_types=1);

namespace Core\Relationships;

use Core\Model;
use Core\LazyMePHP;

/**
 * Many-to-many through a pivot table.
 *
 * Example — Post belongsToMany Tags through post_tags:
 *   public function tags(): BelongsToMany {
 *       return $this->belongsToMany('tags', 'post_tags', 'post_id', 'tag_id');
 *   }
 *
 *   $post->tags;                           // lazy
 *   Post::query()->with('tags')->get();    // eager — 2 queries total
 *
 * Constructor:
 *   $relatedTable      — the target table ('tags')
 *   $pivotTable        — the join table ('post_tags')
 *   $foreignKey        — pivot column pointing to THIS model ('post_id')
 *   $relatedForeignKey — pivot column pointing to the related model ('tag_id')
 *   $localKey          — PK of THIS model (auto-detected when null)
 *   $relatedKey        — PK of the related model (auto-detected when null)
 */
class BelongsToMany extends Relationship
{
    private string  $pivotTable;
    private string  $relatedForeignKey;
    private ?string $relatedKey;

    public function __construct(
        Model   $parent,
        string  $relatedTable,
        string  $pivotTable,
        string  $foreignKey,
        string  $relatedForeignKey,
        string  $localKey,
        ?string $relatedKey = null,
    ) {
        parent::__construct($parent, $relatedTable, $foreignKey, $localKey);
        $this->pivotTable        = $pivotTable;
        $this->relatedForeignKey = $relatedForeignKey;
        $this->relatedKey        = $relatedKey;
    }

    public function getResults(): array
    {
        $db        = LazyMePHP::DB_CONNECTION();
        $relatedPk = $this->relatedPk();
        $parentKey = $this->parent->{$this->localKey};

        $result = $db->query(
            "SELECT r.* FROM \"{$this->relatedTable}\" r
             INNER JOIN \"{$this->pivotTable}\" p
               ON p.\"{$this->relatedForeignKey}\" = r.\"{$relatedPk}\"
             WHERE p.\"{$this->foreignKey}\" = ?",
            [$parentKey]
        );

        $models = [];
        while ($row = $result->fetchArray()) {
            $models[] = new Model($this->relatedTable, $row);
        }
        return $models;
    }

    public function eagerLoad(array $models, string $name): void
    {
        $keys = $this->parentKeys($models, $this->localKey);
        if (empty($keys)) {
            foreach ($models as $m) $m->setRelation($name, []);
            return;
        }

        $db        = LazyMePHP::DB_CONNECTION();
        $relatedPk = $this->relatedPk();
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        // Alias the pivot FK to avoid column name clashes with the related table
        $result = $db->query(
            "SELECT r.*, p.\"{$this->foreignKey}\" AS __pivot_parent_fk
             FROM \"{$this->relatedTable}\" r
             INNER JOIN \"{$this->pivotTable}\" p
               ON p.\"{$this->relatedForeignKey}\" = r.\"{$relatedPk}\"
             WHERE p.\"{$this->foreignKey}\" IN ({$placeholders})",
            $keys
        );

        $grouped = [];
        while ($row = $result->fetchArray()) {
            $pivotFk = (string)$row['__pivot_parent_fk'];
            unset($row['__pivot_parent_fk']);
            $grouped[$pivotFk][] = new Model($this->relatedTable, $row);
        }

        foreach ($models as $m) {
            $m->setRelation($name, $grouped[(string)$m->{$this->localKey}] ?? []);
        }
    }

    public function countSubquery(string $parentTable): string
    {
        return "(SELECT COUNT(*) FROM \"{$this->pivotTable}\" WHERE \"{$this->pivotTable}\".\"{$this->foreignKey}\" = \"{$parentTable}\".\"{$this->localKey}\")";
    }

    public function getPivotTable(): string        { return $this->pivotTable; }
    public function getRelatedForeignKey(): string { return $this->relatedForeignKey; }

    private function relatedPk(): string
    {
        if ($this->relatedKey !== null) return $this->relatedKey;
        $schema = Model::schemaFor($this->relatedTable);
        foreach ($schema as $col => $meta) {
            if ($meta['pk']) return $col;
        }
        return 'id';
    }

    private function parentKeys(array $models, string $key): array
    {
        return array_values(array_unique(
            array_filter(array_map(fn($m) => $m->{$key}, $models), fn($v) => $v !== null)
        ));
    }
}
