<?php
// Test nchan publishing

$urls = [
    'http://127.0.0.1/internal/publish/booking?channel=test',
    'http://localhost/internal/publish/booking?channel=test', 
    'https://my-bookings.co.uk/internal/publish/booking?channel=test'
];

$data = json_encode(['test' => 'message', 'time' => time()]);

foreach ($urls as $url) {
    echo "\nTesting: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Host: my-bookings.co.uk'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stdout', 'w'));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    if ($error) {
        echo "Error: $error\n";
    }
    if ($result) {
        echo "Response: " . substr($result, 0, 200) . "\n";
    }
    echo "---\n";
}