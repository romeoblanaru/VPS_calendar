<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin_user', 'organisation_user', 'workpoint_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_POST['specialist_id'])) {
    echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
    exit;
}

$specialist_id = trim($_POST['specialist_id']);

try {
    // Get specialist data
    $stmt = $pdo->prepare("
        SELECT * FROM specialists 
        WHERE unic_id = ?
    ");
    $stmt->execute([$specialist_id]);
    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$specialist) {
        echo json_encode(['success' => false, 'message' => 'Specialist not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'specialist' => $specialist
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_specialist_data: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 