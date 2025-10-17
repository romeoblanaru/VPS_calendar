<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to output, but log them

session_start();

// Check if db.php exists
if (!file_exists('../includes/db.php')) {
    echo json_encode(['error' => 'Database connection file not found']);
    exit;
}

require_once '../includes/db.php';

// Check if PDO connection exists
if (!isset($pdo)) {
    echo json_encode(['error' => 'Database connection not established']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['service_id'])) {
    echo json_encode(['error' => 'Service ID required']);
    exit;
}

$service_id = $_GET['service_id'];

try {
    // Check for future bookings
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as future_count 
        FROM booking 
        WHERE service_id = ? 
        AND booking_start_datetime > NOW()
    ");
    $stmt->execute([$service_id]);
    $future_result = $stmt->fetch();
    $hasFutureBookings = $future_result['future_count'] > 0;
    
    // Check for past bookings
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as past_count 
        FROM booking 
        WHERE service_id = ?
    ");
    $stmt->execute([$service_id]);
    $past_result = $stmt->fetch();
    $hasPastBookings = $past_result['past_count'] > 0;
    
    // Check if service is suspended
    $stmt = $pdo->prepare("
        SELECT suspended 
        FROM services 
        WHERE unic_id = ?
    ");
    $stmt->execute([$service_id]);
    $service_result = $stmt->fetch();
    $isSuspended = ($service_result && $service_result['suspended'] == 1);
    
    echo json_encode([
        'success' => true,
        'hasFutureBookings' => $hasFutureBookings,
        'hasPastBookings' => $hasPastBookings,
        'futureCount' => $future_result['future_count'],
        'totalCount' => $past_result['past_count'],
        'isSuspended' => $isSuspended
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>