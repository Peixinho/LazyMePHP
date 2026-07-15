<?php

declare(strict_types=1);

namespace Core\Storage;

interface Disk
{
    public function put(string $path, string $contents): bool;
    public function get(string $path): ?string;
    public function delete(string $path): bool;
    public function exists(string $path): bool;
    public function url(string $path): string;
    public function size(string $path): int;
    public function mimeType(string $path): string;
    public function files(string $directory = ''): array;
}
