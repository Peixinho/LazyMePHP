<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\GraphQL\SchemaBuilder;

describe('Generation Pipeline Test', function () {
    beforeEach(function () {
        $this->testDbPath = sys_get_temp_dir() . '/lazyme_gen_test_' . getmypid() . '.db';
        $this->generatedModelsPath   = sys_get_temp_dir() . '/lazyme_models_' . getmypid();
        $this->generatedApiPath      = sys_get_temp_dir() . '/lazyme_api_' . getmypid();

        foreach ([$this->generatedModelsPath, $this->generatedApiPath] as $dir) {
            if (!is_dir($dir)) mkdir($dir, 0755, true);
        }

        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_FILE_PATH'] = $this->testDbPath;
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        $_ENV['APP_ENV'] = 'testing';

        LazyMePHP::reset();
        new LazyMePHP();

        $db = LazyMePHP::DB_CONNECTION();
        $db->query("DROP TABLE IF EXISTS GenUsers");
        $db->query("CREATE TABLE GenUsers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL
        )");
        $db->query("INSERT INTO GenUsers (username, email) VALUES ('alice', 'alice@test.com')");
    });

    afterEach(function () {
        $removeDir = function ($path) use (&$removeDir) {
            if (!is_dir($path)) return;
            foreach (array_diff(scandir($path), ['.', '..']) as $f) {
                $full = "$path/$f";
                is_dir($full) ? $removeDir($full) : unlink($full);
            }
            rmdir($path);
        };
        $removeDir($this->generatedModelsPath);
        $removeDir($this->generatedApiPath);
        if (file_exists($this->testDbPath)) unlink($this->testDbPath);
        LazyMePHP::reset();
    });

    it('Model introspects schema from SQLite table at runtime', function () {
        Model::clearSchemaCache();
        $model = new Model('GenUsers');
        $columns = $model->getColumns();

        expect($columns)->toContain('id');
        expect($columns)->toContain('username');
        expect($columns)->toContain('email');
    });

    it('Model can CRUD records via runtime schema introspection', function () {
        Model::clearSchemaCache();

        $user = new Model('GenUsers');
        $user->username = 'bob';
        $user->email = 'bob@test.com';
        $user->Save();

        $id = $user->getPrimaryKey();
        expect($id)->toBeGreaterThan(0);

        $loaded = new Model('GenUsers', $id);
        expect($loaded->username)->toBe('bob');

        $loaded->username = 'bobby';
        $loaded->Save();

        $updated = new Model('GenUsers', $id);
        expect($updated->username)->toBe('bobby');

        $updated->Delete();
        $gone = new Model('GenUsers', $id);
        expect($gone->getPrimaryKey())->toBeNull();
    });

    it('GraphQL schema exposes query fields for each table', function () {
        Model::clearSchemaCache();
        $schema    = SchemaBuilder::build(['GenUsers']);
        $queryType = $schema->getQueryType();

        expect($queryType)->not->toBeNull();
        $fields = $queryType->getFields();
        expect($fields)->toHaveKey('genUsers');
        expect($fields)->toHaveKey('genUsersList');
    });

    it('GraphQL schema exposes mutation fields for each table', function () {
        Model::clearSchemaCache();
        $schema       = SchemaBuilder::build(['GenUsers']);
        $mutationType = $schema->getMutationType();

        expect($mutationType)->not->toBeNull();
        $fields = $mutationType->getFields();
        expect($fields)->toHaveKey('createGenUsers');
        expect($fields)->toHaveKey('updateGenUsers');
        expect($fields)->toHaveKey('deleteGenUsers');
    });
});
