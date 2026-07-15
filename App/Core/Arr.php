<?php

declare(strict_types=1);

namespace Core;

use ArrayAccess;

class Arr
{
    // -------------------------------------------------------------------------
    // Access (dot-notation)
    // -------------------------------------------------------------------------

    public static function get(array|ArrayAccess $array, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) return $array;

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (!is_string($key) || !str_contains($key, '.')) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public static function set(array &$array, string|int|null $key, mixed $value): array
    {
        if ($key === null) {
            $array = (array)$value;
            return $array;
        }

        $keys = is_string($key) ? explode('.', $key) : [$key];

        foreach ($keys as $i => $k) {
            if (count($keys) === 1) break;
            unset($keys[$i]);
            if (!isset($array[$k]) || !is_array($array[$k])) {
                $array[$k] = [];
            }
            $array = &$array[$k];
        }

        $array[array_shift($keys)] = $value;
        return $array;
    }

    public static function has(array|ArrayAccess $array, string|array $keys): bool
    {
        if (!$array) return false;

        foreach ((array)$keys as $key) {
            $subarray = $array;
            if (static::exists($array, $key)) continue;
            foreach (explode('.', $key) as $segment) {
                if (static::accessible($subarray) && static::exists($subarray, $segment)) {
                    $subarray = $subarray[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    public static function forget(array &$array, string|array $keys): void
    {
        foreach ((array)$keys as $key) {
            if (static::exists($array, $key)) {
                unset($array[$key]);
                continue;
            }

            $parts = explode('.', $key);
            $current = &$array;

            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    break;
                }
                $current = &$current[$part];
            }

            unset($current[array_shift($parts)]);
        }
    }

    public static function pull(array &$array, string $key, mixed $default = null): mixed
    {
        $value = static::get($array, $key, $default);
        static::forget($array, $key);
        return $value;
    }

    public static function add(array $array, string $key, mixed $value): array
    {
        if (static::get($array, $key) === null) {
            static::set($array, $key, $value);
        }
        return $array;
    }

    // -------------------------------------------------------------------------
    // Existence checks
    // -------------------------------------------------------------------------

    public static function accessible(mixed $value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    public static function exists(array|ArrayAccess $array, string|int $key): bool
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }
        return array_key_exists($key, $array);
    }

    // -------------------------------------------------------------------------
    // Wrapping / unwrapping
    // -------------------------------------------------------------------------

    public static function wrap(mixed $value): array
    {
        if ($value === null) return [];
        return is_array($value) ? $value : [$value];
    }

    public static function collapse(iterable $array): array
    {
        $results = [];
        foreach ($array as $values) {
            if (!is_array($values)) continue;
            $results[] = $values;
        }
        return array_merge([], ...$results);
    }

    public static function prepend(array $array, mixed $value, mixed $key = null): array
    {
        if (func_num_args() === 2) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }
        return $array;
    }

    // -------------------------------------------------------------------------
    // Extraction
    // -------------------------------------------------------------------------

    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array)$keys));
    }

    public static function except(array $array, array|string $keys): array
    {
        $result = $array;
        static::forget($result, $keys);
        return $result;
    }

    public static function pluck(array $array, string|array $value, ?string $key = null): array
    {
        $results = [];

        foreach ($array as $item) {
            $itemValue = is_array($item)
                ? static::get($item, is_array($value) ? $value[0] : $value)
                : (is_object($item) ? $item->{is_array($value) ? $value[0] : $value} ?? null : null);

            if ($key !== null) {
                $itemKey = is_array($item)
                    ? static::get($item, $key)
                    : (is_object($item) ? $item->{$key} ?? null : null);
                $results[(string)$itemKey] = $itemValue;
            } else {
                $results[] = $itemValue;
            }
        }

        return $results;
    }

    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if (empty($array)) return $default;
            foreach ($array as $item) return $item;
        }
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) return $value;
        }
        return $default;
    }

    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }
        return static::first(array_reverse($array, true), $callback, $default);
    }

    // -------------------------------------------------------------------------
    // Transformation
    // -------------------------------------------------------------------------

    public static function map(array $array, callable $callback): array
    {
        $keys = array_keys($array);
        return array_combine($keys, array_map($callback, $array, $keys)) ?: [];
    }

    public static function mapWithKeys(array $array, callable $callback): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $assoc = $callback($value, $key);
            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }
        return $result;
    }

    public static function flatMap(array $array, callable $callback): array
    {
        return static::collapse(static::map($array, $callback));
    }

    public static function flatten(array $array, float $depth = INF): array
    {
        $result = [];
        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1.0) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, static::flatten($item, $depth - 1));
            }
        }
        return $result;
    }

    public static function keyBy(array $array, callable|string $keyBy): array
    {
        $results = [];
        foreach ($array as $item) {
            $key = is_callable($keyBy) ? $keyBy($item) : static::get((array)$item, $keyBy);
            $results[(string)$key] = $item;
        }
        return $results;
    }

    public static function groupBy(array $array, callable|string $groupBy): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            $groupKey = is_callable($groupBy) ? $groupBy($value, $key) : static::get((array)$value, $groupBy);
            $results[(string)$groupKey][] = $value;
        }
        return $results;
    }

    // -------------------------------------------------------------------------
    // Filtering
    // -------------------------------------------------------------------------

    public static function filter(array $array, ?callable $callback = null): array
    {
        return $callback !== null
            ? array_filter($array, $callback, ARRAY_FILTER_USE_BOTH)
            : array_filter($array);
    }

    public static function where(array $array, callable $callback): array
    {
        return static::filter($array, $callback);
    }

    public static function whereNotNull(array $array): array
    {
        return static::filter($array, fn($value) => $value !== null);
    }

    public static function reject(array $array, mixed $callback): array
    {
        if (is_callable($callback)) {
            return static::filter($array, fn($v, $k) => !$callback($v, $k));
        }
        return static::filter($array, fn($v) => $v !== $callback);
    }

    // -------------------------------------------------------------------------
    // Sorting
    // -------------------------------------------------------------------------

    public static function sort(array $array, ?callable $callback = null): array
    {
        $callback !== null ? usort($array, $callback) : asort($array);
        return $array;
    }

    public static function sortDesc(array $array, ?callable $callback = null): array
    {
        if ($callback !== null) {
            usort($array, fn($a, $b) => $callback($b, $a));
        } else {
            arsort($array);
        }
        return $array;
    }

    public static function sortRecursive(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = static::sortRecursive($value);
            }
        }
        static::isAssoc($array) ? ksort($array) : sort($array);
        return $array;
    }

    public static function shuffle(array $array): array
    {
        shuffle($array);
        return $array;
    }

    // -------------------------------------------------------------------------
    // Aggregation
    // -------------------------------------------------------------------------

    public static function each(array $array, callable $callback): array
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key) === false) break;
        }
        return $array;
    }

    public static function reduce(array $array, callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($array, $callback, $initial);
    }

    public static function sum(array $array): int|float
    {
        return array_sum($array);
    }

    public static function avg(array $array): float
    {
        if (empty($array)) return 0.0;
        return array_sum($array) / count($array);
    }

    public static function min(array $array): mixed
    {
        return min($array);
    }

    public static function max(array $array): mixed
    {
        return max($array);
    }

    public static function count(array $array): int
    {
        return count($array);
    }

    public static function countBy(array $array, ?callable $callback = null): array
    {
        $counts = [];
        foreach ($array as $value) {
            $key = $callback !== null ? $callback($value) : $value;
            $counts[(string)$key] = ($counts[(string)$key] ?? 0) + 1;
        }
        return $counts;
    }

    public static function some(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) return true;
        }
        return false;
    }

    public static function every(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (!$callback($value, $key)) return false;
        }
        return true;
    }

    public static function none(array $array, callable $callback): bool
    {
        return !static::some($array, $callback);
    }

    // -------------------------------------------------------------------------
    // Joining
    // -------------------------------------------------------------------------

    public static function join(array $array, string $glue, string $finalGlue = ''): string
    {
        if ($finalGlue === '') return implode($glue, $array);
        if (count($array) < 2) return implode('', $array);

        $last = array_pop($array);
        return implode($glue, $array) . $finalGlue . $last;
    }

    public static function implode(array $array, string $glue = ''): string
    {
        return implode($glue, $array);
    }

    // -------------------------------------------------------------------------
    // Slicing / chunking
    // -------------------------------------------------------------------------

    public static function chunk(array $array, int $size, bool $preserveKeys = false): array
    {
        return array_chunk($array, $size, $preserveKeys);
    }

    public static function take(array $array, int $limit): array
    {
        if ($limit < 0) return array_slice($array, $limit);
        return array_slice($array, 0, $limit);
    }

    public static function skip(array $array, int $count): array
    {
        return array_slice($array, $count);
    }

    public static function nth(array $array, int $step, int $offset = 0): array
    {
        $result = [];
        $position = 0;
        foreach ($array as $item) {
            if ($position % $step === $offset) $result[] = $item;
            $position++;
        }
        return $result;
    }

    public static function slice(array $array, int $offset, ?int $length = null, bool $preserveKeys = false): array
    {
        return array_slice($array, $offset, $length, $preserveKeys);
    }

    public static function random(array $array, ?int $number = null): mixed
    {
        if ($number === null) {
            return $array[array_rand($array)];
        }
        $keys = array_rand($array, $number);
        return array_values(array_intersect_key($array, array_flip((array)$keys)));
    }

    // -------------------------------------------------------------------------
    // Type checks
    // -------------------------------------------------------------------------

    public static function isAssoc(array $array): bool
    {
        if ($array === []) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }

    public static function isList(array $array): bool
    {
        return !static::isAssoc($array);
    }

    // -------------------------------------------------------------------------
    // Dot notation
    // -------------------------------------------------------------------------

    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }
        return $results;
    }

    public static function undot(array $array): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            static::set($results, $key, $value);
        }
        return $results;
    }

    // -------------------------------------------------------------------------
    // Set operations
    // -------------------------------------------------------------------------

    public static function unique(array $array): array
    {
        return array_unique($array);
    }

    public static function values(array $array): array
    {
        return array_values($array);
    }

    public static function keys(array $array): array
    {
        return array_keys($array);
    }

    public static function divide(array $array): array
    {
        return [array_keys($array), array_values($array)];
    }

    public static function zip(array ...$arrays): array
    {
        return array_map(null, ...$arrays);
    }

    public static function crossJoin(array ...$arrays): array
    {
        $results = [[]];
        foreach ($arrays as $array) {
            $append = [];
            foreach ($results as $product) {
                foreach ($array as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }
            $results = $append;
        }
        return $results;
    }

    public static function merge(array ...$arrays): array
    {
        return array_merge(...$arrays);
    }

    public static function mergeRecursive(array ...$arrays): array
    {
        return array_merge_recursive(...$arrays);
    }

    public static function reverse(array $array, bool $preserveKeys = false): array
    {
        return array_reverse($array, $preserveKeys);
    }

    public static function intersect(array ...$arrays): array
    {
        return array_intersect(...$arrays);
    }

    public static function diff(array ...$arrays): array
    {
        return array_diff(...$arrays);
    }

    // -------------------------------------------------------------------------
    // Conversion
    // -------------------------------------------------------------------------

    public static function toArray(mixed $value): array
    {
        if (is_array($value)) return $value;
        if ($value instanceof \Traversable) return iterator_to_array($value);
        if (is_object($value)) return (array)$value;
        return (array)$value;
    }

    public static function toObject(array $array): object
    {
        return json_decode((string)json_encode($array));
    }

    public static function fromPairs(array $pairs): array
    {
        $result = [];
        foreach ($pairs as [$key, $value]) {
            $result[$key] = $value;
        }
        return $result;
    }

    public static function toPairs(array $array): array
    {
        return array_map(null, array_keys($array), array_values($array));
    }

    public static function combine(array $keys, array $values): array
    {
        return array_combine($keys, $values) ?: [];
    }

    public static function pad(array $array, int $size, mixed $value = null): array
    {
        return array_pad($array, $size, $value);
    }

    public static function pipe(array $array, callable $callback): mixed
    {
        return $callback($array);
    }

    public static function tap(array $array, callable $callback): array
    {
        $callback($array);
        return $array;
    }

    public static function when(array $array, bool $condition, callable $callback, ?callable $default = null): array
    {
        if ($condition) return $callback($array);
        return $default !== null ? $default($array) : $array;
    }

    public static function unless(array $array, bool $condition, callable $callback, ?callable $default = null): array
    {
        return static::when($array, !$condition, $callback, $default);
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    public static function contains(array $array, mixed $item, ?callable $comparator = null): bool
    {
        if ($comparator !== null) {
            return static::some($array, fn($v) => $comparator($v, $item));
        }
        return in_array($item, $array);
    }

    public static function search(array $array, mixed $value, bool $strict = false): int|string|false
    {
        return array_search($value, $array, $strict);
    }
}
