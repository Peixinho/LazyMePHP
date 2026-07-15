<?php

/**
 * Debug Toolbar for LazyMePHP
 * 
 * Provides real-time debugging information including queries, memory usage,
 * execution time, and request information.
 * 
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Debug;

use Core\LazyMePHP;

class DebugToolbar
{
    private static ?DebugToolbar $instance = null;
    private array $queries = [];
    private float $startTime;
    private int $startMemory;
    private array $requestInfo = [];
    private bool $enabled = false;
    
    private function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
        $this->enabled = LazyMePHP::DEBUG_MODE();
        $this->collectRequestInfo();
    }
    
    public static function getInstance(): DebugToolbar
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Add a query to the debug log (for development only)
     */
    public function addQuery(string $sql, float $time, array $params = []): void
    {
        if (!$this->enabled) return;
        
        // Skip logging system queries to keep debug toolbar clean
        $sqlUpper = strtoupper(trim($sql));
        if (strpos($sqlUpper, 'INSERT INTO __LOG_') === 0 || 
            strpos($sqlUpper, 'SELECT') === 0 && strpos($sqlUpper, '__LOG_') !== false ||
            strpos($sqlUpper, 'SHOW TABLES LIKE') === 0 ||
            strpos($sqlUpper, 'DESCRIBE __LOG_') === 0 ||
            strpos($sqlUpper, 'COUNT(*) FROM __LOG_') !== false) {
            return;
        }
        
        $this->queries[] = [
            'sql' => $sql,
            'time' => $time,
            'params' => $params,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Collect request information
     */
    private function collectRequestInfo(): void
    {
        $headers = [];
        if (function_exists('\getallheaders')) {
            try {
                $headers = \getallheaders() ?: [];
            } catch (\Throwable $e) {
                $headers = [];
            }
        }
        
        $this->requestInfo = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'headers' => $headers,
            'get_params' => $_GET ?? [],
            'post_params' => $_POST ?? [],
            'timestamp' => time()
        ];
    }
    
    /**
     * Get execution time
     */
    public function getExecutionTime(): float
    {
        return microtime(true) - $this->startTime;
    }
    
    /**
     * Get memory usage
     */
    public function getMemoryUsage(): array
    {
        $current = memory_get_usage();
        $peak = memory_get_peak_usage();
        
        return [
            'current' => $this->formatBytes($current),
            'peak' => $this->formatBytes($peak),
            'current_bytes' => $current,
            'peak_bytes' => $peak
        ];
    }
    
    /**
     * Get all collected data
     */
    public function getDebugData(): array
    {
        $spans = class_exists(\Core\Debug\Profiler::class)
            ? array_map([\Core\Debug\Profiler::class, 'spanToMs'], \Core\Debug\Profiler::spans())
            : [];
        $totalMs = class_exists(\Core\Debug\Profiler::class)
            ? \Core\Debug\Profiler::totalMs()
            : $this->getExecutionTime() * 1000;

        return [
            'queries' => $this->queries,
            'errors' => \Core\Helpers\ErrorUtil::getCurrentRequestErrors(),
            'execution_time' => $this->getExecutionTime(),
            'memory' => $this->getMemoryUsage(),
            'request' => $this->requestInfo,
            'php_version' => PHP_VERSION,
            'lazymephp_version' => '1.0.0',
            'profiler_spans' => $spans,
            'profiler_total_ms' => $totalMs,
        ];
    }
    
    /**
     * Render the debug toolbar HTML
     */
    public function render(): string
    {
        if (!$this->enabled) return '';
        
        $data = $this->getDebugData();
        $errorCount = count($data['errors']);
        
        return $this->generateToolbarHTML($data, $errorCount);
    }
    
    /**
     * Generate the toolbar HTML
     */
    private function generateToolbarHTML(array $data, int $errorCount): string
    {
        $executionTime = number_format($data['execution_time'] * 1000, 2);
        $memoryUsage = $data['memory']['current'];
        $queryCount = count($data['queries']);
        
        // Add fallback error indicator if there are errors
        $errorIndicator = '';
        if ($errorCount > 0) {
            $errorLabel = $errorCount === 1 ? 'erro' : 'erros';
            $errorIndicator = "
            <div class='lazymephp-debug-error-indicator' id='lazymephp-debug-error-indicator' role='button' tabindex='0'>
                â� ï¸� {$errorCount} {$errorLabel} â�� Clique ou Ctrl+Shift+D
            </div>
            ";
        }

        if ($errorCount > 0) {
            $toolbarClasses = 'lazymephp-debug-toolbar lazymephp-debug-toolbar--expanded';
            $contentHidden = '';
        } else {
            $toolbarClasses = 'lazymephp-debug-toolbar lazymephp-debug-toolbar--minimized';
            $contentHidden = ' hidden';
        }
        
        return "
        {$errorIndicator}
        <div id='lazymephp-debug-toolbar' class='{$toolbarClasses}' data-error-count='{$errorCount}' data-restore-state='1'>
            <div class='lazymephp-debug-header' id='lazymephp-debug-header' title='Clique para expandir ou recolher Â· Ctrl+Shift+D'>
                <span class='lazymephp-debug-title'>ð��� LazyMePHP Debug</span>
                <div class='lazymephp-debug-stats'>
                    <span class='lazymephp-debug-stat lazymephp-debug-stat--time' title='Execution Time'>{$executionTime}ms</span>
                    <span class='lazymephp-debug-stat lazymephp-debug-stat--memory' title='Memory Usage'>{$memoryUsage}</span>
                    <span class='lazymephp-debug-stat lazymephp-debug-stat--queries' title='SQL Queries'>{$queryCount} SQL</span>
                    <span class='lazymephp-debug-stat lazymephp-debug-stat--errors" . ($errorCount > 0 ? ' lazymephp-debug-stat--errors-active' : '') . "' title='Errors'>{$errorCount} err</span>
                </div>
                <span class='lazymephp-debug-shortcut' title='Toggle panel'>â�¨ Ctrl+Shift+D</span>
                <span class='lazymephp-debug-chevron lazymephp-debug-icon-btn' aria-hidden='true' title='Clique para expandir / recolher'></span>
                <button class='lazymephp-debug-dock' type='button' title='Ocultar barra de debug' aria-label='Ocultar barra de debug'>
                    <span class='lazymephp-debug-dock__open'>Ocultar</span>
                    <span class='lazymephp-debug-dock__closed'>Debug</span>
                </button>
            </div>
            <div id='lazymephp-debug-content' class='lazymephp-debug-content'{$contentHidden}>
                " . $this->generateTabs($data, $errorCount) . "
            </div>
        </div>
        " . $this->generateToolbarCSS() . "
        " . $this->generateToolbarJS() . "
        ";
    }
    
    /**
     * Generate tabs for different debug sections
     */
    private function generateTabs(array $data, int $errorCount): string
    {
        $queryCount = count($data['queries']);
        $defaultTab = $errorCount > 0 ? 'errors' : 'timeline';
        $tabs = [
            'timeline'    => ['label' => 'Timeline',               'icon' => '&#9889;'],
            'queries'     => ['label' => "Queries ({$queryCount})", 'icon' => '&#128451;'],
            'errors'      => ['label' => "Errors ({$errorCount})",  'icon' => '&#9888;'],
            'request'     => ['label' => 'Request',                'icon' => '&#128161;'],
            'performance' => ['label' => 'Performance',            'icon' => '&#128202;'],
        ];
        
        $tabContent = '';
        $tabButtons = '';
        
        foreach ($tabs as $tabId => $tab) {
            $active = $tabId === $defaultTab ? 'active' : '';
            $tabButtons .= "<button class='lazymephp-debug-tab {$active}' type='button' data-tab-id='{$tabId}'>{$tab['icon']} {$tab['label']}</button>";
            
            $content = $this->generateTabContent($tabId, $data);
            $hidden = $tabId === $defaultTab ? '' : ' hidden';
            $tabContent .= "<div id='lazymephp-debug-tab-{$tabId}' class='lazymephp-debug-tab-content'{$hidden}>{$content}</div>";
        }
        
        return "
        <div class='lazymephp-debug-tabs'>{$tabButtons}</div>
        <div class='lazymephp-debug-tab-panels'>{$tabContent}</div>
        ";
    }
    
    /**
     * Generate content for each tab
     */
    private function generateTabContent(string $tabId, array $data): string
    {
        return match ($tabId) {
            'timeline'    => $this->generateTimelineTab($data),
            'queries'     => $this->generateQueriesTab($data['queries']),
            'errors'      => $this->generateErrorsTab($data['errors']),
            'request'     => $this->generateRequestTab($data['request']),
            'performance' => $this->generatePerformanceTab($data),
            default       => '',
        };
    }

    // -------------------------------------------------------------------------
    // Visual profiling timeline
    // -------------------------------------------------------------------------

    private function generateTimelineTab(array $data): string
    {
        $totalMs = $data['profiler_total_ms'] ?? ($data['execution_time'] * 1000);
        $spans   = $data['profiler_spans'] ?? [];
        $queries = $data['queries'] ?? [];

        // Synthesise DB spans from DebugToolbar query log if Profiler has no spans
        if (empty($spans) && !empty($queries)) {
            $reqStart = microtime(true) - $data['execution_time'];
            $offset   = 10.0; // estimate: first query at 10ms
            foreach ($queries as $q) {
                $durMs    = $q['time'] * 1000;
                $ts       = $q['timestamp'] ?? 0;
                $startMs  = $ts > 0 ? ($ts - $reqStart) * 1000 : $offset;
                $spans[]  = [
                    'category'   => 'db',
                    'label'      => substr($q['sql'], 0, 80),
                    'startMs'    => $startMs,
                    'endMs'      => $startMs + $durMs,
                    'durationMs' => $durMs,
                    'depth'      => 0,
                ];
                $offset += $durMs + 5;
            }
        }

        // Sort by startMs
        usort($spans, fn($a, $b) => ($a['startMs'] ?? 0) <=> ($b['startMs'] ?? 0));

        $totalDisplay = number_format($totalMs, 1);
        $queryTotal   = array_sum(array_map(fn($q) => $q['time'] * 1000, $queries));
        $appMs        = $totalMs - $queryTotal;

        $timelineHtml = $this->renderTimeline($spans, $totalMs);
        $legendHtml   = $this->renderLegend($spans);
        $summaryHtml  = $this->renderProfilerSummary($spans, $totalMs, $queries);

        return <<<HTML
        <div class="lm-timeline-wrap">
            <div class="lm-timeline-summary">{$summaryHtml}</div>
            <div class="lm-timeline-container">
                <div class="lm-timeline-scale">
                    <span>0ms</span>
                    <span>{$totalDisplay}ms</span>
                </div>
                {$timelineHtml}
            </div>
            <div class="lm-legend">{$legendHtml}</div>
            <div class="lm-span-list">{$this->renderSpanList($spans)}</div>
        </div>
        HTML;
    }

    private function renderTimeline(array $spans, float $totalMs): string
    {
        if ($totalMs <= 0) $totalMs = 1;

        // Bucket spans by depth
        $rows = [];
        foreach ($spans as $span) {
            $depth         = (int)($span['depth'] ?? 0);
            $rows[$depth][] = $span;
        }
        ksort($rows);

        // Always have at least one row (app background)
        $html = '<div class="lm-timeline-track">';

        // Background "app" bar
        $html .= '<div class="lm-timeline-row lm-timeline-row--bg">';
        $html .= '<span class="lm-span lm-span--app" style="left:0%;width:100%" title="Total request: ' . number_format($totalMs, 1) . 'ms"></span>';
        $html .= '</div>';

        // One row per depth
        foreach ($rows as $depth => $depthSpans) {
            $html .= '<div class="lm-timeline-row">';
            foreach ($depthSpans as $span) {
                $startPct = ($totalMs > 0) ? min(100, ($span['startMs'] / $totalMs) * 100) : 0;
                $widthPct = ($totalMs > 0) ? min(100 - $startPct, ($span['durationMs'] / $totalMs) * 100) : 0;
                $widthPct = max(0.3, $widthPct); // minimum visible width
                $cat      = htmlspecialchars($span['category'] ?? 'app', ENT_QUOTES);
                $label    = htmlspecialchars(substr($span['label'] ?? $cat, 0, 60), ENT_QUOTES);
                $dur      = number_format($span['durationMs'] ?? 0, 2);
                $title    = "{$cat}: {$label} ({$dur}ms)";
                $html    .= sprintf(
                    '<span class="lm-span lm-span--%s" style="left:%.3f%%;width:%.3f%%" title="%s" data-label="%s" data-dur="%sms"></span>',
                    $cat, $startPct, $widthPct, $title, $label, $dur
                );
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderLegend(array $spans): string
    {
        $cats = array_unique(array_column($spans, 'category'));
        sort($cats);
        $items = '';
        foreach (['boot', 'app', 'db', 'cache', 'render', 'http', 'queue', 'auth', 'event'] as $cat) {
            if (in_array($cat, $cats, true) || $cat === 'app') {
                $items .= "<span class=\"lm-legend-item lm-legend-item--{$cat}\"><span class=\"lm-legend-dot\"></span>{$cat}</span>";
            }
        }
        return $items;
    }

    private function renderProfilerSummary(array $spans, float $totalMs, array $queries): string
    {
        $dbMs   = array_sum(array_map(fn($q) => $q['time'] * 1000, $queries));
        $spanMs = array_sum(array_map(fn($s) => $s['durationMs'] ?? 0, $spans));
        $mem    = $this->getMemoryUsage();

        $items = [
            ['label' => 'Total',   'value' => number_format($totalMs, 1) . 'ms',   'cls' => 'app'],
            ['label' => 'DB',      'value' => number_format($dbMs, 1) . 'ms',      'cls' => 'db'],
            ['label' => 'App',     'value' => number_format(max(0, $totalMs - $dbMs), 1) . 'ms', 'cls' => 'render'],
            ['label' => 'Queries', 'value' => count($queries),                      'cls' => 'db'],
            ['label' => 'Memory',  'value' => $mem['peak'],                         'cls' => 'cache'],
            ['label' => 'PHP',     'value' => PHP_VERSION,                          'cls' => 'boot'],
        ];

        $html = '';
        foreach ($items as $item) {
            $html .= "<div class=\"lm-summary-item lm-summary-item--{$item['cls']}\"><span class=\"lm-summary-label\">{$item['label']}</span><span class=\"lm-summary-value\">{$item['value']}</span></div>";
        }
        return $html;
    }

    private function renderSpanList(array $spans): string
    {
        if (empty($spans)) {
            return '<div class="lm-span-list-empty">No profiler spans recorded. Call <code>Profiler::start(\'category\', \'label\')</code> to instrument your code.</div>';
        }
        $html = '<table class="lm-span-table"><thead><tr><th>Category</th><th>Label</th><th>Duration</th><th>Start</th></tr></thead><tbody>';
        foreach ($spans as $span) {
            $cat   = htmlspecialchars($span['category'] ?? 'app', ENT_QUOTES);
            $label = htmlspecialchars(substr($span['label'] ?? '', 0, 100), ENT_QUOTES);
            $dur   = number_format($span['durationMs'] ?? 0, 2);
            $start = number_format($span['startMs'] ?? 0, 2);
            $html .= "<tr><td><span class=\"lm-badge lm-badge--{$cat}\">{$cat}</span></td><td class=\"lm-span-label\">{$label}</td><td class=\"lm-dur\">{$dur}ms</td><td class=\"lm-start\">{$start}ms</td></tr>";
        }
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Generate queries tab content
     */
    private function generateQueriesTab(array $queries): string
    {
        if (empty($queries)) {
            return '<div class="lazymephp-debug-empty">No queries logged</div>';
        }
        
        $html = '';
        foreach ($queries as $index => $query) {
            $sql = htmlspecialchars($query['sql'], ENT_QUOTES, 'UTF-8');
            $time = number_format($query['time'] * 1000, 2);
            $params = is_array($query['params']) ? json_encode($query['params'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : htmlspecialchars((string) $query['params'], ENT_QUOTES, 'UTF-8');
            $blockId = 'lazymephp-debug-query-' . $index;
            
            $html .= "
            <div class='lazymephp-debug-query'>
                <div class='lazymephp-debug-block-header'>
                    <span class='lazymephp-debug-query-time'>{$time}ms</span>
                    <button type='button' class='lazymephp-debug-copy-btn' data-copy-target='{$blockId}' title='Copy SQL'>ð��� Copy</button>
                </div>
                <pre id='{$blockId}' class='lazymephp-debug-copyable lazymephp-debug-query-sql'>{$sql}</pre>
                <div class='lazymephp-debug-query-params lazymephp-debug-copyable'>{$params}</div>
            </div>
            ";
        }
        
        return $html;
    }
    
    /**
     * Generate errors tab content
     */
    private function generateErrorsTab(array $errors): string
    {
        if (empty($errors)) {
            return '<div class="lazymephp-debug-empty">No errors logged</div>';
        }
        
        $html = '';
        foreach ($errors as $index => $error) {
            $message = htmlspecialchars($error['message'] ?? '', ENT_QUOTES, 'UTF-8');
            $file = htmlspecialchars($error['file'] ?? '', ENT_QUOTES, 'UTF-8');
            $line = (int) ($error['line'] ?? 0);
            $errorId = htmlspecialchars($error['error_id'] ?? '', ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars($error['type'] ?? '', ENT_QUOTES, 'UTF-8');
            $timestamp = htmlspecialchars($error['timestamp'] ?? '', ENT_QUOTES, 'UTF-8');
            $blockId = 'lazymephp-debug-error-' . $index;

            $copyText = ($error['message'] ?? '') . "\n"
                . ($error['file'] ?? '') . ':' . ($error['line'] ?? '') . "\n"
                . (isset($error['error_id']) ? 'Error ID: ' . $error['error_id'] . "\n" : '')
                . (isset($error['type']) ? 'Type: ' . $error['type'] . "\n" : '');
            $copyTextEscaped = htmlspecialchars($copyText, ENT_QUOTES, 'UTF-8');
            
            $html .= "
            <div class='lazymephp-debug-error'>
                <div class='lazymephp-debug-block-header'>
                    <span class='lazymephp-debug-error-badge'>" . ($errorId !== '' ? "ID {$errorId}" : 'Error') . "</span>
                    <button type='button' class='lazymephp-debug-copy-btn' data-copy-text='{$copyTextEscaped}' data-copy-target='{$blockId}' title='Copy error'>ð��� Copy</button>
                </div>
                <pre id='{$blockId}' class='lazymephp-debug-copyable lazymephp-debug-error-body'>{$message}\n\n{$file}:{$line}" . ($type !== '' ? "\n\nType: {$type}" : '') . ($timestamp !== '' ? "\nTime: {$timestamp}" : '') . "</pre>
            </div>
            ";
        }
        
        return $html;
    }
    
    /**
     * Generate request tab content
     */
    private function generateRequestTab(array $request): string
    {
        $html = '';
        
        $sections = [
            'Basic Info' => [
                'Method' => $request['method'],
                'URI' => $request['uri'],
                'IP' => $request['ip'],
                'User Agent' => substr($request['user_agent'], 0, 100) . (strlen($request['user_agent']) > 100 ? '...' : '')
            ],
            'GET Parameters' => $request['get_params'],
            'POST Parameters' => $request['post_params'],
            'Session Data' => $_SESSION ?? [],
            'Cookies' => $_COOKIE ?? []
        ];
        
        foreach ($sections as $title => $data) {
            if (empty($data)) continue;
            
            $html .= "<h4>{$title}</h4>";
            $html .= "<div class='lazymephp-debug-section'>";
            
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $key = htmlspecialchars($key);
                    $value = is_array($value) ? json_encode($value) : htmlspecialchars($value);
                    $html .= "<div class='lazymephp-debug-item'><strong>{$key}:</strong> {$value}</div>";
                }
            } else {
                $html .= "<div class='lazymephp-debug-item'>{$data}</div>";
            }
            
            $html .= "</div>";
        }
        
        return $html;
    }
    
    /**
     * Generate performance tab content
     */
    private function generatePerformanceTab(array $data): string
    {
        $executionTime = number_format($data['execution_time'] * 1000, 2);
        $memoryCurrent = $data['memory']['current'];
        $memoryPeak = $data['memory']['peak'];
        
        return "
        <div class='lazymephp-debug-performance'>
            <div class='lazymephp-debug-perf-item'>
                <strong>Execution Time:</strong> {$executionTime}ms
            </div>
            <div class='lazymephp-debug-perf-item'>
                <strong>Memory Usage:</strong> {$memoryCurrent} (Peak: {$memoryPeak})
            </div>
            <div class='lazymephp-debug-perf-item'>
                <strong>PHP Version:</strong> {$data['php_version']}
            </div>
            <div class='lazymephp-debug-perf-item'>
                <strong>LazyMePHP Version:</strong> {$data['lazymephp_version']}
            </div>
        </div>
        ";
    }
    
    /**
     * Generate toolbar CSS
     */
    private function generateToolbarCSS(): string
    {
        return "
        <style id='lazymephp-debug-styles'>
        @media print {
            .lazymephp-debug-toolbar,
            .lazymephp-debug-error-indicator,
            #lazymephp-debug-toolbar,
            #lazymephp-debug-error-indicator {
                display: none !important;
            }
        }

        /* ── Profiling Timeline ───────────────────────────────────────── */
        :root {
            --lm-boot:   #6b7280;
            --lm-app:    #14b8a6;
            --lm-db:     #3b82f6;
            --lm-cache:  #f59e0b;
            --lm-render: #10b981;
            --lm-http:   #8b5cf6;
            --lm-queue:  #f97316;
            --lm-auth:   #ec4899;
            --lm-event:  #ef4444;
        }
        .lm-timeline-wrap { padding: 12px 16px; color: #e2e8f0; }
        .lm-timeline-summary {
            display: flex; gap: 12px; flex-wrap: wrap;
            margin-bottom: 14px; padding-bottom: 10px;
            border-bottom: 1px solid #334155;
        }
        .lm-summary-item {
            display: flex; flex-direction: column; align-items: center;
            padding: 6px 12px; border-radius: 6px; min-width: 70px;
            background: #1e293b;
        }
        .lm-summary-label { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; }
        .lm-summary-value { font-size: 14px; font-weight: 700; margin-top: 2px; }
        .lm-summary-item--app   .lm-summary-value { color: var(--lm-app); }
        .lm-summary-item--db    .lm-summary-value { color: var(--lm-db); }
        .lm-summary-item--cache .lm-summary-value { color: var(--lm-cache); }
        .lm-summary-item--render .lm-summary-value { color: var(--lm-render); }
        .lm-summary-item--boot  .lm-summary-value { color: var(--lm-boot); }

        .lm-timeline-container {
            background: #0f172a; border-radius: 8px;
            padding: 10px 14px; margin-bottom: 10px;
            border: 1px solid #1e293b;
        }
        .lm-timeline-scale {
            display: flex; justify-content: space-between;
            font-size: 10px; color: #64748b; margin-bottom: 6px;
            font-family: monospace;
        }
        .lm-timeline-track { display: flex; flex-direction: column; gap: 3px; }
        .lm-timeline-row {
            position: relative; height: 18px;
            background: #1e293b; border-radius: 3px; overflow: hidden;
        }
        .lm-timeline-row--bg { height: 12px; opacity: .35; }
        .lm-span {
            position: absolute; top: 0; height: 100%; border-radius: 2px;
            cursor: default; transition: opacity .15s;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
        }
        .lm-span:hover { opacity: .85; outline: 1px solid rgba(255,255,255,.4); }

        /* category colours */
        .lm-span--boot   { background: var(--lm-boot); }
        .lm-span--app    { background: var(--lm-app); }
        .lm-span--db     { background: var(--lm-db); }
        .lm-span--cache  { background: var(--lm-cache); }
        .lm-span--render { background: var(--lm-render); }
        .lm-span--http   { background: var(--lm-http); }
        .lm-span--queue  { background: var(--lm-queue); }
        .lm-span--auth   { background: var(--lm-auth); }
        .lm-span--event  { background: var(--lm-event); }

        .lm-legend {
            display: flex; gap: 12px; flex-wrap: wrap;
            font-size: 11px; color: #94a3b8; margin-bottom: 12px;
        }
        .lm-legend-item { display: flex; align-items: center; gap: 5px; }
        .lm-legend-dot {
            width: 10px; height: 10px; border-radius: 2px; display: inline-block;
        }
        .lm-legend-item--boot   .lm-legend-dot { background: var(--lm-boot); }
        .lm-legend-item--app    .lm-legend-dot { background: var(--lm-app); }
        .lm-legend-item--db     .lm-legend-dot { background: var(--lm-db); }
        .lm-legend-item--cache  .lm-legend-dot { background: var(--lm-cache); }
        .lm-legend-item--render .lm-legend-dot { background: var(--lm-render); }
        .lm-legend-item--http   .lm-legend-dot { background: var(--lm-http); }
        .lm-legend-item--queue  .lm-legend-dot { background: var(--lm-queue); }
        .lm-legend-item--auth   .lm-legend-dot { background: var(--lm-auth); }
        .lm-legend-item--event  .lm-legend-dot { background: var(--lm-event); }

        .lm-span-list-empty {
            font-size: 12px; color: #64748b; padding: 8px 0;
        }
        .lm-span-list-empty code {
            background: #1e293b; padding: 1px 5px; border-radius: 3px; font-family: monospace;
        }
        .lm-span-table {
            width: 100%; border-collapse: collapse; font-size: 11px;
            color: #cbd5e1;
        }
        .lm-span-table th {
            text-align: left; padding: 4px 8px; color: #64748b;
            border-bottom: 1px solid #1e293b; font-weight: 600;
            text-transform: uppercase; font-size: 10px; letter-spacing: .05em;
        }
        .lm-span-table td { padding: 3px 8px; border-bottom: 1px solid #0f172a; }
        .lm-span-table tr:hover td { background: #1e293b; }
        .lm-span-label { font-family: monospace; max-width: 480px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lm-dur   { color: #fbbf24; font-family: monospace; }
        .lm-start { color: #64748b; font-family: monospace; }

        .lm-badge {
            display: inline-block; padding: 1px 6px; border-radius: 3px;
            font-size: 10px; font-weight: 700; text-transform: uppercase; color: #fff;
        }
        .lm-badge--boot   { background: var(--lm-boot); }
        .lm-badge--app    { background: var(--lm-app); }
        .lm-badge--db     { background: var(--lm-db); }
        .lm-badge--cache  { background: var(--lm-cache); }
        .lm-badge--render { background: var(--lm-render); }
        .lm-badge--http   { background: var(--lm-http); }
        .lm-badge--queue  { background: var(--lm-queue); }
        .lm-badge--auth   { background: var(--lm-auth); }
        .lm-badge--event  { background: var(--lm-event); }
        /* ── End Timeline ─────────────────────────────────────────────── */

        .lazymephp-debug-toolbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #12151c;
            color: #e8ecf4;
            font-family: 'SF Mono', 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 12px;
            z-index: 2147483646;
            border-top: 2px solid #3b82f6;
            box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.45);
            pointer-events: auto;
        }

        .lazymephp-debug-toolbar--minimized {
            left: auto;
            right: 16px;
            bottom: 16px;
            width: auto;
            max-height: none;
            border-top: none;
            border-radius: 8px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.45);
            overflow: visible;
        }

        .lazymephp-debug-toolbar--minimized .lazymephp-debug-content {
            display: none !important;
        }

        .lazymephp-debug-toolbar--minimized .lazymephp-debug-header {
            padding: 0;
            background: transparent;
            border: none;
            cursor: default;
            pointer-events: none;
        }

        .lazymephp-debug-toolbar--minimized .lazymephp-debug-title,
        .lazymephp-debug-toolbar--minimized .lazymephp-debug-stats,
        .lazymephp-debug-toolbar--minimized .lazymephp-debug-shortcut,
        .lazymephp-debug-toolbar--minimized .lazymephp-debug-chevron {
            display: none;
        }

        .lazymephp-debug-toolbar--minimized .lazymephp-debug-dock {
            pointer-events: auto;
        }

        body.lazymephp-debug-minimized {
            padding-bottom: 0;
        }

        .lazymephp-debug-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            background: linear-gradient(180deg, #1e2430 0%, #181c26 100%);
            border-bottom: 1px solid #2d3548;
            cursor: pointer;
            user-select: none;
        }

        .lazymephp-debug-title {
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .lazymephp-debug-stats {
            display: flex;
            gap: 8px;
            flex: 1;
            flex-wrap: wrap;
        }

        .lazymephp-debug-stat {
            padding: 3px 8px;
            background: #2a3344;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #3d4a63;
        }

        .lazymephp-debug-stat--time { color: #86efac; }
        .lazymephp-debug-stat--memory { color: #93c5fd; }
        .lazymephp-debug-stat--queries { color: #fcd34d; }
        .lazymephp-debug-stat--errors { color: #fca5a5; }
        .lazymephp-debug-stat--errors-active {
            background: #7f1d1d;
            border-color: #ef4444;
            animation: lazymephp-debug-pulse 1.5s ease-in-out infinite;
        }

        @keyframes lazymephp-debug-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.75; }
        }

        .lazymephp-debug-shortcut {
            font-size: 10px;
            color: #8b95a8;
            white-space: nowrap;
        }

        .lazymephp-debug-icon-btn {
            flex-shrink: 0;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #8b95a8;
            font-size: 11px;
            border-radius: 6px;
            background: #2a3344;
            border: 1px solid #3d4a63;
            box-sizing: border-box;
        }

        .lazymephp-debug-chevron::before {
            content: 'â�²';
        }

        .lazymephp-debug-toolbar--expanded .lazymephp-debug-chevron::before {
            content: 'â�¼';
        }

        .lazymephp-debug-icon-btn:hover {
            background: #343f52;
            color: #e8ecf4;
            border-color: #4b5870;
        }

        #lazymephp-debug-toolbar button.lazymephp-debug-dock {
            appearance: none;
            -webkit-appearance: none;
            flex-shrink: 0;
            margin: 0;
            padding: 0 10px;
            height: 28px;
            min-height: 28px;
            border: 1px solid #3d4a63;
            border-radius: 6px;
            background: #2a3344;
            color: #8b95a8;
            font-family: inherit;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-decoration: none;
            cursor: pointer;
            line-height: 1;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        #lazymephp-debug-toolbar button.lazymephp-debug-dock span {
            color: inherit;
            background: transparent;
            border: none;
            padding: 0;
            margin: 0;
            font: inherit;
        }

        #lazymephp-debug-toolbar button.lazymephp-debug-dock:hover {
            background: #2a3344;
            color: #8b95a8;
            border-color: #3d4a63;
        }

        .lazymephp-debug-dock__closed {
            display: none;
        }

        .lazymephp-debug-toolbar--minimized .lazymephp-debug-dock__open {
            display: none;
        }

        .lazymephp-debug-toolbar--minimized .lazymephp-debug-dock__closed {
            display: inline;
        }

        .lazymephp-debug-content {
            max-height: min(55vh, 520px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            pointer-events: auto;
        }

        .lazymephp-debug-content[hidden] {
            display: none !important;
        }

        .lazymephp-debug-tabs {
            display: flex;
            background: #1a1f2b;
            border-bottom: 1px solid #2d3548;
            overflow-x: auto;
            flex-shrink: 0;
        }

        #lazymephp-debug-toolbar button.lazymephp-debug-tab {
            background: transparent;
            border: none;
            color: #7d8799;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            border-bottom: 2px solid transparent;
            padding: 10px 14px;
            white-space: nowrap;
            font-family: inherit;
            border-radius: 0;
            min-height: 0;
            transition: color 0.15s, background 0.15s, border-color 0.15s;
        }

        #lazymephp-debug-toolbar button.lazymephp-debug-tab:hover:not(.active) {
            color: #c5ccd8;
            background: #222836;
        }

        #lazymephp-debug-toolbar button.lazymephp-debug-tab.active {
            color: #fff;
            background: #2a3344;
            border-bottom-color: #60a5fa;
            box-shadow: inset 0 -2px 0 #3b82f6;
            font-weight: 700;
        }

        .lazymephp-debug-tab-panels {
            overflow-y: auto;
            flex: 1;
        }

        .lazymephp-debug-tab-content {
            padding: 12px 14px;
        }

        .lazymephp-debug-tab-content[hidden] {
            display: none !important;
        }

        .lazymephp-debug-copyable {
            user-select: text !important;
            -webkit-user-select: text !important;
            cursor: text;
        }

        .lazymephp-debug-block-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
        }

        #lazymephp-debug-toolbar button.lazymephp-debug-copy-btn {
            background: #2a3344;
            border: 1px solid #3d4a63;
            color: #e8ecf4;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 11px;
            cursor: pointer;
            flex-shrink: 0;
            font-family: inherit;
            font-weight: 600;
            min-height: 0;
        }

        #lazymephp-debug-toolbar button.lazymephp-debug-copy-btn:hover { background: #3b82f6; border-color: #3b82f6; color: #fff; }
        #lazymephp-debug-toolbar button.lazymephp-debug-copy-btn.copied { background: #166534; border-color: #22c55e; color: #fff; }

        .lazymephp-debug-query {
            margin-bottom: 12px;
            padding: 10px;
            background: #181c26;
            border: 1px solid #2d3548;
            border-left: 3px solid #3b82f6;
            border-radius: 8px;
        }

        .lazymephp-debug-query-time {
            color: #86efac;
            font-weight: 700;
        }

        .lazymephp-debug-query-sql,
        .lazymephp-debug-error-body {
            margin: 0;
            background: #0f1218;
            padding: 10px;
            border-radius: 6px;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
            font-family: inherit;
            font-size: 11px;
            line-height: 1.5;
            color: #e8ecf4;
            border: 1px solid #252b3a;
        }

        .lazymephp-debug-query-params {
            margin-top: 8px;
            padding: 8px 10px;
            background: #141820;
            border-radius: 6px;
            font-size: 11px;
            color: #b6c0d4;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .lazymephp-debug-error {
            margin-bottom: 12px;
            padding: 10px;
            background: #1f1418;
            border: 1px solid #4c1d1d;
            border-left: 3px solid #ef4444;
            border-radius: 8px;
        }

        .lazymephp-debug-error-badge {
            color: #fca5a5;
            font-weight: 700;
            font-size: 11px;
        }

        .lazymephp-debug-section { margin-bottom: 15px; }

        .lazymephp-debug-item {
            padding: 4px 0;
            border-bottom: 1px solid #2d3548;
            user-select: text;
            word-break: break-word;
        }

        .lazymephp-debug-performance { line-height: 1.7; }
        .lazymephp-debug-perf-item { padding: 4px 0; user-select: text; }

        .lazymephp-debug-empty {
            color: #8b95a8;
            font-style: italic;
            text-align: center;
            padding: 24px;
        }

        .lazymephp-debug-error-indicator {
            position: fixed;
            top: 14px;
            right: 14px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            z-index: 2147483647;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(220, 38, 38, 0.35);
            user-select: none;
        }

        .lazymephp-debug-error-indicator:hover {
            transform: translateY(-1px);
        }

        body.lazymephp-debug-active {
            padding-bottom: 42px;
        }

        .lazymephp-debug-tab-panels h4 {
            margin: 14px 0 8px;
            color: #93c5fd;
            border-bottom: 1px solid #2d3548;
            padding-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        </style>
        ";
    }
    
    /**
     * Generate toolbar JavaScript
     */
    private function generateToolbarJS(): string
    {
        return <<<'JS'
        <script>
        (function() {
            'use strict';

            var STATE_KEY = 'lazymephp-debug-state';
            var VALID_TABS = ['queries', 'errors', 'request', 'performance'];

            function isDebugShortcut(event) {
                if (!event.ctrlKey || !event.shiftKey || event.altKey || event.metaKey) {
                    return false;
                }
                var key = event.key || '';
                return event.code === 'KeyD' || key.toLowerCase() === 'd';
            }

            function getToolbar() {
                return document.getElementById('lazymephp-debug-toolbar');
            }

            function getContent() {
                return document.getElementById('lazymephp-debug-content');
            }

            function isExpanded() {
                var content = getContent();
                return content && !content.hasAttribute('hidden');
            }

            function loadDebugState() {
                try {
                    var raw = sessionStorage.getItem(STATE_KEY);
                    if (!raw) return null;
                    var state = JSON.parse(raw);
                    if (!state || typeof state !== 'object') return null;
                    if (state.tab && VALID_TABS.indexOf(state.tab) === -1) {
                        state.tab = 'queries';
                    }
                    return state;
                } catch (e) {
                    return null;
                }
            }

            function saveDebugState() {
                var toolbar = getToolbar();
                if (!toolbar) return;

                var activeTab = document.querySelector('.lazymephp-debug-tab.active');
                var state = {
                    minimized: toolbar.classList.contains('lazymephp-debug-toolbar--minimized'),
                    expanded: isExpanded(),
                    tab: activeTab ? activeTab.getAttribute('data-tab-id') : 'queries'
                };

                try {
                    sessionStorage.setItem(STATE_KEY, JSON.stringify(state));
                } catch (e) {
                    // ignore quota / private mode
                }
            }

            function updateDockButtonTitle(dockButton) {
                if (!dockButton) {
                    var toolbar = getToolbar();
                    dockButton = toolbar ? toolbar.querySelector('.lazymephp-debug-dock') : null;
                }
                if (!dockButton) return;

                var toolbar = getToolbar();
                dockButton.title = toolbar && toolbar.classList.contains('lazymephp-debug-toolbar--minimized')
                    ? 'Mostrar barra de debug'
                    : 'Ocultar barra de debug';
            }

            function setDockedCollapsed() {
                var toolbar = getToolbar();
                var content = getContent();
                if (!toolbar || !content) return;

                toolbar.classList.remove('lazymephp-debug-toolbar--minimized');
                document.body.classList.remove('lazymephp-debug-minimized');
                toolbar.classList.remove('lazymephp-debug-toolbar--expanded');
                content.setAttribute('hidden', '');
                document.body.classList.remove('lazymephp-debug-active');
                saveDebugState();
            }

            function setExpanded(expanded, preferErrorsTab) {
                var toolbar = getToolbar();
                var content = getContent();
                if (!toolbar || !content) return;

                toolbar.classList.remove('lazymephp-debug-toolbar--minimized');
                document.body.classList.remove('lazymephp-debug-minimized');
                if (expanded) {
                    toolbar.classList.add('lazymephp-debug-toolbar--expanded');
                } else {
                    toolbar.classList.remove('lazymephp-debug-toolbar--expanded');
                }
                if (expanded) {
                    content.removeAttribute('hidden');
                    document.body.classList.add('lazymephp-debug-active');
                    if (preferErrorsTab) {
                        showDebugTab('errors', true);
                    }
                } else {
                    content.setAttribute('hidden', '');
                    document.body.classList.remove('lazymephp-debug-active');
                }
                saveDebugState();
            }

            function toggleExpanded(preferErrorsTab) {
                setExpanded(!isExpanded(), preferErrorsTab);
            }

            function toggleMinimized() {
                var toolbar = getToolbar();
                var content = getContent();
                if (!toolbar) return;

                if (toolbar.classList.contains('lazymephp-debug-toolbar--minimized')) {
                    var errorCount = parseInt(toolbar.getAttribute('data-error-count') || '0', 10);
                    setExpanded(true, errorCount > 0);
                } else {
                    toolbar.classList.add('lazymephp-debug-toolbar--minimized');
                    document.body.classList.add('lazymephp-debug-minimized');
                    if (content) {
                        content.setAttribute('hidden', '');
                    }
                    toolbar.classList.remove('lazymephp-debug-toolbar--expanded');
                    document.body.classList.remove('lazymephp-debug-active');
                    saveDebugState();
                }
            }

            function showDebugTab(tabId, skipSave) {
                document.querySelectorAll('.lazymephp-debug-tab-content').forEach(function(panel) {
                    panel.setAttribute('hidden', '');
                });
                document.querySelectorAll('.lazymephp-debug-tab').forEach(function(tab) {
                    tab.classList.remove('active');
                });

                var panel = document.getElementById('lazymephp-debug-tab-' + tabId);
                var tab = document.querySelector('.lazymephp-debug-tab[data-tab-id="' + tabId + '"]');
                if (panel) panel.removeAttribute('hidden');
                if (tab) tab.classList.add('active');
                if (!skipSave) {
                    saveDebugState();
                }
            }

            function applySavedDebugState(errorCount) {
                if (errorCount > 0) {
                    setExpanded(true, true);
                    updateDockButtonTitle();
                    return;
                }

                var saved = loadDebugState();
                if (!saved) {
                    document.body.classList.add('lazymephp-debug-minimized');
                    saveDebugState();
                    updateDockButtonTitle();
                    return;
                }

                if (saved.minimized) {
                    var toolbar = getToolbar();
                    var content = getContent();
                    if (toolbar) {
                        toolbar.classList.add('lazymephp-debug-toolbar--minimized');
                    }
                    document.body.classList.add('lazymephp-debug-minimized');
                    if (content) {
                        content.setAttribute('hidden', '');
                    }
                    if (toolbar) {
                        toolbar.classList.remove('lazymephp-debug-toolbar--expanded');
                    }
                    document.body.classList.remove('lazymephp-debug-active');
                } else if (saved.expanded) {
                    setExpanded(true, false);
                    if (saved.tab) {
                        showDebugTab(saved.tab, true);
                    }
                    saveDebugState();
                } else {
                    setDockedCollapsed();
                    if (saved.tab) {
                        showDebugTab(saved.tab, true);
                    }
                }

                updateDockButtonTitle();
            }

            function copyText(text, button) {
                if (!text) return;

                function onSuccess() {
                    if (!button) return;
                    var original = button.textContent;
                    button.textContent = 'â�� Copied';
                    button.classList.add('copied');
                    setTimeout(function() {
                        button.textContent = original;
                        button.classList.remove('copied');
                    }, 1500);
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(onSuccess).catch(function() {
                        fallbackCopy(text, button, onSuccess);
                    });
                } else {
                    fallbackCopy(text, button, onSuccess);
                }
            }

            function fallbackCopy(text, button, onSuccess) {
                var area = document.createElement('textarea');
                area.value = text;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.left = '-9999px';
                document.body.appendChild(area);
                area.select();
                try {
                    document.execCommand('copy');
                    onSuccess();
                } catch (e) {
                    console.warn('Copy failed', e);
                }
                document.body.removeChild(area);
            }

            function handleCopyClick(event) {
                var button = event.target.closest('.lazymephp-debug-copy-btn');
                if (!button) return;

                event.preventDefault();
                event.stopPropagation();

                var text = button.getAttribute('data-copy-text') || '';
                if (!text) {
                    var targetId = button.getAttribute('data-copy-target');
                    var target = targetId ? document.getElementById(targetId) : null;
                    text = target ? target.textContent : '';
                }
                copyText(text, button);
            }

            function openFromErrorIndicator() {
                var toolbar = getToolbar();
                if (!toolbar) return;
                setExpanded(true, true);
                toolbar.scrollIntoView({ block: 'end' });
            }

            function initDebugToolbar() {
                var toolbar = getToolbar();
                if (!toolbar || toolbar.dataset.initialized === '1') return;
                toolbar.dataset.initialized = '1';

                var dockButton = toolbar.querySelector('.lazymephp-debug-dock');
                var header = document.getElementById('lazymephp-debug-header');
                var errorIndicator = document.getElementById('lazymephp-debug-error-indicator');
                var errorCount = parseInt(toolbar.getAttribute('data-error-count') || '0', 10);

                if (dockButton) {
                    dockButton.addEventListener('click', function(event) {
                        event.stopPropagation();
                        toggleMinimized();
                        updateDockButtonTitle(dockButton);
                    });
                }

                if (header) {
                    header.addEventListener('click', function(event) {
                        if (event.target.closest('button')) return;
                        if (toolbar.classList.contains('lazymephp-debug-toolbar--minimized')) return;
                        toggleExpanded(errorCount > 0);
                    });
                }

                toolbar.querySelectorAll('.lazymephp-debug-tab').forEach(function(button) {
                    button.addEventListener('click', function(event) {
                        event.stopPropagation();
                        var tabId = button.getAttribute('data-tab-id');
                        if (tabId) {
                            setExpanded(true, false);
                            showDebugTab(tabId);
                        }
                    });
                });

                toolbar.addEventListener('click', handleCopyClick);

                if (errorIndicator) {
                    errorIndicator.addEventListener('click', openFromErrorIndicator);
                    errorIndicator.addEventListener('keydown', function(event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            openFromErrorIndicator();
                        }
                    });
                }

                window.toggleDebugToolbar = function() { toggleExpanded(false); };
                window.showDebugTab = showDebugTab;
                window.LazyMePHPDebug = window.LazyMePHPDebug || {};
                window.LazyMePHPDebug.toggle = function(preferErrors) { toggleExpanded(preferErrors); };
                window.LazyMePHPDebug.expand = function(preferErrors) { setExpanded(true, !!preferErrors); };

                applySavedDebugState(errorCount);
            }

            window.addEventListener('keydown', function(event) {
                if (!isDebugShortcut(event)) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                var toolbar = getToolbar();
                if (!toolbar) return;
                var errorCount = parseInt(toolbar.getAttribute('data-error-count') || '0', 10);
                if (toolbar.classList.contains('lazymephp-debug-toolbar--minimized') || !isExpanded()) {
                    setExpanded(true, errorCount > 0);
                } else {
                    setExpanded(false, false);
                }
            }, true);

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initDebugToolbar);
            } else {
                initDebugToolbar();
            }

            setTimeout(initDebugToolbar, 0);
            setTimeout(initDebugToolbar, 500);
        })();
        </script>
        JS;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
