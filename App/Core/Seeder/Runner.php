<?php

declare(strict_types=1);

namespace Core\Seeder;

/**
 * Discovers and runs all Seeder subclasses in App/Seeders/.
 *
 * CLI: php lazymephp db:seed [--class=SpecificSeeder]
 */
class Runner
{
    private string $seedersPath;

    public function __construct(?string $seedersPath = null)
    {
        $this->seedersPath = $seedersPath ?? __DIR__ . '/../../Seeders';
    }

    /**
     * Run all seeders (or just the one specified by class name).
     */
    public function run(?string $onlyClass = null): void
    {
        $files = glob($this->seedersPath . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            require_once $file;

            $class = pathinfo($file, PATHINFO_FILENAME);
            if ($onlyClass !== null && $class !== $onlyClass) continue;

            if (!class_exists($class)) continue;

            $seeder = new $class();
            if (!($seeder instanceof Seeder)) continue;

            echo "[seed] Running {$class}...\n";
            $seeder->run();
            echo "[seed] {$class} done.\n";
        }
    }

    /**
     * Return a list of discovered seeder class names (without running them).
     *
     * @return list<string>
     */
    public function discover(): array
    {
        $files   = glob($this->seedersPath . '/*.php') ?: [];
        $classes = [];
        foreach ($files as $file) {
            $classes[] = pathinfo($file, PATHINFO_FILENAME);
        }
        sort($classes);
        return $classes;
    }
}
