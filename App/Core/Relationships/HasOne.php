<?php

declare(strict_types=1);

namespace Core\Relationships;

use Core\Model;

/**
 * One-to-one where the foreign key lives on the related table.
 *
 * Example — User hasOne Profile:
 *   public function profile(): HasOne {
 *       return $this->hasOne('profiles', 'user_id');
 *   }
 *
 *   $user->profile;                          // lazy
 *   User::query()->with('profile')->get();   // eager
 */
class HasOne extends Relationship
{
    public function getResults(): ?Model
    {
        return Model::query($this->relatedTable)
            ->where($this->foreignKey, $this->parent->{$this->localKey})
            ->first();
    }

    public function eagerLoad(array $models, string $name): void
    {
        $keys = $this->parentKeys($models, $this->localKey);
        if (empty($keys)) {
            foreach ($models as $m) $m->setRelation($name, null);
            return;
        }

        $related = Model::query($this->relatedTable)
            ->whereIn($this->foreignKey, $keys)
            ->get();

        $indexed = [];
        foreach ($related as $r) {
            // First match wins — matches hasOne semantics
            $indexed[(string)$r->{$this->foreignKey}] ??= $r;
        }

        foreach ($models as $m) {
            $m->setRelation($name, $indexed[(string)$m->{$this->localKey}] ?? null);
        }
    }

    private function parentKeys(array $models, string $key): array
    {
        return array_values(array_unique(
            array_filter(array_map(fn($m) => $m->{$key}, $models), fn($v) => $v !== null)
        ));
    }
}
