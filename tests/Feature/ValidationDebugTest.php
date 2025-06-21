<?php

use Core\Validations\Validations;
use Core\Validations\ValidationsMethod;
use Core\Validations\ValidationPatterns;

beforeEach(function () {
    // Ensure we have a clean environment for each test
    if (file_exists(__DIR__ . '/../../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
    }
});

describe('Validation Debug System', function () {
    it('should validate string patterns correctly', function () {
        $testString = 'Test Caracteristica ' . time();
        
        // Test regex pattern
        $matches = preg_match(ValidationPatterns::STRING, $testString);
        expect($matches)->toBe(1);
        
        // Test ValidateString method
        $stringValid = Validations::ValidateString($testString);
        expect($stringValid)->toBeTrue();
        
        // Test ValidateRegExp method
        $regexValid = Validations::ValidateRegExp($testString, ValidationPatterns::STRING);
        expect($regexValid)->toBeTrue();
    });

    it('should handle different string types correctly', function () {
        $testStrings = [
            'Simple text',
            'Text with numbers 123',
            'Text with special chars: !@#$%^&*()',
            'Text with spaces and tabs',
            'Unicode text: ñáéíóú',
            'Text with timestamp: ' . time(),
            'Text with date: ' . date('Y-m-d H:i:s')
        ];
        
        foreach ($testStrings as $str) {
            $valid = Validations::ValidateString($str);
            $match = preg_match(ValidationPatterns::STRING, $str);
            
            expect($valid)->toBeTrue();
            expect($match)->toBe(1);
        }
    });

    it('should validate JSON data with rules correctly', function () {
        $validationRules = [
            'Caracteristica' => [
                'type' => 'string',
                'required' => true,
                'validations' => [ValidationsMethod::NOTNULL, ValidationsMethod::STRING],
                'messages' => [
                    ValidationsMethod::NOTNULL->value => 'Caracteristica is required',
                    ValidationsMethod::STRING->value => 'Caracteristica must be a valid string'
                ]
            ]
        ];
        
        $testData = ['Caracteristica' => 'Test Caracteristica ' . time()];
        $validationResult = Validations::ValidateJsonData($testData, $validationRules);
        
        expect($validationResult)->toBeArray();
        // Check if the result has the expected structure with 'success' key
        if (isset($validationResult['success'])) {
            expect($validationResult['success'])->toBeTrue();
        } else {
            // If not, check if it's a boolean result directly
            expect($validationResult)->toBeTrue();
        }
    });

    it('should handle required field validation', function () {
        $validationRules = [
            'required_field' => [
                'type' => 'string',
                'required' => true,
                'validations' => [ValidationsMethod::NOTNULL],
                'messages' => [
                    ValidationsMethod::NOTNULL->value => 'Field is required'
                ]
            ]
        ];
        
        // Test with missing required field
        $testData = [];
        $validationResult = Validations::ValidateJsonData($testData, $validationRules);
        
        expect($validationResult)->toBeArray();
        // Check if the result has the expected structure with 'success' key
        if (isset($validationResult['success'])) {
            expect($validationResult['success'])->toBeFalse();
            expect($validationResult)->toHaveKey('errors');
        } else {
            // If not, check if it's a boolean result directly
            expect($validationResult)->toBeFalse();
        }
        
        // Test with present required field
        $testData = ['required_field' => 'test value'];
        $validationResult = Validations::ValidateJsonData($testData, $validationRules);
        
        if (isset($validationResult['success'])) {
            expect($validationResult['success'])->toBeTrue();
        } else {
            expect($validationResult)->toBeTrue();
        }
    });

    it('should handle string validation patterns', function () {
        // Test the STRING pattern constant
        expect(ValidationPatterns::STRING)->toBeString();
        expect(ValidationPatterns::STRING)->not->toBeEmpty();
        
        // Test that it's a valid regex pattern - should not throw
        preg_match(ValidationPatterns::STRING, 'test');
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle validation method enums', function () {
        // Test that validation methods are properly defined
        expect(ValidationsMethod::NOTNULL)->toBeInstanceOf(ValidationsMethod::class);
        expect(ValidationsMethod::STRING)->toBeInstanceOf(ValidationsMethod::class);
        
        // Test enum values
        expect(ValidationsMethod::NOTNULL->value)->toBeString();
        expect(ValidationsMethod::STRING->value)->toBeString();
    });

    it('should handle complex validation scenarios', function () {
        $validationRules = [
            'name' => [
                'type' => 'string',
                'required' => true,
                'validations' => [ValidationsMethod::NOTNULL, ValidationsMethod::STRING],
                'messages' => [
                    ValidationsMethod::NOTNULL->value => 'Name is required',
                    ValidationsMethod::STRING->value => 'Name must be a valid string'
                ]
            ],
            'email' => [
                'type' => 'string',
                'required' => false,
                'validations' => [ValidationsMethod::STRING],
                'messages' => [
                    ValidationsMethod::STRING->value => 'Email must be a valid string'
                ]
            ]
        ];
        
        // Test with valid data
        $testData = [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];
        $validationResult = Validations::ValidateJsonData($testData, $validationRules);
        
        if (isset($validationResult['success'])) {
            expect($validationResult['success'])->toBeTrue();
        } else {
            expect($validationResult)->toBeTrue();
        }
        
        // Test with invalid data (missing required field)
        $testData = [
            'email' => 'john@example.com'
        ];
        $validationResult = Validations::ValidateJsonData($testData, $validationRules);
        
        if (isset($validationResult['success'])) {
            expect($validationResult['success'])->toBeFalse();
            expect($validationResult)->toHaveKey('errors');
        } else {
            expect($validationResult)->toBeFalse();
        }
    });

    it('should handle edge cases in string validation', function () {
        $edgeCases = [
            '', // Empty string
            '   ', // Whitespace only
            'a', // Single character
            str_repeat('a', 1000), // Very long string
            '0', // String zero
            'false', // String false
            'null', // String null
        ];
        
        foreach ($edgeCases as $str) {
            $valid = Validations::ValidateString($str);
            $match = preg_match(ValidationPatterns::STRING, $str);
            
            // All should be valid strings
            expect($valid)->toBeTrue();
            expect($match)->toBe(1);
        }
    });
}); 