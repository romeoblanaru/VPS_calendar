<?php
session_start();

// Include database connection
if (!file_exists('../includes/db.php')) {
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit;
}

require_once '../includes/db.php';

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_POST['service_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$service_id = $_POST['service_id'];
$action = $_POST['action'];

try {
    if ($action === 'soft_delete_service') {
        // Soft delete - mark as deleted
        $stmt = $pdo->prepare("UPDATE services SET deleted = 1 WHERE unic_id = ?");
        $stmt->execute([$service_id]);
        
        $message = "Service marked as deleted (has past bookings)";
    } else if ($action === 'hard_delete_service') {
        // Hard delete - remove from database
        $stmt = $pdo->prepare("DELETE FROM services WHERE unic_id = ?");
        $stmt->execute([$service_id]);
        
        $message = "Service permanently deleted";
    } else {
        throw new Exception("Invalid action");
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>