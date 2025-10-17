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
    // Get all services for this workpoint, grouped by specialist (excluding deleted services)
    $stmt = $pdo->prepare("
        SELECT 
            s.unic_id as service_id,
            s.name_of_service,
            s.duration,
            s.price_of_service,
            s.id_specialist,
            sp.name as specialist_name,
            sp.speciality as specialist_speciality,
            sp.email as specialist_email,
            sp.phone_nr as specialist_phone
        FROM services s
        INNER JOIN specialists sp ON s.id_specialist = sp.unic_id
        WHERE s.id_work_place = ? AND (s.deleted = 0 OR s.deleted IS NULL)
        ORDER BY sp.name, s.name_of_service
    ");
    $stmt->execute([$workpoint_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group services by specialist
    $grouped_services = [];
    foreach ($services as $service) {
        $specialist_id = $service['id_specialist'];
        if (!isset($grouped_services[$specialist_id])) {
            $grouped_services[$specialist_id] = [
                'specialist_id' => $specialist_id,
                'specialist_name' => $service['specialist_name'],
                'specialist_speciality' => $service['specialist_speciality'],
                'specialist_email' => $service['specialist_email'],
                'specialist_phone' => $service['specialist_phone'],
                'services' => []
            ];
        }
        $grouped_services[$specialist_id]['services'][] = [
            'service_id' => $service['service_id'],
            'name_of_service' => $service['name_of_service'],
            'duration' => $service['duration'],
            'price_of_service' => $service['price_of_service']
        ];
    }
    
    // Get all specialists working at this workpoint (for adding new services)
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
        'grouped_services' => array_values($grouped_services),
        'specialists' => $specialists,
        'workpoint_id' => $workpoint_id
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_services_for_workpoint: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 