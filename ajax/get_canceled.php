<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$mode = $_GET['mode'] ?? '';
$response = ['bookings' => []];

try {
    if ($mode === 'supervisor') {
        $workpoint_id = $_GET['workpoint_id'] ?? 0;
        
        // Get canceled bookings from booking_canceled table
        $stmt = $pdo->prepare("
            SELECT 
                bc.*, 
                s.name_of_service as service_name,
                sp.name as specialist_name,
                sp.speciality as specialist_speciality,
                sa.back_color as specialist_color,
                sa.foreground_color as specialist_fg_color,
                DATE_FORMAT(bc.booking_start_datetime, '%Y-%m-%d') as booking_date,
                TIMESTAMPDIFF(MINUTE, bc.cancellation_time, NOW()) / 60.0 as hours_since_cancellation,
                bc.made_by as canceled_by
            FROM booking_canceled bc
            LEFT JOIN services s ON bc.service_id = s.unic_id
            LEFT JOIN specialists sp ON bc.id_specialist = sp.unic_id
            LEFT JOIN specialists_setting_and_attr sa ON sp.unic_id = sa.specialist_id
            WHERE bc.id_work_place = ?
            AND bc.cancellation_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY bc.cancellation_time DESC
        ");
        $stmt->execute([$workpoint_id]);
        
    } else if ($mode === 'specialist') {
        $specialist_id = $_GET['specialist_id'] ?? 0;
        
        // Get canceled bookings from booking_canceled table for the specialist
        $stmt = $pdo->prepare("
            SELECT 
                bc.*, 
                s.name_of_service as service_name,
                DATE_FORMAT(bc.booking_start_datetime, '%Y-%m-%d') as booking_date,
                TIMESTAMPDIFF(MINUTE, bc.cancellation_time, NOW()) / 60.0 as hours_since_cancellation,
                bc.made_by as canceled_by
            FROM booking_canceled bc
            LEFT JOIN services s ON bc.service_id = s.unic_id
            WHERE bc.id_specialist = ?
            AND bc.cancellation_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY bc.cancellation_time DESC
        ");
        $stmt->execute([$specialist_id]);
    }
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process canceled bookings
    foreach ($bookings as &$booking) {
        // Add cancellation info
        $booking['booking_status_text'] = 'Canceled' . ($booking['canceled_by'] ? ' by ' . $booking['canceled_by'] : '');
        
        // Use cancellation time for display instead of creation time
        $booking['hours_since_creation'] = $booking['hours_since_cancellation'];
    }
    
    $response['bookings'] = $bookings;
    
} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = 'Database error';
}

header('Content-Type: application/json');
echo json_encode($response);