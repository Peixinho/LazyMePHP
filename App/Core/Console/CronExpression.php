<?php

declare(strict_types=1);

namespace Core\Console;

/**
 * Minimal cron expression matcher supporting:
 *   *   — any value
 *   N   — exact value
 *   N/S — every S starting from N  (e.g. *\/5 = every 5)
 *   N-M — range (inclusive)
 *   N,M — list
 */
class CronExpression
{
    public static function matches(string $expression, \DateTimeInterface $dt): bool
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) return false;

        [$minute, $hour, $day, $month, $weekday] = $parts;

        return self::fieldMatches($minute,  (int)$dt->format('i'))
            && self::fieldMatches($hour,    (int)$dt->format('G'))
            && self::fieldMatches($day,     (int)$dt->format('j'))
            && self::fieldMatches($month,   (int)$dt->format('n'))
            && self::fieldMatches($weekday, (int)$dt->format('w'));
    }

    private static function fieldMatches(string $field, int $value): bool
    {
        foreach (explode(',', $field) as $part) {
            if (self::partMatches($part, $value)) return true;
        }
        return false;
    }

    private static function partMatches(string $part, int $value): bool
    {
        if ($part === '*') return true;

        // Step: */N or start/N
        if (str_contains($part, '/')) {
            [$range, $step] = explode('/', $part, 2);
            $step = (int)$step;
            if ($step <= 0) return false;
            $start = ($range === '*') ? 0 : (int)$range;
            return $value >= $start && ($value - $start) % $step === 0;
        }

        // Range: N-M
        if (str_contains($part, '-')) {
            [$from, $to] = explode('-', $part, 2);
            return $value >= (int)$from && $value <= (int)$to;
        }

        // Exact
        return $value === (int)$part;
    }
}
