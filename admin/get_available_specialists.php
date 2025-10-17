<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin_user', 'organisation_user', 'workpoint_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_GET['organisation_id']) || !isset($_GET['workpoint_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organisation ID and Workpoint ID are required']);
    exit;
}

$organisation_id = trim($_GET['organisation_id']);
$workpoint_id = trim($_GET['workpoint_id']);

try {
    // Get specialists that belong to this organisation and have NO active (non-zero) schedule at this workpoint
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.* 
        FROM specialists s
        WHERE s.organisation_id = ?
          AND NOT EXISTS (
            SELECT 1 FROM working_program wp
            WHERE wp.working_place_id = ?
              AND wp.specialist_id = s.unic_id
              AND (
                (wp.shift1_start <> '00:00:00' AND wp.shift1_end <> '00:00:00') OR
                (wp.shift2_start <> '00:00:00' AND wp.shift2_end <> '00:00:00') OR
                (wp.shift3_start <> '00:00:00' AND wp.shift3_end <> '00:00:00')
              )
          )
        ORDER BY s.name
    ");
    $stmt->execute([$organisation_id, $workpoint_id]);
    $specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'specialists' => $specialists
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_available_specialists: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 