<?php
// Force error display
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/srv/project_1/calendar/php_errors.log');

// Start output
header('Content-Type: text/plain');
echo "Error diagnosis page\n";
echo "====================\n\n";

// Test basic PHP
echo "1. PHP is working\n";
echo "   PHP Version: " . phpversion() . "\n\n";

// Test session start
echo "2. Testing session_start()...\n";
try {
    session_start();
    echo "   Session started successfully\n";
    echo "   Session ID: " . session_id() . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Test includes
echo "3. Testing includes...\n";
$includes = [
    'includes/lang_loader.php',
    'config/version.php',
    'includes/db.php'
];

foreach ($includes as $inc) {
    echo "   Testing $inc: ";
    if (file_exists(__DIR__ . '/' . $inc)) {
        try {
            require_once __DIR__ . '/' . $inc;
            echo "OK\n";
        } catch (Exception $e) {
            echo "ERROR - " . $e->getMessage() . "\n";
        }
    } else {
        echo "FILE NOT FOUND\n";
    }
}

echo "\n4. PHP Configuration:\n";
echo "   Memory limit: " . ini_get('memory_limit') . "\n";
echo "   Max execution time: " . ini_get('max_execution_time') . "\n";
echo "   Post max size: " . ini_get('post_max_size') . "\n";
echo "   Upload max filesize: " . ini_get('upload_max_filesize') . "\n";

echo "\nDone.\n";
?>