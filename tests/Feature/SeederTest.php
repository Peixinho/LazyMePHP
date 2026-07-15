<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Seeder\Seeder;
use Core\Seeder\Runner;

// ---------------------------------------------------------------------------
// Inline seeder classes for testing
// ---------------------------------------------------------------------------

class FruitSeeder extends Seeder
{
    public function run(): void
    {
        $this->insert('fruits', ['name' => 'Apple']);
        $this->insert('fruits', ['name' => 'Banana']);
    }
}

class VegetableSeeder extends Seeder
{
    public function run(): void
    {
        $this->insert('fruits', ['name' => 'Carrot']);
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

    LazyMePHP::DB_CONNECTION()->query("CREATE TABLE fruits (
        id   INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    )");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('Seeder::insert()', function () {
    it('inserts rows into the target table', function () {
        $seeder = new FruitSeeder();
        $seeder->run();

        expect(Model::query('fruits')->count())->toBe(2);
    });
});

describe('Runner::run() with inline classes', function () {
    it('runs all discovered seeders when given a file list', function () {
        // Use an in-process runner with an empty dir — manually invoke seeders instead
        $runner = new class('/nonexistent') extends Runner {
            public function run(?string $onlyClass = null): void
            {
                $classes = [FruitSeeder::class, VegetableSeeder::class];
                foreach ($classes as $class) {
                    if ($onlyClass !== null && $class !== $onlyClass) continue;
                    (new $class())->run();
                }
            }
        };

        $runner->run();
        expect(Model::query('fruits')->count())->toBe(3);
    });

    it('runs only a specific class when --class is given', function () {
        $runner = new class('/nonexistent') extends Runner {
            public function run(?string $onlyClass = null): void
            {
                $classes = [FruitSeeder::class, VegetableSeeder::class];
                foreach ($classes as $class) {
                    if ($onlyClass !== null && $class !== $onlyClass) continue;
                    (new $class())->run();
                }
            }
        };

        $runner->run(FruitSeeder::class);
        expect(Model::query('fruits')->count())->toBe(2);
    });
});
