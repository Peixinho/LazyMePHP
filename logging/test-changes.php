<?php
/**
 * Simple test to see what get-changes.php returns
 */

// Simulate the request
$_GET['log_id'] = '1';
$_GET['debug'] = '1';

echo "Testing get-changes.php...\n";

// Capture the output
ob_start();
include 'get-changes.php';
$output = ob_get_clean();

echo "Raw output:\n";
echo "---START---\n";
echo $output;
echo "\n---END---\n";

echo "\nOutput length: " . strlen($output) . " characters\n";
echo "First 100 characters: " . substr($output, 0, 100) . "\n";

// Try to parse as JSON
$json = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Error: " . json_last_error_msg() . "\n";
} else {
    echo "JSON is valid!\n";
    echo "Response: " . print_r($json, true) . "\n";
}
?> 