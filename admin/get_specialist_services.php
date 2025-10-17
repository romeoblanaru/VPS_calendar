<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$specialist_id = isset($_GET['specialist_id']) ? (int)$_GET['specialist_id'] : 0;

if (!$specialist_id) {
    echo json_encode(['success' => false, 'message' => 'No specialist ID provided']);
    exit;
}

try {
    // Debug logging
    error_log("get_specialist_services.php: Fetching services for specialist_id: " . $specialist_id);
    
    // Get services for this specific specialist with booking counts
    $stmt = $pdo->prepare("
        SELECT 
            s.unic_id, 
            s.name_of_service, 
            s.duration, 
            s.price_of_service, 
            s.procent_vat, 
            s.id_specialist, 
            s.deleted,
            s.suspended,
            COALESCE(SUM(CASE WHEN b.booking_start_datetime < NOW() AND b.booking_start_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) as past_bookings,
            COALESCE(SUM(CASE WHEN b.booking_start_datetime >= NOW() THEN 1 ELSE 0 END), 0) as active_bookings
        FROM services s
        LEFT JOIN booking b ON s.unic_id = b.service_id
        WHERE s.id_specialist = ? AND (s.deleted IS NULL OR s.deleted != 1)
        GROUP BY s.unic_id, s.name_of_service, s.duration, s.price_of_service, s.procent_vat, s.id_specialist, s.deleted, s.suspended
        ORDER BY s.name_of_service ASC
    ");
    $stmt->execute([$specialist_id]);
    
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("get_specialist_services.php: Found " . count($services) . " services for specialist_id: " . $specialist_id);
    
    // If no services found for this specialist, return empty array with success
    if (empty($services)) {
        echo json_encode([
            'success' => true,
            'services' => [],
            'message' => 'No services found for this specialist'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'services' => $services,
            'specialist_id' => $specialist_id
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>