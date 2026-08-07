<?php

declare(strict_types=1);

/**
 * LazyMePHP migration column definition
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Migration;

/**
 * Fluent modifiers for a column declared on a Blueprint.
 *
 *   $table->string('email')->nullable()->unique();
 *   $table->integer('count')->default(0);
 */
class ColumnDefinition
{
    public function __construct(
        private Blueprint $blueprint,
        private string $name,
    ) {}

    public function nullable(): static
    {
        $this->blueprint->modifyColumn($this->name, 'nullable', true);
        return $this;
    }

    public function unique(): static
    {
        $this->blueprint->modifyColumn($this->name, 'unique', true);
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->blueprint->modifyColumn($this->name, 'default', $value);
        return $this;
    }
}
