<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/session.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$workpoint_id = (int)($_GET['workpoint_id'] ?? 0);

if (!$workpoint_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid workpoint ID']);
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

// Get all SMS templates for this workpoint
$stmt = $pdo->prepare("
    SELECT setting_key, setting_value, excluded_channels 
    FROM workingpoint_settings_and_attr 
    WHERE working_point_id = ? 
    AND setting_key IN ('sms_cancellation_template', 'sms_creation_template', 'sms_update_template')
");
$stmt->execute([$workpoint_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$templates = [
    'cancellation_template' => 'Your Booking ID:{booking_id} at {organisation_alias} - {workpoint_name} ({workpoint_address}) for {service_name} at {start_time} - {booking_date} was canceled. Call {workpoint_phone} if needed.',
    'creation_template' => 'Booking confirmed! ID:{booking_id} at {organisation_alias} - {workpoint_name} for {service_name} on {booking_date} at {start_time}. Location: {workpoint_address}',
    'update_template' => 'Booking ID:{booking_id} updated. New time: {booking_date} at {start_time} for {service_name} at {workpoint_name}. Call {workpoint_phone} if needed.',
    'excluded_channels' => 'SMS'
];

// Process results
foreach ($results as $row) {
    if ($row['setting_key'] === 'sms_cancellation_template') {
        $templates['cancellation_template'] = $row['setting_value'];
        $templates['excluded_channels'] = $row['excluded_channels'] ?? 'SMS';
    } elseif ($row['setting_key'] === 'sms_creation_template') {
        $templates['creation_template'] = $row['setting_value'];
    } elseif ($row['setting_key'] === 'sms_update_template') {
        $templates['update_template'] = $row['setting_value'];
    }
}

echo json_encode([
    'success' => true,
    'cancellation_template' => $templates['cancellation_template'],
    'creation_template' => $templates['creation_template'],
    'update_template' => $templates['update_template'],
    'excluded_channels' => $templates['excluded_channels']
]);