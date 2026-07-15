<?php

declare(strict_types=1);

use Core\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|min:3|max:255',
            'email' => 'required|email',
            'age'   => 'integer|min:0|max:120',
            'site'  => 'url',
            'role'  => 'in:admin,editor,viewer',
        ];
    }
}

class ProtectedRequest extends FormRequest
{
    public function rules(): array { return ['x' => 'required']; }
    public function authorize(): bool { return false; }
}

describe('FormRequest validation', function () {
    it('passes() returns true for valid input', function () {
        $req = new CreatePostRequest(['title' => 'Hello World', 'email' => 'a@b.com']);
        expect($req->passes())->toBeTrue();
        expect($req->errors())->toBeEmpty();
    });

    it('fails when required field is missing', function () {
        $req = new CreatePostRequest(['title' => 'Hi']);
        expect($req->fails())->toBeTrue();
        expect($req->errors())->toHaveKey('email');
    });

    it('fails when min length not met', function () {
        $req = new CreatePostRequest(['title' => 'Hi', 'email' => 'a@b.com']);
        expect($req->fails())->toBeTrue();
        expect($req->errors())->toHaveKey('title');
    });

    it('fails when email is invalid', function () {
        $req = new CreatePostRequest(['title' => 'Hello World', 'email' => 'not-an-email']);
        expect($req->fails())->toBeTrue();
        expect($req->errors())->toHaveKey('email');
    });

    it('fails when value not in allowed list', function () {
        $req = new CreatePostRequest(['title' => 'Hello World', 'email' => 'a@b.com', 'role' => 'superuser']);
        expect($req->fails())->toBeTrue();
        expect($req->errors())->toHaveKey('role');
    });

    it('fails when url is invalid', function () {
        $req = new CreatePostRequest(['title' => 'Hello World', 'email' => 'a@b.com', 'site' => 'not-a-url']);
        expect($req->fails())->toBeTrue();
        expect($req->errors())->toHaveKey('site');
    });

    it('skips optional fields when not provided', function () {
        $req = new CreatePostRequest(['title' => 'Hello World', 'email' => 'a@b.com']);
        expect($req->passes())->toBeTrue();
    });

    it('authorize() returning false causes failure', function () {
        $req = new ProtectedRequest(['x' => 'value']);
        expect($req->fails())->toBeTrue();
        expect($req->errors())->toHaveKey('_authorize');
    });

    it('validated() returns only rule fields', function () {
        $req = new CreatePostRequest(['title' => 'Hello World', 'email' => 'a@b.com', 'injected' => 'evil']);
        $req->passes();
        $data = $req->validated();
        expect($data)->toHaveKey('title');
        expect($data)->not->toHaveKey('injected');
    });

    it('input() reads individual values', function () {
        $req = new CreatePostRequest(['title' => 'Test', 'email' => 'a@b.com']);
        expect($req->input('title'))->toBe('Test');
        expect($req->input('missing', 'default'))->toBe('default');
    });
});
