<?php

declare(strict_types=1);

namespace Core;

/**
 * Serves the pre-built Docusaurus static site from docs/build/ at the /docs path.
 *
 * Called from public/index.php before the router so that no output buffer
 * wrapping or Blade layout is applied to these responses.
 */
class DocsServer
{
    private const MIME_TYPES = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'xml'  => 'application/xml',
        'txt'  => 'text/plain',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'map'  => 'application/json',
    ];

    /**
     * Resolve the request path to a file inside $buildDir and send it.
     * Returns without calling exit() — the caller is responsible for exiting.
     *
     * @param string $path     The path component after /docs (e.g. '' or '/intro' or '/assets/js/main.js')
     * @param string $buildDir Absolute path to the Docusaurus build directory
     */
    public static function serve(string $path, string $buildDir): void
    {
        // Normalise: strip trailing slash, default to /
        $path = '/' . ltrim($path, '/');

        // Security: prevent path traversal
        $resolved = realpath($buildDir . $path);
        if ($resolved === false || !str_starts_with($resolved, realpath($buildDir))) {
            self::send404();
            return;
        }

        // If it's a directory, look for index.html inside it
        if (is_dir($resolved)) {
            $resolved = rtrim($resolved, '/') . '/index.html';
        }

        // If still not a file, try appending /index.html (clean URLs without .html)
        if (!is_file($resolved)) {
            $candidate = rtrim($buildDir . $path, '/') . '/index.html';
            $candidate = realpath($candidate);
            if ($candidate && str_starts_with($candidate, realpath($buildDir)) && is_file($candidate)) {
                $resolved = $candidate;
            } else {
                self::send404();
                return;
            }
        }

        $ext      = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        $mimeType = self::MIME_TYPES[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($resolved));

        // Cache static assets aggressively; HTML pages should revalidate
        if ($ext === 'html') {
            header('Cache-Control: no-cache');
        } else {
            header('Cache-Control: public, max-age=31536000, immutable');
        }

        readfile($resolved);
    }

    private static function send404(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo "404 — docs page not found";
    }
}
