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
        return [
            'queries' => $this->queries,
            'errors' => \Core\Helpers\ErrorUtil::getCurrentRequestErrors(),
            'execution_time' => $this->getExecutionTime(),
            'memory' => $this->getMemoryUsage(),
            'request' => $this->requestInfo,
            'php_version' => PHP_VERSION,
            'lazymephp_version' => '1.0.0' // You can make this dynamic
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
        
        return "
        <div id='lazymephp-debug-toolbar' class='lazymephp-debug-toolbar'>
            <div class='lazymephp-debug-header'>
                <span class='lazymephp-debug-title'>🚀 LazyMePHP Debug</span>
                <div class='lazymephp-debug-stats'>
                    <span class='lazymephp-debug-stat' title='Execution Time'>{$executionTime}ms</span>
                    <span class='lazymephp-debug-stat' title='Memory Usage'>{$memoryUsage}</span>
                    <span class='lazymephp-debug-stat' title='SQL Queries'>{$queryCount}</span>
                    <span class='lazymephp-debug-stat' title='Errors'>{$errorCount}</span>
                </div>
                <button class='lazymephp-debug-toggle' onclick='toggleDebugToolbar()'>📊</button>
            </div>
            <div id='lazymephp-debug-content' class='lazymephp-debug-content' style='display: none;'>
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
        $tabs = [
            'queries' => ['label' => "Queries ({$queryCount})", 'icon' => '🗄️'],
            'errors' => ['label' => "Errors ({$errorCount})", 'icon' => '⚠️'],
            'request' => ['label' => 'Request', 'icon' => '📡'],
            'performance' => ['label' => 'Performance', 'icon' => '⚡']
        ];
        
        $tabContent = '';
        $tabButtons = '';
        
        foreach ($tabs as $tabId => $tab) {
            $active = $tabId === 'queries' ? 'active' : '';
            $tabButtons .= "<button class='lazymephp-debug-tab {$active}' onclick='showDebugTab(\"{$tabId}\")'>{$tab['icon']} {$tab['label']}</button>";
            
            $content = $this->generateTabContent($tabId, $data);
            $display = $tabId === 'queries' ? 'block' : 'none';
            $tabContent .= "<div id='lazymephp-debug-tab-{$tabId}' class='lazymephp-debug-tab-content' style='display: {$display};'>{$content}</div>";
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
            'queries' => $this->generateQueriesTab($data['queries']),
            'errors' => $this->generateErrorsTab($data['errors']),
            'request' => $this->generateRequestTab($data['request']),
            'performance' => $this->generatePerformanceTab($data),
            default => ''
        };
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
        foreach ($queries as $query) {
            $sql = htmlspecialchars($query['sql']);
            $time = number_format($query['time'] * 1000, 2);
            $params = is_array($query['params']) ? json_encode($query['params']) : htmlspecialchars($query['params']);
            
            $html .= "
            <div class='lazymephp-debug-query'>
                <div class='lazymephp-debug-query-header'>
                    <span class='lazymephp-debug-query-time'>{$time}ms</span>
                    <span class='lazymephp-debug-query-number'>{$query['timestamp']}</span>
                </div>
                <div class='lazymephp-debug-query-sql'>{$sql}</div>
                <div class='lazymephp-debug-query-params'>{$params}</div>
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
        foreach ($errors as $error) {
            $message = htmlspecialchars($error['message']);
            $file = htmlspecialchars($error['file']);
            $line = $error['line'];
            
            $html .= "
            <div class='lazymephp-debug-error'>
                <div class='lazymephp-debug-error-message'>{$message}</div>
                <div class='lazymephp-debug-error-location'>{$file}:{$line}</div>
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
        <style>
        .lazymephp-debug-toolbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #1a1a1a;
            color: #fff;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 12px;
            z-index: 9999;
            border-top: 2px solid #007acc;
        }
        
        .lazymephp-debug-header {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: #2d2d2d;
            border-bottom: 1px solid #444;
        }
        
        .lazymephp-debug-title {
            font-weight: bold;
            margin-right: 20px;
        }
        
        .lazymephp-debug-stats {
            display: flex;
            gap: 15px;
            flex: 1;
        }
        
        .lazymephp-debug-stat {
            padding: 2px 6px;
            background: #007acc;
            border-radius: 3px;
            font-size: 11px;
        }
        
        .lazymephp-debug-toggle {
            background: #007acc;
            border: none;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .lazymephp-debug-content {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .lazymephp-debug-tabs {
            display: flex;
            background: #333;
            border-bottom: 1px solid #555;
        }
        
        .lazymephp-debug-tab {
            background: #444;
            border: none;
            color: #ccc;
            cursor: pointer;
            font-size: 11px;
            border-right: 1px solid #555;
            padding: 8px 12px;
        }
        
        .lazymephp-debug-tab.active {
            background: #007acc;
            color: white;
        }
        
        .lazymephp-debug-tab-content {
            padding: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .lazymephp-debug-query {
            margin-bottom: 10px;
            padding: 8px;
            background: #2a2a2a;
            border-left: 3px solid #007acc;
        }
        
        .lazymephp-debug-query-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .lazymephp-debug-query-time {
            color: #00ff00;
            font-weight: bold;
        }
        
        .lazymephp-debug-query-number {
            color: #888;
        }
        
        .lazymephp-debug-query-sql {
            background: #1a1a1a;
            padding: 5px;
            border-radius: 3px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .lazymephp-debug-query-params {
            margin-top: 5px;
            padding: 3px 5px;
            background: #333;
            border-radius: 2px;
            font-size: 11px;
            color: #ccc;
        }
        
        .lazymephp-debug-error {
            margin-bottom: 10px;
            padding: 8px;
            background: #2a2a2a;
            border-left: 3px solid #ff4444;
        }
        
        .lazymephp-debug-error-message {
            color: #ff6666;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .lazymephp-debug-error-location {
            color: #888;
            font-size: 11px;
        }
        
        .lazymephp-debug-section {
            margin-bottom: 15px;
        }
        
        .lazymephp-debug-item {
            padding: 2px 0;
            border-bottom: 1px solid #333;
        }
        
        .lazymephp-debug-performance {
            line-height: 1.6;
        }
        
        .lazymephp-debug-perf-item {
            padding: 3px 0;
        }
        
        .lazymephp-debug-summary {
            background: #007acc;
            color: white;
            padding: 5px 8px;
            margin-bottom: 10px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .lazymephp-debug-empty {
            color: #888;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
        
        h4 {
            margin: 15px 0 8px 0;
            color: #007acc;
            border-bottom: 1px solid #444;
            padding-bottom: 3px;
        }
        </style>
        ";
    }
    
    /**
     * Generate toolbar JavaScript
     */
    private function generateToolbarJS(): string
    {
        return "
        <script>
        function toggleDebugToolbar() {
            const content = document.getElementById('lazymephp-debug-content');
            const toggle = document.querySelector('.lazymephp-debug-toggle');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                toggle.textContent = '📊';
            } else {
                content.style.display = 'none';
                toggle.textContent = '📊';
            }
        }
        
        function showDebugTab(tabId) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.lazymephp-debug-tab-content');
            tabContents.forEach(content => content.style.display = 'none');
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.lazymephp-debug-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById('lazymephp-debug-tab-' + tabId).style.display = 'block';
            
            // Add active class to selected tab
            event.target.classList.add('active');
        }
        </script>
        ";
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