<?php

declare(strict_types=1);

namespace Core\Storage;

/**
 * Storage facade.
 *
 * Configure in .env:
 *   STORAGE_DRIVER=local         # only driver currently supported
 *   STORAGE_PATH=storage/app     # root directory (relative to project root)
 *   STORAGE_URL=/storage         # public URL prefix
 *
 * Usage:
 *   Storage::disk()->put('images/logo.png', $contents);
 *   Storage::disk()->url('images/logo.png');   // → /storage/images/logo.png
 *   Storage::disk('local')->get('images/logo.png');
 *
 *   // File uploads:
 *   $file = UploadedFile::fromInput('avatar');
 *   $path = $file->store('avatars');
 */
class Storage
{
    /** @var Disk[] */
    private static array $disks = [];

    public static function disk(string $name = 'local'): Disk
    {
        if (isset(self::$disks[$name])) {
            return self::$disks[$name];
        }

        $driver  = $_ENV['STORAGE_DRIVER'] ?? 'local';
        $root    = $_ENV['STORAGE_PATH']   ?? 'storage/app';
        $baseUrl = $_ENV['STORAGE_URL']    ?? '/storage';

        // Resolve relative paths against the project root
        if (!str_starts_with($root, '/')) {
            $root = dirname(__DIR__, 3) . '/' . $root;
        }

        self::$disks[$name] = match ($driver) {
            default => new LocalDisk($root, $baseUrl),
        };

        return self::$disks[$name];
    }

    /** Register a custom disk instance (useful for testing). */
    public static function fake(string $name = 'local', ?Disk $disk = null): Disk
    {
        $root = sys_get_temp_dir() . '/lazyme_storage_' . $name;
        self::$disks[$name] = $disk ?? new LocalDisk($root, '/storage');
        return self::$disks[$name];
    }

    public static function reset(): void
    {
        self::$disks = [];
    }
}
