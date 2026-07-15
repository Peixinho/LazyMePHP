<?php

declare(strict_types=1);

namespace Core;

/**
 * Fluent proxy returned by the arr() global helper.
 *
 *   arr(['a.b' => 1])->undot()                     // ['a' => ['b' => 1]]
 *   arr($users)->pluck('email')                     // ['a@…', 'b@…']
 *   arr($items)->where(fn($v) => $v > 2)->values()  // [3, 4, 5]
 *
 * All Arr static methods are available as instance methods.
 */
class ArrProxy
{
    private array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /** Delegate to Arr static methods, passing $this->items as the first argument. */
    public function __call(string $method, array $args): mixed
    {
        $result = Arr::{$method}($this->items, ...$args);
        if (is_array($result)) {
            return new static($result);
        }
        return $result;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function all(): array
    {
        return $this->items;
    }
}
