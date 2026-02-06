<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['organisation_user', 'admin_user', 'workpoint_supervisor', 'workpoint_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$wp_id = $_POST['wp_id'] ?? '';

if (!$wp_id) {
    echo json_encode(['success' => false, 'message' => 'Working point ID is required']);
    exit;
}

try {
    // Check if user has permission to access this workpoint
    if ($_SESSION['role'] === 'organisation_user') {
        $stmt = $pdo->prepare("SELECT organisation_id FROM working_points WHERE unic_id = ?");
        $stmt->execute([$wp_id]);
        $workpoint = $stmt->fetch();

        if (!$workpoint) {
            echo json_encode(['success' => false, 'message' => 'Workpoint not found']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
        $stmt->execute([$_SESSION['user']]);
        $org = $stmt->fetch();

        if (!$org || $workpoint['organisation_id'] != $org['unic_id']) {
            echo json_encode(['success' => false, 'message' => 'Access denied to this workpoint']);
            exit;
        }
    }

    // Get SMS config
    $stmt = $pdo->prepare("SELECT * FROM macro_droid_sms WHERE workpoint_id = ? LIMIT 1");
    $stmt->execute([$wp_id]);
    $sms_config = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get WhatsApp config
    $stmt = $pdo->prepare("SELECT * FROM macro_droid_whatsapp WHERE workpoint_id = ? LIMIT 1");
    $stmt->execute([$wp_id]);
    $whatsapp_config = $stmt->fetch(PDO::FETCH_ASSOC);

    // Default to INACTIVE (0) when no record found
    echo json_encode([
        'success' => true,
        'sms' => $sms_config ?: [
            'active' => 0,
            'sms_phone_nr' => '',
            'droid_link' => '',
            'workpoint_name' => ''
        ],
        'whatsapp' => $whatsapp_config ?: [
            'active' => 0,
            'whatsapp_phone_nr' => '',
            'droid_link' => '',
            'workpoint_name' => ''
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_droid_config.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
}
?>
