<?php

declare(strict_types=1);

use Core\Storage\Storage;
use Core\Storage\LocalDisk;

beforeEach(function () {
    Storage::reset();
    // Use a temp dir for all tests
    Storage::fake('local', new LocalDisk(sys_get_temp_dir() . '/lazyme_test_storage'));
});

afterEach(function () {
    // Clean up temp files
    $disk = Storage::disk();
    foreach ($disk->files() as $f) {
        $disk->delete($f);
    }
    Storage::reset();
});

describe('LocalDisk', function () {
    it('put() and get() work round-trip', function () {
        Storage::disk()->put('hello.txt', 'world');
        expect(Storage::disk()->get('hello.txt'))->toBe('world');
    });

    it('exists() returns true after put()', function () {
        Storage::disk()->put('ex.txt', 'yes');
        expect(Storage::disk()->exists('ex.txt'))->toBeTrue();
    });

    it('exists() returns false for missing file', function () {
        expect(Storage::disk()->exists('nope.txt'))->toBeFalse();
    });

    it('delete() removes the file', function () {
        Storage::disk()->put('del.txt', 'bye');
        Storage::disk()->delete('del.txt');
        expect(Storage::disk()->exists('del.txt'))->toBeFalse();
    });

    it('get() returns null for missing file', function () {
        expect(Storage::disk()->get('missing.txt'))->toBeNull();
    });

    it('size() returns correct byte count', function () {
        Storage::disk()->put('size.txt', 'hello');
        expect(Storage::disk()->size('size.txt'))->toBe(5);
    });

    it('url() returns the expected path', function () {
        $disk = new LocalDisk('/tmp/test', '/files');
        expect($disk->url('images/logo.png'))->toBe('/files/images/logo.png');
    });

    it('files() lists stored files', function () {
        Storage::disk()->put('a.txt', 'a');
        Storage::disk()->put('b.txt', 'b');
        $files = Storage::disk()->files();
        expect(count($files))->toBeGreaterThanOrEqual(2);
    });

    it('put() creates subdirectories automatically', function () {
        Storage::disk()->put('nested/dir/file.txt', 'deep');
        expect(Storage::disk()->exists('nested/dir/file.txt'))->toBeTrue();
        Storage::disk()->delete('nested/dir/file.txt');
    });
});
