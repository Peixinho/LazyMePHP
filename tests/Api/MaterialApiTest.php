<?php

test('material list API returns success', function () {
    $apiFile = __DIR__ . '/../../public/api/index.php';
    $_GET['entity'] = 'Material';
    $_GET['action'] = 'list';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/Material';

    ob_start();
    try {
        include $apiFile;
    } catch (\Core\Http\ApiExitException $e) {
        // Expected in test context
    } finally {
        $output = ob_get_clean();
    }
    $json = json_decode($output, true);

    expect($json)->toHaveKey('success');
    expect($json['success'])->toBeTrue();
}); 