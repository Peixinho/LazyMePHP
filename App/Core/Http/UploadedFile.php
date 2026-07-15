<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Wraps a single entry from $_FILES with a clean API.
 *
 *   $file = Request::capture()->file('avatar');
 *   if ($file && $file->isValid()) {
 *       $path = $file->store('avatars');         // returns stored path
 *       $mime = $file->getMimeType();
 *   }
 */
class UploadedFile
{
    private static array $ERROR_MESSAGES = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by a PHP extension.',
    ];

    public function __construct(private readonly array $file) {}

    public function isValid(): bool
    {
        return $this->file['error'] === UPLOAD_ERR_OK && is_uploaded_file($this->file['tmp_name']);
    }

    public function getClientOriginalName(): string
    {
        return basename($this->file['name'] ?? '');
    }

    public function getClientOriginalExtension(): string
    {
        return strtolower(pathinfo($this->getClientOriginalName(), PATHINFO_EXTENSION));
    }

    public function extension(): string
    {
        return $this->getClientOriginalExtension();
    }

    public function getMimeType(): string
    {
        if (function_exists('mime_content_type') && $this->isValid()) {
            return (string)mime_content_type($this->file['tmp_name']);
        }
        return $this->file['type'] ?? 'application/octet-stream';
    }

    public function getSize(): int
    {
        return (int)($this->file['size'] ?? 0);
    }

    public function getError(): int
    {
        return (int)($this->file['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    public function getErrorMessage(): string
    {
        return self::$ERROR_MESSAGES[$this->getError()] ?? 'Unknown upload error.';
    }

    public function getTmpPath(): string
    {
        return $this->file['tmp_name'] ?? '';
    }

    /**
     * Store the file in the given directory and return the stored path.
     *
     *   $path = $file->store('uploads/avatars');          // unique hash name
     *   $path = $file->storeAs('uploads', 'avatar.jpg');  // specific name
     */
    public function store(string $directory, string $disk = 'local'): string
    {
        return $this->storeAs($directory, $this->hashName(), $disk);
    }

    public function storeAs(string $directory, string $name, string $disk = 'local'): string
    {
        $root      = $this->storagePath($disk);
        $dest      = rtrim($root . '/' . ltrim($directory, '/'), '/');

        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $target = $dest . '/' . $name;

        if (!move_uploaded_file($this->file['tmp_name'], $target)) {
            throw new \RuntimeException("Could not move uploaded file to [{$target}].");
        }

        return ltrim($directory, '/') . '/' . $name;
    }

    /** Generate a random hash-based filename preserving the extension. */
    public function hashName(): string
    {
        $ext = $this->extension();
        return bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
    }

    private function storagePath(string $disk): string
    {
        return match ($disk) {
            'public' => (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/public/storage',
            default  => (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/storage/app',
        };
    }
}
