<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Factory\Factory;

// ---------------------------------------------------------------------------
// Test factory
// ---------------------------------------------------------------------------

class WidgetFactory extends Factory
{
    protected string $table = 'widgets';

    public function definition(): array
    {
        static $n = 0;
        $n++;
        return [
            'name'  => "Widget {$n}",
            'price' => $n * 10,
        ];
    }
}

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    LazyMePHP::DB_CONNECTION()->query("CREATE TABLE widgets (
        id    INTEGER PRIMARY KEY AUTOINCREMENT,
        name  TEXT NOT NULL,
        price REAL NOT NULL DEFAULT 0
    )");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('Factory::make()', function () {
    it('returns an unsaved Model instance', function () {
        $w = WidgetFactory::new()->make();

        expect($w)->toBeInstanceOf(Model::class);
        expect($w->getPrimaryKey())->toBeNull();
        expect($w->name)->toStartWith('Widget');
    });

    it('applies overrides', function () {
        $w = WidgetFactory::new()->make(['name' => 'Custom', 'price' => 999]);
        expect($w->name)->toBe('Custom');
        expect((float)$w->price)->toBe(999.0);
    });

    it('does not persist to the database', function () {
        WidgetFactory::new()->make();
        expect(Model::query('widgets')->count())->toBe(0);
    });
});

describe('Factory::create()', function () {
    it('persists and returns a model', function () {
        $w = WidgetFactory::new()->create();

        expect($w->getPrimaryKey())->not->toBeNull();
        expect(Model::query('widgets')->count())->toBe(1);
    });

    it('creates multiple models with count()', function () {
        $widgets = WidgetFactory::new()->count(5)->create();

        expect($widgets)->toBeArray()->toHaveCount(5);
        expect(Model::query('widgets')->count())->toBe(5);
    });
});

describe('Factory::state()', function () {
    it('merges state overrides into the definition', function () {
        $w = WidgetFactory::new()->state(['price' => 0])->create();
        expect((float)$w->price)->toBe(0.0);
    });
});
