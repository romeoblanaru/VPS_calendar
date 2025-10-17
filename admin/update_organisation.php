<?php
// Start output buffering to catch any unwanted output
ob_start();

// Disable error reporting to prevent warnings from corrupting JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../includes/db.php';
require_once '../includes/logger.php';

// Set proper headers for JSON response
header('Content-Type: application/json');

// Debug: Log all POST data
error_log("Update organisation - POST data: " . print_r($_POST, true));

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin_user') {
    error_log("Access denied - user_id: " . ($_SESSION['user_id'] ?? 'not set') . ", role: " . ($_SESSION['role'] ?? 'not set'));
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate required fields
$required_fields = ['org_id', 'alias_name', 'oficial_company_name'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

$org_id = trim($_POST['org_id']);
error_log("Updating organisation ID: " . $org_id);

try {
    // Check if organization exists
    $stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE unic_id = ?");
    $stmt->execute([$org_id]);
    if (!$stmt->fetch()) {
        error_log("Organisation not found: " . $org_id);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Organization not found']);
        exit;
    }
    
    // Prepare update data
    $update_data = [
        'alias_name' => trim($_POST['alias_name']),
        'oficial_company_name' => trim($_POST['oficial_company_name']),
        // 'booking_phone_nr' => trim($_POST['booking_phone_nr'] ?? ''), // Now managed per working point
        'contact_name' => trim($_POST['contact_name'] ?? ''),
        'position' => trim($_POST['position'] ?? ''),
        'email_address' => trim($_POST['email_address'] ?? ''),
        'www_address' => trim($_POST['www_address'] ?? ''),
        'company_head_office_address' => trim($_POST['company_head_office_address'] ?? ''),
        'company_phone_nr' => trim($_POST['company_phone_nr'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'owner_name' => trim($_POST['owner_name'] ?? ''),
        'owner_phone_nr' => trim($_POST['owner_phone_nr'] ?? ''),
        'user' => trim($_POST['user'] ?? ''),
        'pasword' => trim($_POST['pasword'] ?? '')
    ];
    
    error_log("Update data: " . print_r($update_data, true));
    
    // Build SQL query dynamically
    $sql_parts = [];
    $params = [];
    
    foreach ($update_data as $field => $value) {
        $sql_parts[] = "$field = ?";
        $params[] = $value;
    }
    
    $params[] = $org_id; // For WHERE clause
    
    $sql = "UPDATE organisations SET " . implode(', ', $sql_parts) . " WHERE unic_id = ?";
    error_log("SQL query: " . $sql);
    error_log("SQL params: " . print_r($params, true));
    
    // Execute update
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    error_log("Update result: " . ($result ? 'success' : 'failed'));
    error_log("Rows affected: " . $stmt->rowCount());
    
    if ($result && $stmt->rowCount() > 0) {
        // Log the action
        // logAction($_SESSION['user_id'], 'UPDATE_ORGANISATION', $log_message, $org_id);
        $log_message = "Admin updated organisation: $org_id - " . $update_data['alias_name'];
        error_log($log_message);
        
        $response = [
            'success' => true,
            'message' => 'Organization updated successfully',
            'rows_affected' => $stmt->rowCount()
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'No changes were made to the organization'
        ];
    }
    
    // Get any buffered output and log it
    $buffered_output = ob_get_contents();
    if (!empty($buffered_output)) {
        error_log("Buffered output before JSON: " . $buffered_output);
    }
    
    // Clear any output buffer and send clean JSON response
    ob_clean();
    $json_response = json_encode($response);
    error_log("JSON response being sent: " . $json_response);
    echo $json_response;
    
} catch (PDOException $e) {
    error_log("Database error in update_organisation.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error occurred while updating organization: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in update_organisation.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error occurred: ' . $e->getMessage()]);
}
?> 