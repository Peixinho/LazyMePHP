<?php

declare(strict_types=1);

use Core\DB\Ident;
use Core\LazyMePHP;
use Core\Migration\Blueprint;
use Core\Migration\Schema;
use Core\Model;

beforeEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('Ident quoting', function () {
    it('uses double quotes for sqlite', function () {
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_FILE_PATH'] = ':memory:';
        new LazyMePHP();
        expect(Ident::quote('users'))->toBe('"users"');
        expect(Ident::quotePath('users.id'))->toBe('"users"."id"');
    });

    it('uses backticks for mysql', function () {
        $_ENV['DB_TYPE'] = 'mysql';
        $_ENV['DB_NAME'] = 'app';
        $_ENV['DB_USER'] = 'root';
        $_ENV['DB_PASSWORD'] = '';
        $_ENV['DB_HOST'] = 'localhost';
        new LazyMePHP();
        expect(Ident::quote('users'))->toBe('`users`');
        expect(Ident::quotePath('users.id'))->toBe('`users`.`id`');
        expect(Ident::quoteList(['a', 'b']))->toBe('`a`, `b`');
    });

    it('uses brackets for mssql', function () {
        $_ENV['DB_TYPE'] = 'mssql';
        $_ENV['DB_NAME'] = 'app';
        $_ENV['DB_USER'] = 'sa';
        $_ENV['DB_PASSWORD'] = '';
        $_ENV['DB_HOST'] = 'localhost';
        new LazyMePHP();
        expect(Ident::quote('users'))->toBe('[users]');
        expect(Ident::quotePath('users.id'))->toBe('[users].[id]');
    });
});

describe('Schema Blueprint DDL', function () {
    it('emits sqlite AUTOINCREMENT for id()', function () {
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_FILE_PATH'] = ':memory:';
        new LazyMePHP();

        $bp = new Blueprint('posts', 'create');
        $bp->id();
        $bp->string('title');
        $sql = $bp->toSql();

        expect($sql)->toContain('CREATE TABLE "posts"');
        expect($sql)->toContain('"id" INTEGER PRIMARY KEY AUTOINCREMENT');
        expect($sql)->toContain('"title" TEXT NOT NULL');
        expect($sql)->not->toContain('AUTO_INCREMENT');
    });

    it('emits mysql AUTO_INCREMENT and VARCHAR for the same blueprint', function () {
        $_ENV['DB_TYPE'] = 'mysql';
        $_ENV['DB_NAME'] = 'app';
        $_ENV['DB_USER'] = 'root';
        $_ENV['DB_HOST'] = 'localhost';
        new LazyMePHP();

        $bp = new Blueprint('posts', 'create');
        $bp->id();
        $bp->string('title');
        $bp->text('body')->nullable();
        $sql = $bp->toSql();

        expect($sql)->toContain('CREATE TABLE `posts`');
        expect($sql)->toContain('`id` INT AUTO_INCREMENT PRIMARY KEY');
        expect($sql)->toContain('`title` VARCHAR(255) NOT NULL');
        expect($sql)->toContain('`body` TEXT');
        expect($sql)->not->toContain('AUTOINCREMENT');
    });

    it('runs Schema::create on sqlite end-to-end', function () {
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_FILE_PATH'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        new LazyMePHP();

        Schema::create('demo_posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });

        $tables = Model::listTables();
        expect($tables)->toContain('demo_posts');

        Schema::dropIfExists('demo_posts');
        expect(Model::listTables())->not->toContain('demo_posts');
    });
});

describe('hasDatabaseConfig / boot without DB', function () {
    it('reports false when mysql has empty DB_NAME', function () {
        $_ENV['DB_TYPE'] = 'mysql';
        $_ENV['DB_NAME'] = '';
        $_ENV['DB_USER'] = '';
        $_ENV['DB_HOST'] = 'localhost';
        new LazyMePHP();
        expect(LazyMePHP::hasDatabaseConfig())->toBeFalse();
        expect(LazyMePHP::DB_CONNECTION())->toBeNull();
        expect(Model::listTables())->toBe([]);
    });

    it('reports false when DB_TYPE is none', function () {
        $_ENV['DB_TYPE'] = 'none';
        new LazyMePHP();
        expect(LazyMePHP::hasDatabaseConfig())->toBeFalse();
    });
});
