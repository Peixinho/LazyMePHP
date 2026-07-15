---
sidebar_position: 18
---

# Debug Profiler & Toolbar

LazyMePHP includes a visual profiler that overlays a collapsible toolbar at the bottom of every page in development, showing query counts, cache hits, render times, and a colour-coded timeline.

## Enabling

```ini
# .env
APP_DEBUG=true
```

The toolbar renders automatically when `APP_DEBUG=true`. It is never shown in production.

## Timeline tab

The **Timeline** tab shows a visual swimlane of every profiled span, grouped by category:

| Category | Colour | What it covers |
|---|---|---|
| `boot` | purple | Framework bootstrap |
| `db` | red | SQL queries (auto-instrumented) |
| `cache` | teal | Cache get/set/remember (auto-instrumented) |
| `render` | green | Blade view rendering (auto-instrumented) |
| `http` | blue | Outbound HTTP requests |
| `queue` | orange | Job dispatches |
| `auth` | yellow | Auth checks |
| `event` | pink | Event dispatches |
| `app` | grey | Your custom spans |

Each span shows its label on hover and its duration in milliseconds.

## Manual instrumentation

Add your own spans anywhere in application code:

```php
use Core\Debug\Profiler;

// Start a span
Profiler::start('app', 'build-report');
$report = buildReport($data);
Profiler::stop();

// Or use the closure helper (auto start/stop)
$result = Profiler::measure('app', 'expensive-calculation', function () {
    return expensiveCalculation();
});
```

## Reading profiler data

```php
$spans    = Profiler::spans();       // all completed spans, sorted by start time
$totalMs  = Profiler::totalMs();     // total request time so far

foreach ($spans as $span) {
    $ms = Profiler::spanToMs($span);
    echo "{$ms['category']} / {$ms['label']}: {$ms['durationMs']}ms\n";
}
```

## What's auto-instrumented

| Layer | Instrumented operations |
|---|---|
| **Database** | Every SQL query via `ISQL::query()` |
| **Cache** | `Cache::get()`, `Cache::set()`, `Cache::remember()` |
| **Rendering** | `BladeFactory::render()` (all AutoRouter views) |

## Profiler API

```php
Profiler::init(float $startTime);          // called by framework on boot
Profiler::start(string $category, string $label, array $meta = []);
Profiler::stop();                          // stops the most recently started span
Profiler::measure(string $category, string $label, callable $fn): mixed;
Profiler::spans(): array;                  // completed spans
Profiler::requestStart(): float;           // microtime(true) of request start
Profiler::totalMs(): float;
Profiler::reset();                         // clear all spans (useful in tests)
```

## Debug bar tabs

| Tab | Contents |
|---|---|
| **Timeline** | Visual span swimlane |
| **Queries** | All SQL queries with execution time |
| **Cache** | Cache hits, misses, and sets |
| **Request** | `$_GET`, `$_POST`, `$_SERVER`, headers |
| **Session** | Active session data |
| **Errors** | PHP errors and exceptions caught this request |
