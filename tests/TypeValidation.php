<?php

use Core\Validations\Validations;
use Core\Validations\ValidationsMethod;

test('string validation passes', function () {
    $value = 'Hello World';
    $errors = Validations::ValidateField($value, [ValidationsMethod::STRING]);
    expect($errors)->toBeEmpty();
});

test('int validation passes', function () {
    $value = 42;
    $errors = Validations::ValidateField($value, [ValidationsMethod::INT]);
    expect($errors)->toBeEmpty();
});

test('float validation passes', function () {
    $value = 3.14;
    $errors = Validations::ValidateField($value, [ValidationsMethod::FLOAT]);
    expect($errors)->toBeEmpty();
});

test('boolean validation passes', function () {
    $value = true;
    $errors = Validations::ValidateField($value, [ValidationsMethod::BOOLEAN]);
    expect($errors)->toBeEmpty();
}); 

test('date validation passes', function () {
    $value = '2025-01-01';
    $errors = Validations::ValidateField($value, [ValidationsMethod::DATE]);
    expect($errors)->toBeEmpty();
});

test('date validation fails', function () {
    $value = 'not-a-date';
    $errors = Validations::ValidateField($value, [ValidationsMethod::DATE]);
    expect($errors)->not->toBeEmpty();
});

test('email validation passes', function () {
    $value = 'user@example.com';
    $errors = Validations::ValidateField($value, [ValidationsMethod::EMAIL]);
    expect($errors)->toBeEmpty();
});

test('email validation fails', function () {
    $value = 'not-an-email';
    $errors = Validations::ValidateField($value, [ValidationsMethod::EMAIL]);
    expect($errors)->not->toBeEmpty();
});

test('not null validation passes', function () {
    $value = 'Hello World';
    $errors = Validations::ValidateField($value, [ValidationsMethod::NOTNULL]);
    expect($errors)->toBeEmpty();
});

test('not null validation fails', function () {
    $value = null;
    $errors = Validations::ValidateField($value, [ValidationsMethod::NOTNULL]);
    expect($errors)->not->toBeEmpty();
});