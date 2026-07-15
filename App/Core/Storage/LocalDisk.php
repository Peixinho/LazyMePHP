<?php

declare(strict_types=1);

namespace Core\Storage;

class LocalDisk implements Disk
{
    private string $root;
    private string $baseUrl;

    public function __construct(string $root, string $baseUrl = '/storage')
    {
        $this->root    = rtrim($root, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function put(string $path, string $contents): bool
    {
        $full = $this->fullPath($path);
        $dir  = dirname($full);

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        return file_put_contents($full, $contents) !== false;
    }

    public function get(string $path): ?string
    {
        $full = $this->fullPath($path);
        if (!is_file($full)) return null;
        $contents = file_get_contents($full);
        return $contents === false ? null : $contents;
    }

    public function delete(string $path): bool
    {
        $full = $this->fullPath($path);
        return is_file($full) && unlink($full);
    }

    public function exists(string $path): bool
    {
        return is_file($this->fullPath($path));
    }

    public function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    public function size(string $path): int
    {
        $full = $this->fullPath($path);
        return is_file($full) ? (int) filesize($full) : 0;
    }

    public function mimeType(string $path): string
    {
        $full = $this->fullPath($path);
        if (!is_file($full)) return 'application/octet-stream';
        return mime_content_type($full) ?: 'application/octet-stream';
    }

    public function files(string $directory = ''): array
    {
        $dir = $this->fullPath($directory);
        if (!is_dir($dir)) return [];

        $files = [];
        foreach (new \DirectoryIterator($dir) as $item) {
            if ($item->isFile()) {
                $files[] = ($directory ? $directory . '/' : '') . $item->getFilename();
            }
        }
        return $files;
    }

    public function fullPath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }
}
