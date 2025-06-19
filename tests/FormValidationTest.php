<?php

use Core\Validations\Validations;
use Core\Validations\ValidationsMethod;

test('form required field validation', function () {
    $_POST = [];
    $rules = [
        'name' => [
            'type' => 'string',
            'required' => true,
            'validations' => [ValidationsMethod::NOTNULL, ValidationsMethod::STRING],
        ],
    ];
    $_POST['name'] = 'John Doe';
    $result = Validations::ValidateFormData($rules);
    expect($result['errors'])->toBeArray()->toBeEmpty();
});

test('form missing required field', function () {
    $_POST = [];
    $rules = [
        'email' => [
            'type' => 'string',
            'required' => true,
            'validations' => [ValidationsMethod::NOTNULL, ValidationsMethod::EMAIL],
            'messages' => [ValidationsMethod::NOTNULL->value => 'Email is required.']
        ],
    ];
    $result = Validations::ValidateFormData($rules);
    expect($result['errors'])->toHaveKey('email');
    expect($result['errors']['email'][0])->toBe('Field is required.');
}); 

test('form valid email', function () {
    $_POST = [];
    $rules = [
        'email' => [
            'type' => 'string',
            'required' => true,
            'validations' => [ValidationsMethod::NOTNULL, ValidationsMethod::EMAIL],
        ],
    ];
    $_POST['email'] = 'user@example.com';
    $result = Validations::ValidateFormData($rules);
    expect($result['errors'])->toBeArray()->toBeEmpty();
});

test('form invalid email', function () {
    $_POST = [];
    $rules = [
        'email' => [
            'type' => 'string',
            'required' => true,
            'validations' => [ValidationsMethod::NOTNULL, ValidationsMethod::EMAIL],
        ],
    ];
    $_POST['email'] = 'not-an-email';
    $result = Validations::ValidateFormData($rules);
    expect($result['errors'])->toHaveKey('email');
    expect($result['errors']['email'][0])->toBe('Value must be a valid email address.');
}); 

test('form valid date', function () {
    $_POST = [];
    $rules = [
        'date' => [
            'type' => 'iso_date',
            'required' => true,
            'validations' => [ValidationsMethod::NOTNULL, ValidationsMethod::DATE],
        ],
    ];
    $_POST['date'] = '2025-01-01';
    $result = Validations::ValidateFormData($rules);
    expect($result['errors'])->toBeArray()->toBeEmpty();
});

test('form invalid date', function () {
    $_POST = [];
    $rules = [
        'date' => [
            'type' => 'iso_date',
            'required' => true,
            'validations' => [ValidationsMethod::NOTNULL, ValidationsMethod::DATE],
        ],
    ];
    $_POST['date'] = 'not-a-date';
    $result = Validations::ValidateFormData($rules);
    expect($result['errors'])->toHaveKey('date');
    expect($result['errors']['date'][0])->toBe('Value must be a valid date.');
}); 

test('form valid string', function () {
    $_POST = [];
    $rules = [
        'string' => [
            'type' => 'string',
            'required' => true,
            'validations' => [ValidationsMethod::NOTNULL, ValidationsMethod::STRING],
        ],
    ];
    $_POST['string'] = 'Hello World';
    $result = Validations::ValidateFormData($rules);
    expect($result['errors'])->toBeArray()->toBeEmpty();
});