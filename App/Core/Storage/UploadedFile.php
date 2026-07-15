<?php

declare(strict_types=1);

namespace Core\Storage;

/**
 * Wraps a single entry from $_FILES.
 *
 *   $file = UploadedFile::fromInput('avatar');
 *   if ($file && $file->isValid()) {
 *       $path = $file->store('avatars');  // returns 'avatars/<uuid>.jpg'
 *       $url  = Storage::disk()->url($path);
 *   }
 */
class UploadedFile
{
    private string $name;
    private string $type;
    private string $tmpName;
    private int    $error;
    private int    $size;

    public function __construct(array $fileEntry)
    {
        $this->name    = $fileEntry['name']     ?? '';
        $this->type    = $fileEntry['type']     ?? '';
        $this->tmpName = $fileEntry['tmp_name'] ?? '';
        $this->error   = $fileEntry['error']    ?? UPLOAD_ERR_NO_FILE;
        $this->size    = $fileEntry['size']     ?? 0;
    }

    public static function fromInput(string $key): ?static
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return new static($_FILES[$key]);
    }

    /** Returns array of UploadedFile for multi-file inputs (name="files[]"). */
    public static function fromInputMultiple(string $key): array
    {
        if (!isset($_FILES[$key])) return [];

        $files  = $_FILES[$key];
        $result = [];
        $count  = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $result[] = new static([
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ]);
        }
        return $result;
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tmpName);
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getOriginalName(): string
    {
        return $this->name;
    }

    public function getMimeType(): string
    {
        return $this->type;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getExtension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /**
     * Store the file and return the relative path.
     *
     * @param string $directory  Subdirectory within the disk root (e.g. 'avatars')
     * @param string $diskName   Disk name from Storage (default: 'local')
     * @param string|null $name  Custom filename without extension; null = random UUID
     */
    public function store(string $directory = '', string $diskName = 'local', ?string $name = null): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException('Cannot store an invalid uploaded file (error code: ' . $this->error . ').');
        }

        $ext      = $this->getExtension();
        $filename = ($name ?? bin2hex(random_bytes(16))) . ($ext ? '.' . $ext : '');
        $path     = ($directory ? rtrim($directory, '/') . '/' : '') . $filename;

        $disk     = Storage::disk($diskName);
        $contents = file_get_contents($this->tmpName);
        if ($contents === false) {
            throw new \RuntimeException('Could not read uploaded file.');
        }

        $disk->put($path, $contents);
        return $path;
    }
}
