<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/session.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$workpoint_id = (int)($_POST['workpoint_id'] ?? 0);
$cancellation_template = $_POST['cancellation_template'] ?? '';
$creation_template = $_POST['creation_template'] ?? '';
$update_template = $_POST['update_template'] ?? '';
$excluded_channels = $_POST['excluded_channels'] ?? 'SMS';

if (!$workpoint_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid workpoint ID']);
    exit;
}

if (!$cancellation_template || !$creation_template || !$update_template) {
    echo json_encode(['success' => false, 'message' => 'All templates must be provided']);
    exit;
}

// Check user permissions
$user_role = $_SESSION['role'] ?? '';

if ($user_role === 'organisation_user') {
    // Organisation users can only access their own workpoints
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM working_points wp
        JOIN organisations o ON wp.organisation_id = o.unic_id
        WHERE wp.unic_id = ? AND o.user = ?
    ");
    $stmt->execute([$workpoint_id, $_SESSION['user']]);
    
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
} elseif ($user_role === 'workpoint_user') {
    // Workpoint supervisors can only access their assigned workpoint
    if ($workpoint_id != ($_SESSION['workpoint_id'] ?? 0)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
} elseif ($user_role !== 'admin_user') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Update cancellation template with excluded channels
    $stmt = $pdo->prepare("
        INSERT INTO workingpoint_settings_and_attr 
        (working_point_id, setting_key, setting_value, excluded_channels, description) 
        VALUES (?, 'sms_cancellation_template', ?, ?, 'SMS template for booking cancellation')
        ON DUPLICATE KEY UPDATE 
        setting_value = VALUES(setting_value),
        excluded_channels = VALUES(excluded_channels),
        updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$workpoint_id, $cancellation_template, $excluded_channels]);
    
    // Update creation template
    $stmt = $pdo->prepare("
        INSERT INTO workingpoint_settings_and_attr 
        (working_point_id, setting_key, setting_value, excluded_channels, description) 
        VALUES (?, 'sms_creation_template', ?, ?, 'SMS template for booking creation')
        ON DUPLICATE KEY UPDATE 
        setting_value = VALUES(setting_value),
        excluded_channels = VALUES(excluded_channels),
        updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$workpoint_id, $creation_template, $excluded_channels]);
    
    // Update update template
    $stmt = $pdo->prepare("
        INSERT INTO workingpoint_settings_and_attr 
        (working_point_id, setting_key, setting_value, excluded_channels, description) 
        VALUES (?, 'sms_update_template', ?, ?, 'SMS template for booking update')
        ON DUPLICATE KEY UPDATE 
        setting_value = VALUES(setting_value),
        excluded_channels = VALUES(excluded_channels),
        updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$workpoint_id, $update_template, $excluded_channels]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'SMS templates saved successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error saving SMS templates: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save templates'
    ]);
}