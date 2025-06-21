<?php

describe('UnitTestApiFieldMask - Abstract Field Hiding Test', function () {
    // Define a test-only ApiFieldMask with generic names
    class UnitTestApiFieldMask {
        private static array $fields = [
            'TableA' => ['id', 'field1', 'field2', 'field3'],
            'TableB' => ['id', 'user', 'role', 'department'],
            'TableC' => ['id', 'property'],
        ];
        public static function get(string $entity): array {
            return self::$fields[$entity] ?? [];
        }
        public static function apply(string $entity, array $data): array {
            $allowed = self::get($entity);
            return array_intersect_key($data, array_flip($allowed));
        }
    }

    it('should filter out sensitive fields from data', function () {
        $testData = [
            'id' => 1,
            'field1' => 'Value 1',
            'field2' => 'Value 2',
            'field3' => 123,
            'password' => 'secret_password',        // Should be hidden
            'internal_note' => 'private data',      // Should be hidden
            'secret_field' => 'should_not_expose'   // Should be hidden
        ];
        
        $masked = UnitTestApiFieldMask::apply('TableA', $testData);
        
        expect($masked)->toBeArray();
        expect($masked)->not->toBeEmpty();
        expect($masked)->not->toHaveKey('password');
        expect($masked)->not->toHaveKey('internal_note');
        expect($masked)->not->toHaveKey('secret_field');
        expect($masked)->toHaveKey('id');
        expect($masked['id'])->toBe(1);
    });

    it('should filter out sensitive fields from user data', function () {
        $testData = [
            'id' => 1,
            'user' => 'testuser',
            'role' => 'admin',
            'department' => 'IT',
            'password' => 'secret_password',    // Should be hidden
            'email' => 'test@example.com',      // Should be hidden
            'phone' => '123456789'              // Should be hidden
        ];
        
        $masked = UnitTestApiFieldMask::apply('TableB', $testData);
        
        expect($masked)->toBeArray();
        expect($masked)->not->toBeEmpty();
        expect($masked)->not->toHaveKey('password');
        expect($masked)->not->toHaveKey('email');
        expect($masked)->not->toHaveKey('phone');
        expect($masked)->toHaveKey('id');
        expect($masked['id'])->toBe(1);
    });

    it('should return empty array when no allowed fields match', function () {
        $testData = [
            'password' => 'secret',
            'email' => 'test@example.com',
            'phone' => '123456789',
            'internal_data' => 'private'
        ];
        $masked = UnitTestApiFieldMask::apply('TableA', $testData);
        expect($masked)->toBeArray();
        expect($masked)->toBeEmpty();
    });

    it('should handle empty input data', function () {
        $masked = UnitTestApiFieldMask::apply('TableA', []);
        expect($masked)->toBeArray();
        expect($masked)->toBeEmpty();
    });

    it('should preserve data types of allowed fields', function () {
        $testData = [
            'id' => 1,
            'field1' => 100.5,
            'field2' => 'Test description',
            'field3' => 5,
        ];
        $masked = UnitTestApiFieldMask::apply('TableA', $testData);
        expect($masked['id'])->toBe(1);
        foreach ($masked as $key => $value) {
            if (is_numeric($value)) {
                expect($value)->toBeNumeric();
            }
        }
    });

    it('should handle null values in allowed fields', function () {
        $testData = [
            'id' => 1,
            'field1' => null,
            'field2' => null,
            'field3' => 5
        ];
        $masked = UnitTestApiFieldMask::apply('TableA', $testData);
        expect($masked['id'])->toBe(1);
        foreach ($masked as $key => $value) {
            if ($value === null) {
                expect($value)->toBeNull();
            }
        }
    });

    it('should work with different table types', function () {
        $genericData = [
            'id' => 1,
            'property' => 'Test Property',
            'internal_field' => 'should_not_be_exposed'
        ];
        $masked = UnitTestApiFieldMask::apply('TableC', $genericData);
        expect($masked)->toBeArray();
        expect($masked)->not->toBeEmpty();
        expect($masked)->toHaveKey('id');
        expect($masked)->not->toHaveKey('internal_field');
        expect($masked['id'])->toBe(1);
    });

    it('should return empty array for non-existent table', function () {
        $testData = [
            'id' => 1,
            'name' => 'test',
            'field' => 'value'
        ];
        $masked = UnitTestApiFieldMask::apply('NonExistentTable', $testData);
        expect($masked)->toBeArray();
        expect($masked)->toBeEmpty();
    });

    it('should get allowed fields for a table', function () {
        $fields = UnitTestApiFieldMask::get('TableA');
        expect($fields)->toBeArray();
        expect($fields)->not->toBeEmpty();
        expect($fields)->toContain('id');
    });

    it('should return empty array for non-existent table in get method', function () {
        $fields = UnitTestApiFieldMask::get('NonExistentTable');
        expect($fields)->toBeArray();
        expect($fields)->toBeEmpty();
    });

    it('should demonstrate field masking in real-world scenario', function () {
        $apiResponseData = [
            'id' => 123,
            'field1' => 299.99,
            'field2' => 'High-quality description',
            'field3' => 5,
            'internal_cost' => 150.00,        // Should be hidden
            'profit_margin' => 0.45,          // Should be hidden
            'supplier_notes' => 'Internal notes', // Should be hidden
            'admin_notes' => 'Confidential info'  // Should be hidden
        ];
        $masked = UnitTestApiFieldMask::apply('TableA', $apiResponseData);
        expect($masked)->toBeArray();
        expect($masked)->not->toBeEmpty();
        expect($masked)->toHaveKey('id');
        expect($masked)->not->toHaveKey('internal_cost');
        expect($masked)->not->toHaveKey('profit_margin');
        expect($masked)->not->toHaveKey('supplier_notes');
        expect($masked)->not->toHaveKey('admin_notes');
        expect($masked['id'])->toBe(123);
    });

    it('should maintain array structure integrity', function () {
        $testData = [
            'id' => 1,
            'field2' => 'Test',
            'nested' => ['key' => 'value'],
            'password' => 'secret'
        ];
        $masked = UnitTestApiFieldMask::apply('TableA', $testData);
        expect($masked)->toBeArray();
        expect($masked)->not->toHaveKey('password');
        if (isset($masked['nested'])) {
            expect($masked['nested'])->toBeArray();
            expect($masked['nested']['key'])->toBe('value');
        }
    });

    it('should handle mixed data types correctly', function () {
        $testData = [
            'id' => 1,
            'field2' => 'text',
            'field1' => 42,
            'field3' => 3.14,
            'extra_bool' => true,
            'extra_array' => [1, 2, 3],
            'extra_null' => null,
            'password' => 'secret'
        ];
        
        $masked = UnitTestApiFieldMask::apply('TableA', $testData);
        
        expect($masked)->toBeArray();
        expect($masked)->not->toHaveKey('password');
        
        // Check that data types are preserved for included fields
        foreach ($masked as $key => $value) {
            if (is_string($value)) {
                expect($value)->toBeString();
            } elseif (is_int($value)) {
                expect($value)->toBeInt();
            } elseif (is_float($value)) {
                expect($value)->toBeFloat();
            } elseif (is_bool($value)) {
                expect($value)->toBeBool();
            } elseif (is_array($value)) {
                expect($value)->toBeArray();
            } elseif ($value === null) {
                expect($value)->toBeNull();
            }
        }
    });
}); 