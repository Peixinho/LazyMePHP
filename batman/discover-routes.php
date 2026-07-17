<?php
// Batman Route Discovery Endpoint
//
// Regex-scans a directory for web-route definitions (SimpleRouter::get(),
// $router->post(), @route doc comments). This only finds the legacy REST-style
// routes registered in App/Routes/*.php — the actual data API is served over a
// single POST /graphql endpoint built at runtime from the DB schema, which
// can't be found by scanning source text for route calls. See
// discover-graphql.php for that.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $path = $data['path'] ?? 'App/Routes';

    $projectRoot = realpath(__DIR__ . '/..');
    $fullPath = realpath($projectRoot . '/' . $path);

    if ($fullPath === false || !is_dir($fullPath) || !str_starts_with($fullPath, $projectRoot . DIRECTORY_SEPARATOR)) {
        echo json_encode([
            'success' => false,
            'message' => 'Directory not found: ' . $path
        ]);
        exit;
    }

    $routes = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $content = file_get_contents($file->getPathname());
            $routes = array_merge($routes, parseRoutesFromText($content, $file->getFilename()));
        }
    }

    echo json_encode([
        'success' => true,
        'routes' => $routes
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to discover routes: ' . $e->getMessage()
    ]);
    exit;
}

function parseRoutesFromText($content, $filename) {
    $routes = [];
    $patterns = [
        // Any class::get/post/put/delete/patch('url', ...)
        '/::(get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/i',
        // $router->get/post/put/delete/patch('url', ...)
        '/\\$router->(get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/i',
        // Inline doc: @route METHOD /url
        '/@route\s+(GET|POST|PUT|DELETE|PATCH)\s+([^\s]+)/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $method = isset($match[1]) ? strtoupper($match[1]) : 'GET';
                $path = isset($match[2]) ? $match[2] : (isset($match[1]) ? $match[1] : '');
                if (!empty($path)) {
                    $routes[] = [
                        'method' => $method,
                        'path' => $path,
                        'description' => 'Discovered in ' . $filename
                    ];
                }
            }
        }
    }
    // Remove duplicates
    $unique = [];
    foreach ($routes as $r) {
        $key = $r['method'] . ':' . $r['path'];
        if (!isset($unique[$key])) {
            $unique[$key] = $r;
        }
    }
    return array_values($unique);
} 