<?php
declare(strict_types=1);

// Batman Server-Side API Proxy
//
// The GraphQL Explorer used to have the BROWSER call the target app directly
// (fetch() from api-client.php straight to {baseUrl}/auth/login or /graphql).
// That's a cross-origin request from a webpage, which is exactly what CORS
// exists to restrict — the target app had to set APP_CORS_ORIGIN to whatever
// port Batman happened to be running on that day.
//
// Postman never hits this wall because it isn't a browser: there's no page,
// no JS origin, so there's nothing for CORS to restrict. This proxy makes
// Batman behave the same way. The browser only ever calls this same-origin
// endpoint; THIS SCRIPT (server-to-server, via cURL) makes the actual request
// to the target app. CORS is a browser-only restriction on JavaScript, so a
// PHP-to-PHP HTTP call is never subject to it, regardless of origin/port.
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$input   = json_decode((string) file_get_contents('php://input'), true) ?? [];
$action  = (string) ($input['action'] ?? '');
$baseUrl = rtrim((string) ($input['baseUrl'] ?? ''), '/');

if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid Base URL is required']);
    exit;
}

try {
    $result = match ($action) {
        'login' => proxyLogin($baseUrl, (string) ($input['email'] ?? ''), (string) ($input['password'] ?? '')),
        'query' => proxyGraphql($baseUrl, (string) ($input['token'] ?? ''), (string) ($input['query'] ?? ''), $input['variables'] ?? null),
        default => throw new \InvalidArgumentException('Unknown proxy action: ' . $action),
    };
    echo json_encode(['success' => true] + $result);
} catch (\Throwable $e) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Proxy request failed: ' . $e->getMessage()]);
}

function proxyLogin(string $baseUrl, string $email, string $password): array
{
    return httpPostJson($baseUrl . '/auth/login', ['email' => $email, 'password' => $password]);
}

function proxyGraphql(string $baseUrl, string $token, string $query, mixed $variables): array
{
    $headers = [];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    return httpPostJson($baseUrl . '/graphql', ['query' => $query, 'variables' => $variables], $headers);
}

/** @param list<string> $extraHeaders */
function httpPostJson(string $url, array $body, array $extraHeaders = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $extraHeaders),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $raw = curl_exec($ch);

    if ($raw === false) {
        // curl_close() is a no-op since PHP 8.0 (deprecated entirely in 8.5) —
        // the handle is freed automatically once it goes out of scope.
        throw new \RuntimeException(curl_error($ch) ?: 'Connection failed');
    }

    $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $rawHeaders = substr($raw, 0, $headerSize);
    $body       = substr($raw, $headerSize);

    $headers = [];
    foreach (explode("\r\n", trim($rawHeaders)) as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }
    }

    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}
