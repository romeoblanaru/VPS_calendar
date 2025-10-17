<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/session.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if (!$service_id) {
        echo json_encode(['success' => false, 'message' => 'Service ID is required']);
        exit;
    }
    
    // Check if service exists
    $stmt = $pdo->prepare("
        SELECT * FROM services WHERE unic_id = ?
    ");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit;
    }
    
    // Determine action based on current state and booking history
    if ($action === 'suspend') {
        // Suspend the service
        $stmt = $pdo->prepare("UPDATE services SET suspended = 1 WHERE unic_id = ?");
        $result = $stmt->execute([$service_id]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Service suspended successfully',
                'action' => 'suspended'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to suspend service']);
        }
    } elseif ($action === 'activate') {
        // Activate (unsuspend) the service
        $stmt = $pdo->prepare("UPDATE services SET suspended = NULL WHERE unic_id = ?");
        $result = $stmt->execute([$service_id]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Service activated successfully',
                'action' => 'activated'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to activate service']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Error in process_suspend_service.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>