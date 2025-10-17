<?php
session_start();
require_once 'includes/nchan_publisher.php';

if (!isset($_SESSION['user'])) {
    die("Unauthorized");
}

header('Content-Type: text/html');

$data = json_decode(file_get_contents('php://input'), true);
$specialist_id = $data['specialist_id'] ?? 2;

echo "<h3>Publishing Test Event</h3>";

// Test event data
$testEvent = [
    'booking_id' => 999,
    'specialist_id' => $specialist_id,
    'working_point_id' => 1,
    'client_full_name' => 'Debug Test Client',
    'booking_start_datetime' => date('Y-m-d H:i:s'),
    'booking_end_datetime' => date('Y-m-d H:i:s', strtotime('+1 hour'))
];

echo "<pre>Event data:\n" . json_encode($testEvent, JSON_PRETTY_PRINT) . "</pre>";

// Publish
echo "<h4>Publishing to nchan...</h4>";

// Test direct channel publish
$channel = 'specialist_' . $specialist_id;
$url = 'https://my-bookings.co.uk/internal/publish/booking?channel=' . urlencode($channel);

echo "URL: $url<br>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'type' => 'test',
    'timestamp' => time(),
    'data' => $testEvent
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, fopen('php://output', 'w'));

echo "<h4>cURL Output:</h4><pre>";
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);
echo "</pre>";

echo "<h4>Result:</h4>";
echo "HTTP Code: $httpCode<br>";
if ($error) echo "Error: $error<br>";
echo "Response: <pre>" . htmlspecialchars($result) . "</pre>";

if ($httpCode == 202 || $httpCode == 201 || $httpCode == 200) {
    echo "<p class='success'>✅ Successfully published to nchan!</p>";
} else {
    echo "<p class='error'>❌ Failed to publish to nchan</p>";
}

// Also try using the publisher class
echo "<h4>Using NchanPublisher class:</h4>";
$published = publishBookingEvent('test', $testEvent);
echo $published ? "✅ Success" : "❌ Failed";