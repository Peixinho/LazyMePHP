<?php

declare(strict_types=1);

namespace Core\Relationships;

use Core\Model;

/**
 * Inverse of hasOne / hasMany — the foreign key lives on this model's own table.
 *
 * Example — Post belongsTo User:
 *   public function author(): BelongsTo {
 *       return $this->belongsTo('users', 'user_id');
 *   }
 *
 *   $post->author;                           // lazy
 *   Post::query()->with('author')->get();    // eager
 *
 * Constructor key convention:
 *   $foreignKey — column on THIS model's table (e.g. 'user_id')
 *   $localKey   — primary key on the RELATED table (e.g. 'id')
 */
class BelongsTo extends Relationship
{
    public function getResults(): ?Model
    {
        $fkValue = $this->parent->{$this->foreignKey};
        if ($fkValue === null) return null;

        return Model::query($this->relatedTable)
            ->where($this->localKey, $fkValue)
            ->first();
    }

    public function eagerLoad(array $models, string $name): void
    {
        $keys = $this->fkValues($models);
        if (empty($keys)) {
            foreach ($models as $m) $m->setRelation($name, null);
            return;
        }

        $related = Model::query($this->relatedTable)
            ->whereIn($this->localKey, $keys)
            ->get();

        $indexed = [];
        foreach ($related as $r) {
            $indexed[(string)$r->{$this->localKey}] = $r;
        }

        foreach ($models as $m) {
            $fk = $m->{$this->foreignKey};
            $m->setRelation($name, $fk !== null ? ($indexed[(string)$fk] ?? null) : null);
        }
    }

    private function fkValues(array $models): array
    {
        return array_values(array_unique(
            array_filter(array_map(fn($m) => $m->{$this->foreignKey}, $models), fn($v) => $v !== null)
        ));
    }
}
