<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Log the request method and POST data
error_log("Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET'));
error_log("POST data: " . json_encode($_POST));
error_log("HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NOT SET'));

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    error_log("Permission update failed: User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Allow workpoint_user or organisation_user roles
if (!in_array($_SESSION['role'] ?? '', ['workpoint_user', 'organisation_user'])) {
    error_log("Permission update failed: Unauthorized access for role " . ($_SESSION['role'] ?? 'none'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// More flexible request method check
$is_post_request = false;
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_post_request = true;
} elseif (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    // If it's an AJAX request, treat it as POST
    $is_post_request = true;
} elseif (!empty($_POST)) {
    // If we have POST data, treat it as POST
    $is_post_request = true;
}

if ($is_post_request) {
    try {
        $specialist_id = $_POST['specialist_id'] ?? null;
        $permission_field = $_POST['permission_field'] ?? null;
        $permission_value = $_POST['permission_value'] ?? null;
        
        error_log("Processing permission update: specialist_id=$specialist_id, field=$permission_field, value=$permission_value");
        
        // Validate inputs
        if (!$specialist_id || !$permission_field || !isset($permission_value)) {
            error_log("Permission update failed: Missing required parameters");
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit;
        }
        
        // Validate permission field name
        $allowed_fields = [
            'specialist_can_delete_booking',
            'specialist_can_modify_booking',
            'specialist_can_add_services',
            'specialist_can_modify_services',
            'specialist_can_delete_services',
            'specialist_nr_visible_to_client',
            'specialist_email_visible_to_client'
        ];
        
        if (!in_array($permission_field, $allowed_fields)) {
            error_log("Permission update failed: Invalid permission field: $permission_field");
            echo json_encode(['success' => false, 'message' => 'Invalid permission field']);
            exit;
        }
        
        // Validate permission value (should be 0 or 1)
        $permission_value = (int)$permission_value;
        if (!in_array($permission_value, [0, 1])) {
            error_log("Permission update failed: Invalid permission value: $permission_value");
            echo json_encode(['success' => false, 'message' => 'Invalid permission value']);
            exit;
        }
        
        // Check if specialist exists
        $stmt = $pdo->prepare("SELECT unic_id FROM specialists WHERE unic_id = ?");
        $stmt->execute([$specialist_id]);
        
        if (!$stmt->fetch()) {
            error_log("Permission update failed: Specialist not found: $specialist_id");
            echo json_encode(['success' => false, 'message' => 'Specialist not found']);
            exit;
        }
        
        error_log("Specialist found, proceeding with permission update");
        
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
            $result = $stmt->execute([$permission_value, $specialist_id]);
            error_log("Updated existing record: " . ($result ? "success" : "failed"));
        } else {
            // Record doesn't exist, insert it with default values
            $stmt = $pdo->prepare("
                INSERT INTO specialists_setting_and_attr (specialist_id, $permission_field) 
                VALUES (?, ?)
            ");
            $result = $stmt->execute([$specialist_id, $permission_value]);
            error_log("Inserted new record: " . ($result ? "success" : "failed"));
        }
        
        // Verify the update
        $stmt = $pdo->prepare("SELECT $permission_field FROM specialists_setting_and_attr WHERE specialist_id = ?");
        $stmt->execute([$specialist_id]);
        $verify_result = $stmt->fetch();
        
        if ($verify_result) {
            $actual_value = $verify_result[$permission_field];
            error_log("Verification: $permission_field = $actual_value (expected: $permission_value)");
            
            if ($actual_value == $permission_value) {
                error_log("Permission update successful");
                echo json_encode([
                    'success' => true, 
                    'message' => 'Permission updated successfully',
                    'debug' => [
                        'specialist_id' => $specialist_id,
                        'field' => $permission_field,
                        'value' => $permission_value,
                        'verified' => true,
                        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'NOT SET',
                        'is_ajax' => isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                    ]
                ]);
            } else {
                error_log("Permission update verification failed: expected $permission_value, got $actual_value");
                echo json_encode(['success' => false, 'message' => 'Permission update verification failed']);
            }
        } else {
            error_log("Permission update verification failed: no record found after update");
            echo json_encode(['success' => false, 'message' => 'Permission update verification failed']);
        }
        
    } catch (PDOException $e) {
        error_log("Permission update database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    } catch (Exception $e) {
        error_log("Permission update general error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
} else {
    error_log("Permission update failed: Invalid request method. REQUEST_METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET'));
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method',
        'debug' => [
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'NOT SET',
            'http_x_requested_with' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NOT SET',
            'post_data' => !empty($_POST) ? 'PRESENT' : 'EMPTY'
        ]
    ]);
}
?> 