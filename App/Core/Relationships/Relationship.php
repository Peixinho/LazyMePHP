<?php

declare(strict_types=1);

namespace Core\Relationships;

use Core\Model;

/**
 * Base class for all ORM relationships.
 *
 * Each concrete subclass knows how to:
 *   - getResults(): execute a lazy query for one parent model
 *   - eagerLoad():  batch-load for many parent models (prevents N+1)
 */
abstract class Relationship
{
    protected Model  $parent;
    protected string $relatedTable;
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(
        Model  $parent,
        string $relatedTable,
        string $foreignKey,
        string $localKey,
    ) {
        $this->parent       = $parent;
        $this->relatedTable = $relatedTable;
        $this->foreignKey   = $foreignKey;
        $this->localKey     = $localKey;
    }

    /** Execute the relationship query for a single already-loaded parent model. */
    abstract public function getResults(): mixed;

    /**
     * Batch-load this relationship for all $models in one query and call
     * setRelation($name, $value) on each model. Called by ModelQuery::get()
     * when ->with('relationName') is used.
     *
     * @param list<Model> $models
     */
    abstract public function eagerLoad(array $models, string $name): void;
}
