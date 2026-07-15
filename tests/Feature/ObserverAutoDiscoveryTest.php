<?php

declare(strict_types=1);

use Core\Events\ModelEvents;
use Core\LazyMePHP;
use Core\Model;

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();
    ModelEvents::clearAll();
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
    ModelEvents::clearAll();
});

describe('Observer auto-discovery', function () {
    it('registers an observer class that declares $table', function () {
        // Simulate what bootstrap does: scan a directory for observer files
        $tmpDir = sys_get_temp_dir() . '/lazyme_observer_test_' . uniqid();
        mkdir($tmpDir);

        file_put_contents($tmpDir . '/ProductObserver.php', <<<'PHP'
<?php
class ProductObserver
{
    protected static string $table = 'products';
    public static int $createdCount = 0;

    public function created(mixed $model): void
    {
        self::$createdCount++;
    }
}
PHP);

        // Run discovery logic (mirrors bootstrap.php)
        foreach (glob($tmpDir . '/*.php') ?: [] as $file) {
            require_once $file;
            $className = basename($file, '.php');
            if (class_exists($className)) {
                $ref   = new ReflectionClass($className);
                $table = $ref->hasProperty('table')
                    ? $ref->getStaticPropertyValue('table', null)
                    : null;
                if ($table) {
                    ModelEvents::registerObserver($table, new $className());
                }
            }
        }

        // Fire the event
        ModelEvents::fire('products', 'created', new stdClass());

        expect(ProductObserver::$createdCount)->toBe(1);

        unlink($tmpDir . '/ProductObserver.php');
        rmdir($tmpDir);
    });

    it('ignores observer files without a $table property', function () {
        $tmpDir = sys_get_temp_dir() . '/lazyme_observer_notbl_' . uniqid();
        mkdir($tmpDir);

        file_put_contents($tmpDir . '/OrphanObserver.php', <<<'PHP'
<?php
class OrphanObserver
{
    public static int $hit = 0;
    public function created(mixed $model): void { self::$hit++; }
}
PHP);

        foreach (glob($tmpDir . '/*.php') ?: [] as $file) {
            require_once $file;
            $className = basename($file, '.php');
            if (class_exists($className)) {
                $ref   = new ReflectionClass($className);
                $table = $ref->hasProperty('table')
                    ? $ref->getStaticPropertyValue('table', null)
                    : null;
                if ($table) {
                    ModelEvents::registerObserver($table, new $className());
                }
            }
        }

        ModelEvents::fire('products', 'created', new stdClass());
        expect(OrphanObserver::$hit)->toBe(0);

        unlink($tmpDir . '/OrphanObserver.php');
        rmdir($tmpDir);
    });

    it('ModelEvents::registerObserver wires all lifecycle events', function () {
        $log = [];

        $observer = new class($log) {
            public function __construct(private array &$log) {}
            public function creating(mixed $m): void  { $this->log[] = 'creating'; }
            public function created(mixed $m): void   { $this->log[] = 'created'; }
            public function updating(mixed $m): void  { $this->log[] = 'updating'; }
            public function updated(mixed $m): void   { $this->log[] = 'updated'; }
            public function deleting(mixed $m): void  { $this->log[] = 'deleting'; }
            public function deleted(mixed $m): void   { $this->log[] = 'deleted'; }
        };

        ModelEvents::registerObserver('widgets', $observer);

        foreach (['creating','created','updating','updated','deleting','deleted'] as $ev) {
            ModelEvents::fire('widgets', $ev, new stdClass());
        }

        expect($log)->toBe(['creating','created','updating','updated','deleting','deleted']);
    });
});
