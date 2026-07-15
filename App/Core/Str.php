<?php

declare(strict_types=1);

namespace Core;

class Str
{
    // -------------------------------------------------------------------------
    // Case conversion
    // -------------------------------------------------------------------------

    public static function camel(string $value): string
    {
        return lcfirst(static::studly($value));
    }

    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    public static function snake(string $value, string $delimiter = '_'): string
    {
        $value = preg_replace('/\s+/u', '', ucwords($value)) ?? $value;
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value) ?? $value;
        return strtolower($value);
    }

    public static function kebab(string $value): string
    {
        return static::snake($value, '-');
    }

    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    public static function headline(string $value): string
    {
        $parts = preg_split('/[_\-\s]+/u', $value) ?: [$value];
        $parts = array_map('ucfirst', $parts);
        return implode(' ', $parts);
    }

    // -------------------------------------------------------------------------
    // URL / slug
    // -------------------------------------------------------------------------

    public static function slug(string $value, string $separator = '-'): string
    {
        $value = mb_strtolower($value, 'UTF-8');

        // Transliterate accented characters
        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value) ?: $value;
        } elseif (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }

        $value = preg_replace('/[^a-z0-9\s\-\_]/i', '', $value) ?? $value;
        $value = preg_replace('/[\s\-\_]+/', $separator, $value) ?? $value;
        return trim($value, $separator);
    }

    public static function ascii(string $value): string
    {
        if (function_exists('transliterator_transliterate')) {
            return transliterator_transliterate('Any-Latin; Latin-ASCII', $value) ?: $value;
        }
        if (function_exists('iconv')) {
            $result = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            return $result !== false ? $result : $value;
        }
        return $value;
    }

    // -------------------------------------------------------------------------
    // Length / substring
    // -------------------------------------------------------------------------

    public static function length(string $value, ?string $encoding = null): int
    {
        return mb_strlen($value, $encoding ?? 'UTF-8');
    }

    public static function substr(string $string, int $start, ?int $length = null): string
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    public static function substrCount(string $haystack, string $needle): int
    {
        return substr_count($haystack, $needle);
    }

    public static function charAt(string $string, int $index): string
    {
        return mb_substr($string, $index, 1, 'UTF-8');
    }

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) return $value;
        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S+\s*+){1,' . $words . '}/u', $value, $matches);
        if (!isset($matches[0]) || static::length($value) === static::length($matches[0])) {
            return $value;
        }
        return rtrim($matches[0]) . $end;
    }

    public static function take(string $string, int $limit): string
    {
        if ($limit < 0) {
            return static::substr($string, $limit);
        }
        return static::substr($string, 0, $limit);
    }

    // -------------------------------------------------------------------------
    // Search / test
    // -------------------------------------------------------------------------

    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) return true;
        }
        return false;
    }

    public static function containsAll(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (!str_contains($haystack, $needle)) return false;
        }
        return true;
    }

    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) return true;
        }
        return false;
    }

    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) return true;
        }
        return false;
    }

    public static function is(string $pattern, string $value): bool
    {
        if ($pattern === $value) return true;
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);
        return (bool)preg_match('#^' . $pattern . '\z#u', $value);
    }

    public static function isJson(string $value): bool
    {
        if ($value === '') return false;
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function isUrl(string $value): bool
    {
        return (bool)filter_var($value, FILTER_VALIDATE_URL);
    }

    public static function isEmail(string $value): bool
    {
        return (bool)filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public static function isUuid(string $value): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $value);
    }

    // -------------------------------------------------------------------------
    // Manipulation
    // -------------------------------------------------------------------------

    public static function before(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strrpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    public static function after(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') return $subject;
        $pos = strrpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') return $subject;
        return static::beforeLast(static::after($subject, $from), $to);
    }

    public static function replace(array|string $search, array|string $replace, string $subject): string
    {
        return str_replace($search, $replace, $subject);
    }

    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') return $subject;
        $pos = strpos($subject, $search);
        if ($pos === false) return $subject;
        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') return $subject;
        $pos = strrpos($subject, $search);
        if ($pos === false) return $subject;
        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    public static function remove(array|string $search, string $subject, bool $caseSensitive = true): string
    {
        return $caseSensitive
            ? str_replace($search, '', $subject)
            : str_ireplace($search, '', $subject);
    }

    public static function finish(string $value, string $cap): string
    {
        return str_ends_with($value, $cap) ? $value : $value . $cap;
    }

    public static function start(string $value, string $prefix): string
    {
        return str_starts_with($value, $prefix) ? $value : $prefix . $value;
    }

    public static function wrap(string $value, string $before, ?string $after = null): string
    {
        return $before . $value . ($after ?? $before);
    }

    public static function unwrap(string $value, string $before, ?string $after = null): string
    {
        $after ??= $before;
        if (str_starts_with($value, $before) && str_ends_with($value, $after)) {
            return substr($value, strlen($before), -strlen($after));
        }
        return $value;
    }

    public static function reverse(string $value): string
    {
        return implode('', array_reverse(mb_str_split($value, 1, 'UTF-8')));
    }

    public static function repeat(string $string, int $times): string
    {
        return str_repeat($string, $times);
    }

    public static function squish(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
    }

    public static function mask(string $string, string $character, int $index, ?int $length = null): string
    {
        $segment = mb_substr($string, $index, $length, 'UTF-8');
        $start   = mb_substr($string, 0, $index, 'UTF-8');
        $end     = mb_substr($string, $index + mb_strlen($segment, 'UTF-8'), null, 'UTF-8');
        return $start . str_repeat($character, mb_strlen($segment, 'UTF-8')) . $end;
    }

    // -------------------------------------------------------------------------
    // Padding / trimming
    // -------------------------------------------------------------------------

    public static function padLeft(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_LEFT);
    }

    public static function padRight(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_RIGHT);
    }

    public static function pad(string $value, int $length, string $pad = ' ', int $type = STR_PAD_BOTH): string
    {
        return str_pad($value, $length, $pad, $type);
    }

    public static function trim(string $value, ?string $characters = null): string
    {
        return $characters !== null ? trim($value, $characters) : trim($value);
    }

    public static function ltrim(string $value, ?string $characters = null): string
    {
        return $characters !== null ? ltrim($value, $characters) : ltrim($value);
    }

    public static function rtrim(string $value, ?string $characters = null): string
    {
        return $characters !== null ? rtrim($value, $characters) : rtrim($value);
    }

    // -------------------------------------------------------------------------
    // Random / UUID
    // -------------------------------------------------------------------------

    public static function random(int $length = 16): string
    {
        $bytes = random_bytes((int)ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }

    public static function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // -------------------------------------------------------------------------
    // Pluralization (basic English rules)
    // -------------------------------------------------------------------------

    public static function plural(string $value, int $count = 2): string
    {
        if ($count === 1) return $value;

        $lower = strtolower($value);

        $irregulars = [
            'person' => 'people', 'man' => 'men', 'woman' => 'women',
            'child' => 'children', 'tooth' => 'teeth', 'foot' => 'feet',
            'mouse' => 'mice', 'goose' => 'geese', 'ox' => 'oxen',
            'leaf' => 'leaves', 'life' => 'lives', 'knife' => 'knives',
            'wife' => 'wives', 'half' => 'halves', 'self' => 'selves',
            'elf' => 'elves', 'loaf' => 'loaves', 'potato' => 'potatoes',
            'tomato' => 'tomatoes', 'cactus' => 'cacti', 'focus' => 'foci',
            'fungus' => 'fungi', 'nucleus' => 'nuclei', 'syllabus' => 'syllabi',
            'analysis' => 'analyses', 'diagnosis' => 'diagnoses', 'oasis' => 'oases',
            'thesis' => 'theses', 'crisis' => 'crises', 'criterion' => 'criteria',
            'phenomenon' => 'phenomena', 'datum' => 'data', 'medium' => 'media',
            'index' => 'indices', 'matrix' => 'matrices', 'vertex' => 'vertices',
        ];

        if (isset($irregulars[$lower])) {
            $plural = $irregulars[$lower];
            return ctype_upper($value[0]) ? ucfirst($plural) : $plural;
        }

        // Unchanged
        foreach (['sheep', 'fish', 'deer', 'series', 'species', 'moose', 'swine', 'bison', 'corps', 'means', 'scissors'] as $unchanged) {
            if ($lower === $unchanged) return $value;
        }

        // Rules
        if (preg_match('/(quiz)$/i', $value)) return preg_replace('/(quiz)$/i', '$1zes', $value) ?? $value;
        if (preg_match('/^(oxen)$/i', $value)) return $value;
        if (preg_match('/([m|l])ice$/i', $value)) return $value;
        if (preg_match('/(matr|vert|append)(ix|ices)$/i', $value)) return preg_replace('/(ix|ex)$/i', 'ices', $value) ?? $value;
        if (preg_match('/(x|ch|ss|sh)$/i', $value)) return preg_replace('/(x|ch|ss|sh)$/i', '$1es', $value) ?? $value;
        if (preg_match('/([^aeiouy]|qu)y$/i', $value)) return preg_replace('/([^aeiouy]|qu)y$/i', '$1ies', $value) ?? $value;
        if (preg_match('/(?:([^f])fe|([lr])f)$/i', $value)) return preg_replace('/(?:([^f])fe|([lr])f)$/i', '$1$2ves', $value) ?? $value;
        if (preg_match('/sis$/i', $value)) return preg_replace('/sis$/i', 'ses', $value) ?? $value;
        if (preg_match('/([ti])a$/i', $value)) return $value;
        if (preg_match('/(buffal|tomat)o$/i', $value)) return preg_replace('/(o)$/i', 'oes', $value) ?? $value;
        if (preg_match('/(bu)s$/i', $value)) return preg_replace('/(bu)s$/i', '$1ses', $value) ?? $value;
        if (preg_match('/(alias|status)$/i', $value)) return preg_replace('/(alias|status)$/i', '$1es', $value) ?? $value;
        if (preg_match('/(octop|vir)us$/i', $value)) return preg_replace('/(octop|vir)us$/i', '$1i', $value) ?? $value;
        if (preg_match('/(ax|test)is$/i', $value)) return preg_replace('/(ax|test)is$/i', '$1es', $value) ?? $value;
        if (preg_match('/s$/i', $value)) return $value;

        return $value . 's';
    }

    public static function singular(string $value): string
    {
        $lower = strtolower($value);

        $irregulars = [
            'people' => 'person', 'men' => 'man', 'women' => 'woman',
            'children' => 'child', 'teeth' => 'tooth', 'feet' => 'foot',
            'mice' => 'mouse', 'geese' => 'goose', 'oxen' => 'ox',
            'leaves' => 'leaf', 'lives' => 'life', 'knives' => 'knife',
            'wives' => 'wife', 'halves' => 'half', 'selves' => 'self',
            'elves' => 'elf', 'loaves' => 'loaf', 'potatoes' => 'potato',
            'tomatoes' => 'tomato', 'cacti' => 'cactus', 'foci' => 'focus',
            'fungi' => 'fungus', 'nuclei' => 'nucleus', 'syllabi' => 'syllabus',
            'analyses' => 'analysis', 'diagnoses' => 'diagnosis', 'oases' => 'oasis',
            'theses' => 'thesis', 'crises' => 'crisis', 'criteria' => 'criterion',
            'phenomena' => 'phenomenon', 'data' => 'datum', 'media' => 'medium',
            'indices' => 'index', 'matrices' => 'matrix', 'vertices' => 'vertex',
        ];

        if (isset($irregulars[$lower])) {
            $singular = $irregulars[$lower];
            return ctype_upper($value[0]) ? ucfirst($singular) : $singular;
        }

        foreach (['sheep', 'fish', 'deer', 'series', 'species', 'moose', 'swine', 'bison', 'corps', 'means', 'scissors'] as $unchanged) {
            if ($lower === $unchanged) return $value;
        }

        if (preg_match('/(quiz)zes$/i', $value)) return preg_replace('/(quiz)zes$/i', '$1', $value) ?? $value;
        if (preg_match('/(matr)ices$/i', $value)) return preg_replace('/(matr)ices$/i', '$1ix', $value) ?? $value;
        if (preg_match('/(vert|ind)ices$/i', $value)) return preg_replace('/(vert|ind)ices$/i', '$1ex', $value) ?? $value;
        if (preg_match('/^(ox)en/i', $value)) return preg_replace('/^(ox)en/i', '$1', $value) ?? $value;
        if (preg_match('/(alias|status)es$/i', $value)) return preg_replace('/(alias|status)es$/i', '$1', $value) ?? $value;
        if (preg_match('/(octop|vir)i$/i', $value)) return preg_replace('/(octop|vir)i$/i', '$1us', $value) ?? $value;
        if (preg_match('/(cris|ax|test)es$/i', $value)) return preg_replace('/(cris|ax|test)es$/i', '$1is', $value) ?? $value;
        if (preg_match('/(shoe)s$/i', $value)) return preg_replace('/(shoe)s$/i', '$1', $value) ?? $value;
        if (preg_match('/(o)es$/i', $value)) return preg_replace('/(o)es$/i', '$1', $value) ?? $value;
        if (preg_match('/(bus)es$/i', $value)) return preg_replace('/(bus)es$/i', '$1', $value) ?? $value;
        if (preg_match('/([m|l])ice$/i', $value)) return preg_replace('/([m|l])ice$/i', '$1ouse', $value) ?? $value;
        if (preg_match('/(x|ch|ss|sh)es$/i', $value)) return preg_replace('/(x|ch|ss|sh)es$/i', '$1', $value) ?? $value;
        if (preg_match('/(m)ovies$/i', $value)) return preg_replace('/(m)ovies$/i', '$1ovie', $value) ?? $value;
        if (preg_match('/(s)eries$/i', $value)) return preg_replace('/(s)eries$/i', '$1eries', $value) ?? $value;
        if (preg_match('/([^aeiouy]|qu)ies$/i', $value)) return preg_replace('/([^aeiouy]|qu)ies$/i', '$1y', $value) ?? $value;
        if (preg_match('/([lr])ves$/i', $value)) return preg_replace('/([lr])ves$/i', '$1f', $value) ?? $value;
        if (preg_match('/(thi|shea|lea)ves$/i', $value)) return preg_replace('/(thi|shea|lea)ves$/i', '$1f', $value) ?? $value;
        if (preg_match('/(hea)ves$/i', $value)) return preg_replace('/(hea)ves$/i', '$1ve', $value) ?? $value;
        if (preg_match('/(^analy)ses$/i', $value)) return preg_replace('/(^analy)ses$/i', '$1sis', $value) ?? $value;
        if (preg_match('/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i', $value)) {
            return preg_replace('/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i', '$1$2sis', $value) ?? $value;
        }
        if (preg_match('/([ti])a$/i', $value)) return preg_replace('/([ti])a$/i', '$1um', $value) ?? $value;
        if (preg_match('/(database)s$/i', $value)) return preg_replace('/(database)s$/i', '$1', $value) ?? $value;
        if (preg_match('/s$/i', $value) && !preg_match('/ss$/i', $value)) {
            return preg_replace('/s$/i', '', $value) ?? $value;
        }

        return $value;
    }
}
