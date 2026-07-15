<?php

declare(strict_types=1);

use Core\LazyMePHP;
use Core\Model;
use Core\Http\ApiResource;

class UserResource extends ApiResource
{
    public function toArray(): array
    {
        return [
            'id'    => $this->model->id,
            'name'  => $this->model->name,
            // email intentionally omitted
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

    $db = LazyMePHP::DB_CONNECTION();
    $db->query("CREATE TABLE res_users (
        id    INTEGER PRIMARY KEY AUTOINCREMENT,
        name  TEXT NOT NULL,
        email TEXT NOT NULL
    )");
    $db->query("INSERT INTO res_users (name, email) VALUES ('Alice', 'alice@example.com'), ('Bob', 'bob@example.com')");
});

afterEach(function () {
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

describe('ApiResource::make()', function () {
    it('wraps a single model under a data key', function () {
        $user     = new Model('res_users', 1);
        $resource = UserResource::make($user);
        $arr      = $resource->toResponseArray();

        expect($arr)->toHaveKey('data');
        expect($arr['data'])->toHaveKey('id');
        expect($arr['data'])->toHaveKey('name');
        expect($arr['data'])->not->toHaveKey('email'); // omitted
        expect($arr['data']['name'])->toBe('Alice');
    });

    it('toJson() returns valid JSON', function () {
        $user = new Model('res_users', 1);
        $json = UserResource::make($user)->toJson();
        $decoded = json_decode($json, true);

        expect($decoded)->toHaveKey('data');
        expect($decoded['data']['name'])->toBe('Alice');
    });
});

describe('ApiResource::collection()', function () {
    it('wraps multiple models under a data array', function () {
        $users = Model::query('res_users')->get();
        $arr   = UserResource::collection($users)->toResponseArray();

        expect($arr)->toHaveKey('data');
        expect($arr['data'])->toHaveCount(2);
        expect($arr['data'][0]['name'])->toBe('Alice');
        expect($arr['data'][1]['name'])->toBe('Bob');
    });

    it('does not expose fields omitted by toArray()', function () {
        $users = Model::query('res_users')->get();
        $arr   = UserResource::collection($users)->toResponseArray();

        foreach ($arr['data'] as $item) {
            expect($item)->not->toHaveKey('email');
        }
    });
});

describe('ApiResource::withMeta()', function () {
    it('attaches metadata to the response', function () {
        $users = Model::query('res_users')->get();
        $arr   = UserResource::collection($users)->withMeta(['total' => 2, 'page' => 1])->toResponseArray();

        expect($arr)->toHaveKey('meta');
        expect($arr['meta']['total'])->toBe(2);
    });
});
