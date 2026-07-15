<?php

declare(strict_types=1);

use Core\Console\Command;

class GreetCommand extends Command
{
    protected string $name        = 'greet';
    protected string $description = 'Say hello';

    public string $output = '';

    public function handle(): void
    {
        $name = $this->argument(0) ?? 'World';
        $this->output = "Hello, {$name}!";
    }
}

class EchoOptionCommand extends Command
{
    protected string $name = 'echo:opt';

    public ?string $capturedOption = null;

    public function handle(): void
    {
        $this->capturedOption = $this->option('queue');
    }
}

describe('Console Command base class', function () {
    it('handle() is called with no args', function () {
        $cmd = new GreetCommand();
        $cmd->setArgs([]);
        $cmd->handle();

        expect($cmd->output)->toBe('Hello, World!');
    });

    it('argument() returns positional args', function () {
        $cmd = new GreetCommand();
        $cmd->setArgs(['Alice']);
        $cmd->handle();

        expect($cmd->output)->toBe('Hello, Alice!');
    });

    it('option() parses --name=value flags', function () {
        $cmd = new EchoOptionCommand();
        $cmd->setArgs(['--queue=high']);
        $cmd->handle();

        expect($cmd->capturedOption)->toBe('high');
    });

    it('option() returns null for missing flags', function () {
        $cmd = new EchoOptionCommand();
        $cmd->setArgs([]);
        $cmd->handle();

        expect($cmd->capturedOption)->toBeNull();
    });

    it('option() returns "true" for bare flags', function () {
        $cmd = new EchoOptionCommand();
        $cmd->setArgs(['--queue']);
        $cmd->handle();

        expect($cmd->capturedOption)->toBe('true');
    });

    it('getName() and getDescription() return properties', function () {
        $cmd = new GreetCommand();
        expect($cmd->getName())->toBe('greet');
        expect($cmd->getDescription())->toBe('Say hello');
    });
});
