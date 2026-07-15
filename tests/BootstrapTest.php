<?php

test('bootstrap loads without errors', function () {
    require_once __DIR__ . '/../App/bootstrap.php';
    expect(true)->toBeTrue();
})->after(function () {
    restore_error_handler();
    restore_exception_handler();
});