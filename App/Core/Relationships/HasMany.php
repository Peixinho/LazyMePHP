<?php

declare(strict_types=1);

namespace Core\Relationships;

use Core\Model;

/**
 * One-to-many: the related table holds a foreign key pointing back to this model.
 *
 * Example — User hasMany Posts:
 *   public function posts(): HasMany {
 *       return $this->hasMany('posts', 'user_id');
 *   }
 *
 *   $user->posts;                           // lazy — one query
 *   User::query()->with('posts')->get();    // eager — two queries total
 */
class HasMany extends Relationship
{
    public function getResults(): array
    {
        return Model::query($this->relatedTable)
            ->where($this->foreignKey, $this->parent->{$this->localKey})
            ->get();
    }

    public function eagerLoad(array $models, string $name): void
    {
        $keys = $this->parentKeys($models, $this->localKey);
        if (empty($keys)) {
            foreach ($models as $m) $m->setRelation($name, []);
            return;
        }

        $related = Model::query($this->relatedTable)
            ->whereIn($this->foreignKey, $keys)
            ->get();

        $grouped = [];
        foreach ($related as $r) {
            $grouped[(string)$r->{$this->foreignKey}][] = $r;
        }

        foreach ($models as $m) {
            $m->setRelation($name, $grouped[(string)$m->{$this->localKey}] ?? []);
        }
    }

    /** @return list<mixed> */
    private function parentKeys(array $models, string $key): array
    {
        return array_values(array_unique(
            array_filter(array_map(fn($m) => $m->{$key}, $models), fn($v) => $v !== null)
        ));
    }
}
