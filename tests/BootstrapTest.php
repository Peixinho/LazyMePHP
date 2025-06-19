<?php

test('bootstrap loads without errors', function () {
    require_once __DIR__ . '/../App/bootstrap.php';
    expect(true)->toBeTrue();
}); 