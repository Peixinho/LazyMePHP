---
id: storage
title: File Storage
sidebar_position: 10
---

# File Storage

`Core\Storage\Storage` provides a driver-based abstraction over the filesystem. The local disk driver ships out of the box.

## Configuration

```env
STORAGE_DRIVER=local
STORAGE_PATH=storage/app
STORAGE_URL=/storage
```

## Basic operations

```php
use Core\Storage\Storage;

$disk = Storage::disk();

// Write
$disk->put('reports/2026-07.csv', $csvContents);

// Read
$contents = $disk->get('reports/2026-07.csv');

// Delete
$disk->delete('reports/2026-07.csv');

// Check existence
$disk->exists('reports/2026-07.csv');  // bool

// Public URL
$url = $disk->url('reports/2026-07.csv');  // /storage/reports/2026-07.csv
```

## File uploads

```php
use Core\Storage\UploadedFile;

// Single file from $_FILES['avatar']
$file = UploadedFile::fromInput('avatar');

if ($file && $file->isValid()) {
    $path = $file->store('avatars');     // 'avatars/<random-hex>.jpg'
    $url  = Storage::disk()->url($path); // '/storage/avatars/<hash>.jpg'
}
```

`store()` generates a random hex filename with the original extension to avoid collisions and path traversal.

### File metadata

```php
$file->getExtension();     // 'jpg'
$file->getMimeType();      // 'image/jpeg'
$file->getSize();          // bytes
$file->getOriginalName();  // 'profile-photo.jpg'
$file->isValid();          // true when upload succeeded and file is not empty
```

### Multiple uploads

```php
$files = UploadedFile::fromInputMultiple('documents');

foreach ($files as $file) {
    if ($file->isValid()) {
        $file->store('docs');
    }
}
```

## Testing

```php
use Core\Storage\Storage;

// Swap for an in-memory fake in tests
Storage::fake();

// ... call code that uses Storage::disk()

// Assert
Storage::fake()->assertExists('avatars/test.jpg');
Storage::fake()->assertMissing('avatars/deleted.jpg');

// Reset after test
Storage::reset();
```
