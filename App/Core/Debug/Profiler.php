<?php

declare(strict_types=1);

namespace Core\Debug;

/**
 * Lightweight request profiler.
 *
 * Records labelled spans with start/end timestamps so the DebugToolbar
 * can render a visual timeline.
 *
 *   Profiler::start('db', 'SELECT * FROM users');
 *   // ... do work
 *   Profiler::stop();
 *
 *   // Or wrap a callable:
 *   $result = Profiler::measure('render', 'home.blade', fn() => $view->render());
 */
class Profiler
{
    private static float $requestStart = 0.0;

    /** @var list<array{label:string,category:string,start:float,end:float,depth:int,meta:array}> */
    private static array $spans = [];

    /** @var list<array{label:string,category:string,start:float,depth:int}> open span stack */
    private static array $stack = [];

    public static function init(float $startTime = 0.0): void
    {
        self::$requestStart = $startTime > 0.0 ? $startTime : microtime(true);
        self::$spans        = [];
        self::$stack        = [];
    }

    /** Open a new span. Nests inside any currently-open span. */
    public static function start(string $category, string $label = '', array $meta = []): void
    {
        if (self::$requestStart === 0.0) self::init();

        self::$stack[] = [
            'label'    => $label ?: $category,
            'category' => $category,
            'start'    => microtime(true),
            'depth'    => count(self::$stack),
            'meta'     => $meta,
        ];
    }

    /** Close the most-recently opened span. */
    public static function stop(): void
    {
        if (empty(self::$stack)) return;
        $entry        = array_pop(self::$stack);
        $entry['end'] = microtime(true);
        self::$spans[] = $entry;
    }

    /**
     * Run a callable inside a span and return its result.
     *
     *   $users = Profiler::measure('db', 'SELECT users', fn() => $db->query('SELECT * FROM users'));
     */
    public static function measure(string $category, string $label, callable $callback): mixed
    {
        self::start($category, $label);
        try {
            return $callback();
        } finally {
            self::stop();
        }
    }

    /** All completed spans, sorted by start time. */
    public static function spans(): array
    {
        // Close any leaked open spans
        $now    = microtime(true);
        $leaked = self::$stack;
        $all    = self::$spans;
        foreach (array_reverse($leaked) as $s) {
            $s['end'] = $now;
            $all[]    = $s;
        }
        usort($all, fn($a, $b) => $a['start'] <=> $b['start']);
        return $all;
    }

    /** Request start timestamp (microtime float). */
    public static function requestStart(): float
    {
        return self::$requestStart;
    }

    /** Total elapsed milliseconds since request start. */
    public static function totalMs(): float
    {
        return (microtime(true) - self::$requestStart) * 1000;
    }

    /** Convert a span's absolute timestamps to ms-offsets from request start. */
    public static function spanToMs(array $span): array
    {
        $origin      = self::$requestStart;
        $span['startMs'] = ($span['start'] - $origin) * 1000;
        $span['endMs']   = ($span['end']   - $origin) * 1000;
        $span['durationMs'] = $span['endMs'] - $span['startMs'];
        return $span;
    }

    public static function reset(): void
    {
        self::$spans       = [];
        self::$stack       = [];
        self::$requestStart = 0.0;
    }
}
