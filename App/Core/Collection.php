<?php

declare(strict_types=1);

namespace Core;

/**
 * Fluent array wrapper returned by ModelQuery::collect() and usable anywhere.
 *
 *   $users = User::query()->collect();
 *   $names = $users->pluck('name');
 *   $by_role = $users->groupBy('role');
 *   $total = Order::query()->collect()->sum('amount');
 */
class Collection implements \Countable, \IteratorAggregate, \ArrayAccess, \JsonSerializable
{
    /** @param list<mixed> $items */
    public function __construct(private array $items = []) {}

    public static function make(array $items = []): static
    {
        return new static($items);
    }

    // -------------------------------------------------------------------------
    // Transformations
    // -------------------------------------------------------------------------

    public function filter(?callable $callback = null): static
    {
        return new static($callback
            ? array_values(array_filter($this->items, $callback))
            : array_values(array_filter($this->items)));
    }

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    public function flatMap(callable $callback): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $mapped = $callback($item);
            foreach ((is_array($mapped) ? $mapped : [$mapped]) as $v) {
                $result[] = $v;
            }
        }
        return new static($result);
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) break;
        }
        return $this;
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function reverse(): static
    {
        return new static(array_reverse($this->items));
    }

    public function unique(?string $key = null): static
    {
        if ($key === null) {
            return new static(array_values(array_unique($this->items)));
        }
        $seen   = [];
        $result = [];
        foreach ($this->items as $item) {
            $val = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            $k   = serialize($val);
            if (!isset($seen[$k])) {
                $seen[$k]  = true;
                $result[] = $item;
            }
        }
        return new static($result);
    }

    public function values(): static
    {
        return new static($this->items);
    }

    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    public function merge(array|self $items): static
    {
        return new static(array_merge($this->items, $items instanceof self ? $items->all() : $items));
    }

    public function push(mixed $item): static
    {
        $items   = $this->items;
        $items[] = $item;
        return new static($items);
    }

    public function put(string|int $key, mixed $value): static
    {
        $items[$key] = $value;
        return new static(array_replace($this->items, [$key => $value]));
    }

    public function forget(string|int $key): static
    {
        $items = $this->items;
        unset($items[$key]);
        return new static(array_values($items));
    }

    public function take(int $count): static
    {
        return new static(array_slice($this->items, 0, $count));
    }

    public function skip(int $count): static
    {
        return new static(array_slice($this->items, $count));
    }

    public function chunk(int $size): static
    {
        $chunks = array_chunk($this->items, $size);
        return new static(array_map(fn(array $c) => new static($c), $chunks));
    }

    public function flatten(int $depth = PHP_INT_MAX): static
    {
        $items  = $depth === PHP_INT_MAX ? $this->items : $this->flattenDepth($this->items, $depth);
        $result = [];
        $flat   = function (array $arr) use (&$flat, &$result): void {
            foreach ($arr as $v) {
                if (is_array($v)) { $flat($v); } else { $result[] = $v; }
            }
        };
        $flat($items);
        return new static($result);
    }

    private function flattenDepth(array $array, int $depth): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item) && $depth > 0) {
                foreach ($this->flattenDepth($item, $depth - 1) as $v) {
                    $result[] = $v;
                }
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // Sorting
    // -------------------------------------------------------------------------

    public function sortBy(string|callable $key, bool $descending = false): static
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $descending) {
            $va = is_callable($key) ? $key($a) : (is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null));
            $vb = is_callable($key) ? $key($b) : (is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null));
            return $descending ? $vb <=> $va : $va <=> $vb;
        });
        return new static($items);
    }

    public function sortByDesc(string|callable $key): static
    {
        return $this->sortBy($key, true);
    }

    // -------------------------------------------------------------------------
    // Searching
    // -------------------------------------------------------------------------

    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) return $this->items[0] ?? $default;
        foreach ($this->items as $item) {
            if ($callback($item)) return $item;
        }
        return $default;
    }

    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        $items = $callback ? array_values(array_filter($this->items, $callback)) : $this->items;
        return empty($items) ? $default : end($items);
    }

    public function get(string|int $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function find(callable $callback): mixed
    {
        return $this->first($callback);
    }

    public function contains(mixed $item): bool
    {
        if (is_callable($item)) {
            foreach ($this->items as $v) {
                if ($item($v)) return true;
            }
            return false;
        }
        return in_array($item, $this->items, true);
    }

    // -------------------------------------------------------------------------
    // Grouping / indexing
    // -------------------------------------------------------------------------

    public function groupBy(string|callable $key): static
    {
        $groups = [];
        foreach ($this->items as $item) {
            $k = is_callable($key)
                ? (string)$key($item)
                : (string)(is_array($item) ? ($item[$key] ?? '') : ($item->$key ?? ''));
            $groups[$k][] = $item;
        }
        return new static(array_map(fn(array $g) => new static($g), $groups));
    }

    public function keyBy(string|callable $key): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $k = is_callable($key)
                ? (string)$key($item)
                : (string)(is_array($item) ? ($item[$key] ?? '') : ($item->$key ?? ''));
            $result[$k] = $item;
        }
        return new static($result);
    }

    public function pluck(string $key, ?string $indexBy = null): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $val = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            if ($indexBy !== null) {
                $idx          = is_array($item) ? ($item[$indexBy] ?? '') : ($item->$indexBy ?? '');
                $result[$idx] = $val;
            } else {
                $result[] = $val;
            }
        }
        return new static($result);
    }

    public function countBy(string|callable $key): static
    {
        $counts = [];
        foreach ($this->items as $item) {
            $k = is_callable($key)
                ? (string)$key($item)
                : (string)(is_array($item) ? ($item[$key] ?? '') : ($item->$key ?? ''));
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        return new static($counts);
    }

    // -------------------------------------------------------------------------
    // Filtering shortcuts
    // -------------------------------------------------------------------------

    public function where(string $key, mixed $operatorOrValue, mixed $value = null): static
    {
        if ($value === null) {
            $value    = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = (string)$operatorOrValue;
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $v = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            return match ($operator) {
                '=', '=='  => $v == $value,
                '===', 'is' => $v === $value,
                '!='        => $v != $value,
                '!=='       => $v !== $value,
                '<'         => $v < $value,
                '<='        => $v <= $value,
                '>'         => $v > $value,
                '>='        => $v >= $value,
                default     => $v == $value,
            };
        });
    }

    public function whereIn(string $key, array $values): static
    {
        return $this->filter(function ($item) use ($key, $values) {
            $v = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            return in_array($v, $values, true);
        });
    }

    public function whereNotIn(string $key, array $values): static
    {
        return $this->filter(function ($item) use ($key, $values) {
            $v = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            return !in_array($v, $values, true);
        });
    }

    // -------------------------------------------------------------------------
    // Aggregates
    // -------------------------------------------------------------------------

    public function sum(string|callable|null $field = null): int|float
    {
        return array_sum($this->pluckValues($field));
    }

    public function avg(string|callable|null $field = null): float
    {
        $vals = $this->pluckValues($field);
        return empty($vals) ? 0.0 : array_sum($vals) / count($vals);
    }

    public function min(string|callable|null $field = null): mixed
    {
        $vals = $this->pluckValues($field);
        return empty($vals) ? null : min($vals);
    }

    public function max(string|callable|null $field = null): mixed
    {
        $vals = $this->pluckValues($field);
        return empty($vals) ? null : max($vals);
    }

    private function pluckValues(string|callable|null $field): array
    {
        if ($field === null) return $this->items;
        if (is_callable($field)) return array_map($field, $this->items);
        return array_map(fn($item) => is_array($item) ? ($item[$field] ?? 0) : ($item->$field ?? 0), $this->items);
    }

    public function implode(string $glue, ?string $key = null): string
    {
        $values = $key === null ? $this->items : $this->pluck($key)->all();
        return implode($glue, array_map('strval', $values));
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    public function tap(callable $callback): static
    {
        $callback($this);
        return $this;
    }

    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    public function zip(array $items): static
    {
        $result = [];
        foreach ($this->items as $i => $item) {
            $result[] = new static([$item, $items[$i] ?? null]);
        }
        return new static($result);
    }

    public function shuffle(): static
    {
        $items = $this->items;
        shuffle($items);
        return new static($items);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function all(): array    { return $this->items; }
    public function toArray(): array { return $this->items; }
    public function count(): int    { return count($this->items); }
    public function isEmpty(): bool  { return empty($this->items); }
    public function isNotEmpty(): bool { return !empty($this->items); }

    // -------------------------------------------------------------------------
    // Interface implementations
    // -------------------------------------------------------------------------

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function jsonSerialize(): array
    {
        return $this->items;
    }

    public function offsetExists(mixed $offset): bool  { return isset($this->items[$offset]); }
    public function offsetGet(mixed $offset): mixed    { return $this->items[$offset]; }
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) { $this->items[] = $value; } else { $this->items[$offset] = $value; }
    }
    public function offsetUnset(mixed $offset): void { unset($this->items[$offset]); }

    public function __toString(): string
    {
        return (string)json_encode($this->items);
    }
}
