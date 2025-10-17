<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['workpoint_user', 'organisation_user', 'admin_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_GET['workpoint_id']) || empty($_GET['workpoint_id'])) {
    echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
    exit;
}

$workpoint_id = trim($_GET['workpoint_id']);

try {
    // Get all services for this workpoint (including unassigned ones)
    $stmt = $pdo->prepare("
        SELECT 
            s.unic_id as service_id,
            s.name_of_service,
            s.duration,
            s.price_of_service,
            s.procent_vat,
            s.id_specialist,
            s.deleted,
            sp.name as specialist_name,
            sp.speciality as specialist_speciality,
            (SELECT COUNT(*) FROM booking WHERE service_id = s.unic_id AND booking_start_datetime < NOW()) as past_booking_count,
            (SELECT COUNT(*) FROM booking WHERE service_id = s.unic_id AND booking_start_datetime >= NOW()) as future_booking_count
        FROM services s
        LEFT JOIN specialists sp ON s.id_specialist = sp.unic_id
        WHERE s.id_work_place = ?
        ORDER BY s.name_of_service
    ");
    $stmt->execute([$workpoint_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all specialists working at this workpoint (for assignment dropdown)
    $stmt = $pdo->prepare("
        SELECT DISTINCT sp.unic_id, sp.name, sp.speciality, sp.email, sp.phone_nr
        FROM specialists sp
        INNER JOIN working_program wp ON sp.unic_id = wp.specialist_id
        WHERE wp.working_place_id = ?
        ORDER BY sp.name
    ");
    $stmt->execute([$workpoint_id]);
    $specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'services' => $services,
        'specialists' => $specialists,
        'workpoint_id' => $workpoint_id
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_all_services_for_workpoint: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 