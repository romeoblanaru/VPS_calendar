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
        
        // Get recent bookings for the workpoint - only future bookings
        $stmt = $pdo->prepare("
            SELECT 
                b.*, 
                s.name_of_service as service_name,
                sp.name as specialist_name,
                sp.speciality as specialist_speciality,
                sa.back_color as specialist_color,
                sa.foreground_color as specialist_fg_color,
                DATE_FORMAT(b.booking_start_datetime, '%Y-%m-%d') as booking_date,
                CASE 
                    WHEN b.day_of_creation IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, b.day_of_creation, NOW()) / 60.0
                    ELSE TIMESTAMPDIFF(MINUTE, b.booking_start_datetime, NOW()) / 60.0
                END as hours_since_creation
            FROM booking b
            LEFT JOIN services s ON b.service_id = s.unic_id
            LEFT JOIN specialists sp ON b.id_specialist = sp.unic_id
            LEFT JOIN specialists_setting_and_attr sa ON sp.unic_id = sa.specialist_id
            WHERE b.id_work_place = ?
            AND b.booking_start_datetime >= NOW()
            ORDER BY COALESCE(b.day_of_creation, b.booking_start_datetime) DESC
        ");
        $stmt->execute([$workpoint_id]);
        
    } else if ($mode === 'specialist') {
        $specialist_id = $_GET['specialist_id'] ?? 0;
        
        // Get recent bookings for the specialist - only future bookings
        $stmt = $pdo->prepare("
            SELECT 
                b.*, 
                s.name_of_service as service_name,
                DATE_FORMAT(b.booking_start_datetime, '%Y-%m-%d') as booking_date,
                CASE 
                    WHEN b.day_of_creation IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, b.day_of_creation, NOW()) / 60.0
                    ELSE TIMESTAMPDIFF(MINUTE, b.booking_start_datetime, NOW()) / 60.0
                END as hours_since_creation
            FROM booking b
            LEFT JOIN services s ON b.service_id = s.unic_id
            WHERE b.id_specialist = ?
            AND b.booking_start_datetime >= NOW()
            ORDER BY COALESCE(b.day_of_creation, b.booking_start_datetime) DESC
        ");
        $stmt->execute([$specialist_id]);
    }
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter out canceled bookings if booking_status exists
    $filteredBookings = [];
    foreach ($bookings as $booking) {
        // Include bookings that don't have a status or are not canceled/deleted
        if (!isset($booking['booking_status']) || 
            $booking['booking_status'] === null || 
            $booking['booking_status'] === '' ||
            !in_array($booking['booking_status'], ['canceled', 'deleted'])) {
            
            // Categorize bookings by time since creation
            $hours = $booking['hours_since_creation'];
            
            if ($hours === null || $hours < 0) {
                // Future bookings or no creation date
                $booking['category'] = 'older';
            } else if ($hours <= 2) {
                $booking['category'] = 'hot';
            } else if ($hours <= 6) {
                $booking['category'] = 'mild';
            } else if ($hours <= 24) {
                $booking['category'] = 'recent';
            } else {
                $booking['category'] = 'older';
            }
            
            $filteredBookings[] = $booking;
        }
    }
    
    $response['bookings'] = $filteredBookings;
    
} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);