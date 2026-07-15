<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Tenancy\Tenant;

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();

    LazyMePHP::DB_CONNECTION()->query("CREATE TABLE tenants (
        id   INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL
    )");

    LazyMePHP::DB_CONNECTION()->query("INSERT INTO tenants (slug, name) VALUES ('acme', 'Acme Corp'), ('globex', 'Globex')");

    Tenant::clear();
});

afterEach(function () {
    Tenant::clear();
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('Tenant state', function () {
    it('is not resolved initially', function () {
        expect(Tenant::isResolved())->toBeFalse();
        expect(Tenant::id())->toBeNull();
    });

    it('set() makes the tenant available', function () {
        Tenant::set(['id' => 1, 'slug' => 'acme', 'name' => 'Acme Corp']);
        expect(Tenant::isResolved())->toBeTrue();
        expect(Tenant::id())->toBe(1);
        expect(Tenant::slug())->toBe('acme');
        expect(Tenant::get('name'))->toBe('Acme Corp');
    });

    it('clear() removes the tenant', function () {
        Tenant::set(['id' => 1, 'slug' => 'acme', 'name' => 'Acme Corp']);
        Tenant::clear();
        expect(Tenant::isResolved())->toBeFalse();
    });

    it('get() returns default for unknown key', function () {
        Tenant::set(['id' => 5]);
        expect(Tenant::get('missing', 'fallback'))->toBe('fallback');
    });
});
