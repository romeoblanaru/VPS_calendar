<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if workpoint_id is provided
if (!isset($_GET['workpoint_id'])) {
    echo json_encode(['success' => false, 'error' => 'Workpoint ID is required']);
    exit;
}

$workpoint_id = $_GET['workpoint_id'];

try {
    // Get workpoint details
    $stmt = $pdo->prepare("SELECT * FROM working_points WHERE unic_id = ?");
    $stmt->execute([$workpoint_id]);
    $workpoint = $stmt->fetch();
    
    if (!$workpoint) {
        echo json_encode(['success' => false, 'error' => 'Workpoint not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'workpoint' => $workpoint]);
    
} catch (Exception $e) {
    error_log("Error getting workpoint details: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 