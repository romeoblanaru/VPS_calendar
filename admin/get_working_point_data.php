<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['organisation_user', 'admin_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Accept both POST and GET for backward compatibility
$workpoint_id = null;
if (isset($_POST['wp_id'])) {
    $workpoint_id = trim($_POST['wp_id']);
} elseif (isset($_GET['workpoint_id'])) {
    $workpoint_id = trim($_GET['workpoint_id']);
}
if (!$workpoint_id) {
    echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
    exit;
}

try {
    // Get workpoint data
    $stmt = $pdo->prepare("
        SELECT * FROM working_points 
        WHERE unic_id = ?
    ");
    $stmt->execute([$workpoint_id]);
    $workpoint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workpoint) {
        echo json_encode(['success' => false, 'message' => 'Workpoint not found']);
        exit;
    }
    
    // Check if user has permission to access this workpoint
    if ($_SESSION['role'] === 'organisation_user') {
        $stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
        $stmt->execute([$_SESSION['user']]);
        $org = $stmt->fetch();
        
        if (!$org || $workpoint['organisation_id'] != $org['unic_id']) {
            echo json_encode(['success' => false, 'message' => 'Access denied to this workpoint']);
            exit;
        }
    }
    
    echo json_encode([
        'success' => true,
        'workpoint' => $workpoint
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_working_point_data.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 