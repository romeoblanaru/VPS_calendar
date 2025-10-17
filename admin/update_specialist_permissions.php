<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Allow workpoint_user or organisation_user roles
if (!in_array($_SESSION['role'] ?? '', ['workpoint_user', 'organisation_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $specialist_id = $_POST['specialist_id'] ?? null;
        $permission_field = $_POST['permission_field'] ?? null;
        $permission_value = $_POST['permission_value'] ?? null;
        
        // Validate inputs
        if (!$specialist_id || !$permission_field || !isset($permission_value)) {
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit;
        }
        
        // Validate permission field name
        $allowed_fields = [
            'specialist_can_delete_booking',
            'specialist_can_modify_booking', 
            'specialist_nr_visible_to_client',
            'specialist_email_visible_to_client'
        ];
        
        if (!in_array($permission_field, $allowed_fields)) {
            echo json_encode(['success' => false, 'message' => 'Invalid permission field']);
            exit;
        }
        
        // Validate permission value (should be 0 or 1)
        $permission_value = (int)$permission_value;
        if (!in_array($permission_value, [0, 1])) {
            echo json_encode(['success' => false, 'message' => 'Invalid permission value']);
            exit;
        }
        
        // Check if specialist exists
        $stmt = $pdo->prepare("SELECT unic_id FROM specialists WHERE unic_id = ?");
        $stmt->execute([$specialist_id]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Specialist not found']);
            exit;
        }
        
        // Update the permission setting
        // First check if record exists
        $stmt = $pdo->prepare("SELECT specialist_id FROM specialists_setting_and_attr WHERE specialist_id = ?");
        $stmt->execute([$specialist_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Record exists, update it
            $stmt = $pdo->prepare("
                UPDATE specialists_setting_and_attr 
                SET $permission_field = ? 
                WHERE specialist_id = ?
            ");
            $stmt->execute([$permission_value, $specialist_id]);
        } else {
            // Record doesn't exist, insert it with default values
            $stmt = $pdo->prepare("
                INSERT INTO specialists_setting_and_attr (specialist_id, $permission_field) 
                VALUES (?, ?)
            ");
            $stmt->execute([$specialist_id, $permission_value]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 