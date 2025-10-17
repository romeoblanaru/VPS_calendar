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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$workpoint_id = $_POST['workpoint_id'] ?? '';
$password = $_POST['password'] ?? '';

if (!$workpoint_id) {
    echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
    exit;
}

if (!$password) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Verify password
try {
    $stmt = $pdo->prepare("SELECT pasword FROM super_users WHERE unic_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    if ($password !== $user['pasword']) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error verifying password: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during password verification']);
    exit;
}

try {
    // Check if workpoint exists and user has permission
    $stmt = $pdo->prepare("SELECT organisation_id FROM working_points WHERE unic_id = ?");
    $stmt->execute([$workpoint_id]);
    $workpoint = $stmt->fetch();
    
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
    
    $pdo->beginTransaction();
    
    // Delete related records first
    // Delete working programs for this workpoint
    $stmt = $pdo->prepare("DELETE FROM working_program WHERE working_place_id = ?");
    $stmt->execute([$workpoint_id]);
    
    // Delete bookings for this workpoint
    $stmt = $pdo->prepare("DELETE FROM booking WHERE id_work_place = ?");
    $stmt->execute([$workpoint_id]);
    
    // Delete services for this workpoint
    $stmt = $pdo->prepare("DELETE FROM services WHERE id_work_place = ?");
    $stmt->execute([$workpoint_id]);
    
    // Finally delete the workpoint
    $stmt = $pdo->prepare("DELETE FROM working_points WHERE unic_id = ?");
    $result = $stmt->execute([$workpoint_id]);
    
    if ($result) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Workpoint deleted successfully'
        ]);
    } else {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete workpoint']);
    }
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("Database error in delete_workpoint.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>