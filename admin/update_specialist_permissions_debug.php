<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Log session data
error_log("Debug - Session data: " . print_r($_SESSION, true));
error_log("Debug - Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'not set'));
error_log("Debug - POST data: " . print_r($_POST, true));

require_once __DIR__ . '/../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    error_log("Debug - User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

error_log("Debug - User logged in: " . $_SESSION['user']);

// Allow workpoint_user or organisation_user roles
if (!in_array($_SESSION['role'] ?? '', ['workpoint_user', 'organisation_user'])) {
    error_log("Debug - Unauthorized role: " . ($_SESSION['role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized access - role: ' . ($_SESSION['role'] ?? 'not set')]);
    exit;
}

error_log("Debug - Role authorized: " . $_SESSION['role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $specialist_id = $_POST['specialist_id'] ?? null;
        $permission_field = $_POST['permission_field'] ?? null;
        $permission_value = $_POST['permission_value'] ?? null;
        
        error_log("Debug - Processing permission update: specialist_id=$specialist_id, field=$permission_field, value=$permission_value");
        
        // Validate inputs
        if (!$specialist_id || !$permission_field || !isset($permission_value)) {
            error_log("Debug - Missing required parameters");
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
            error_log("Debug - Invalid permission field: $permission_field");
            echo json_encode(['success' => false, 'message' => 'Invalid permission field']);
            exit;
        }
        
        // Validate permission value (should be 0 or 1)
        $permission_value = (int)$permission_value;
        if (!in_array($permission_value, [0, 1])) {
            error_log("Debug - Invalid permission value: $permission_value");
            echo json_encode(['success' => false, 'message' => 'Invalid permission value']);
            exit;
        }
        
        // Check if specialist exists
        $stmt = $pdo->prepare("SELECT unic_id FROM specialists WHERE unic_id = ?");
        $stmt->execute([$specialist_id]);
        
        if (!$stmt->fetch()) {
            error_log("Debug - Specialist not found: $specialist_id");
            echo json_encode(['success' => false, 'message' => 'Specialist not found']);
            exit;
        }
        
        error_log("Debug - Specialist found, updating permission");
        
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
            error_log("Debug - Updated existing record");
        } else {
            // Record doesn't exist, insert it with default values
            $stmt = $pdo->prepare("
                INSERT INTO specialists_setting_and_attr (specialist_id, $permission_field) 
                VALUES (?, ?)
            ");
            $stmt->execute([$specialist_id, $permission_value]);
            error_log("Debug - Inserted new record");
        }
        
        error_log("Debug - Permission update successful");
        echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
        
    } catch (PDOException $e) {
        error_log("Debug - Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    } catch (Exception $e) {
        error_log("Debug - General error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
} else {
    error_log("Debug - Invalid request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 