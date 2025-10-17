<?php
// Direct nchan publish test
require_once 'includes/db.php';

echo "Testing direct nchan publish...\n\n";

// Test data
$event = [
    'type' => 'test',
    'timestamp' => time(),
    'data' => [
        'booking_id' => 'DIRECT-TEST',
        'message' => 'Direct nchan test'
    ]
];

$channels = [
    'specialist_1',
    'workpoint_1', 
    'admin_all'
];

foreach ($channels as $channel) {
    echo "Publishing to channel: $channel\n";
    
    // Try HTTPS directly
    $url = "https://my-bookings.co.uk/internal/publish/booking?channel=" . urlencode($channel);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "  HTTP Code: $httpCode\n";
    if ($error) echo "  Error: $error\n";
    if ($httpCode == 403) {
        echo "  Access denied - publisher endpoint is IP restricted\n";
    }
    echo "\n";
}

echo "Done.\n";