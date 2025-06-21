<?php

use Core\Validations\Validations;
use Core\Validations\ValidationsMethod;

describe('Validation System', function () {
    
    describe('Type Validation', function () {
        it('should validate string correctly', function () {
            $value = 'Hello World';
            $errors = Validations::ValidateField($value, [ValidationsMethod::STRING]);
            expect($errors)->toBeEmpty();
        });

        it('should validate int correctly', function () {
            $value = 42;
            $errors = Validations::ValidateField($value, [ValidationsMethod::INT]);
            expect($errors)->toBeEmpty();
        });

        it('should validate float correctly', function () {
            $value = 3.14;
            $errors = Validations::ValidateField($value, [ValidationsMethod::FLOAT]);
            expect($errors)->toBeEmpty();
        });

        it('should validate boolean correctly', function () {
            $value = true;
            $errors = Validations::ValidateField($value, [ValidationsMethod::BOOLEAN]);
            expect($errors)->toBeEmpty();
        });

        it('should validate date correctly', function () {
            $value = '2025-01-01';
            $errors = Validations::ValidateField($value, [ValidationsMethod::DATE]);
            expect($errors)->toBeEmpty();
        });

        it('should reject invalid date', function () {
            $value = 'not-a-date';
            $errors = Validations::ValidateField($value, [ValidationsMethod::DATE]);
            expect($errors)->not->toBeEmpty();
        });

        it('should validate email correctly', function () {
            $value = 'user@example.com';
            $errors = Validations::ValidateField($value, [ValidationsMethod::EMAIL]);
            expect($errors)->toBeEmpty();
        });

        it('should reject invalid email', function () {
            $value = 'not-an-email';
            $errors = Validations::ValidateField($value, [ValidationsMethod::EMAIL]);
            expect($errors)->not->toBeEmpty();
        });

        it('should validate not null correctly', function () {
            $value = 'Hello World';
            $errors = Validations::ValidateField($value, [ValidationsMethod::NOTNULL]);
            expect($errors)->toBeEmpty();
        });

        it('should reject null values', function () {
            $value = null;
            $errors = Validations::ValidateField($value, [ValidationsMethod::NOTNULL]);
            expect($errors)->not->toBeEmpty();
        });
    });

    describe('Form Validation', function () {
        it('should validate required field correctly', function () {
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

        it('should reject missing required field', function () {
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

        it('should validate valid email in form', function () {
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

        it('should reject invalid email in form', function () {
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

        it('should validate valid date in form', function () {
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

        it('should reject invalid date in form', function () {
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

        it('should validate valid string in form', function () {
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
    });
}); 