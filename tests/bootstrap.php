<?php

// Set environment variables for Pest/PHPUnit detection
putenv('PEST=1');
putenv('PEST_RUNNING=1');
$_ENV['PEST'] = '1';
$_ENV['PEST_RUNNING'] = '1';
$_SERVER['PEST'] = '1';
$_SERVER['PEST_RUNNING'] = '1';
$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

// Include the main bootstrap
require_once __DIR__ . '/../vendor/autoload.php'; 