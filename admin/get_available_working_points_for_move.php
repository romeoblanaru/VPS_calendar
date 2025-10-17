<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin_user') {
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$required_fields = ['organisation_id', 'specialist_id'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

$organisation_id = trim($_POST['organisation_id']);
$specialist_id = trim($_POST['specialist_id']);

try {
    // Get all working points in the organisation
    $stmt = $pdo->prepare("
        SELECT 
            wp.unic_id, 
            wp.name_of_the_place, 
            wp.address,
            CASE WHEN wp2.specialist_id IS NOT NULL THEN 1 ELSE 0 END as is_current_assignment
        FROM working_points wp 
        LEFT JOIN working_program wp2 ON wp.unic_id = wp2.working_place_id AND wp2.specialist_id = ?
        WHERE wp.organisation_id = ?
        ORDER BY wp.name_of_the_place
    ");
    $stmt->execute([$specialist_id, $organisation_id]);
    $working_points = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'working_points' => $working_points
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_available_working_points_for_move.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 