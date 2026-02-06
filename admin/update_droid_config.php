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

// Get form data
$sms_active = $_POST['sms_active'] ?? '0';
$sms_phone_nr = $_POST['sms_phone_nr'] ?? '';
$sms_droid_link = $_POST['sms_droid_link'] ?? '';

$whatsapp_active = $_POST['whatsapp_active'] ?? '0';
$whatsapp_phone_nr = $_POST['whatsapp_phone_nr'] ?? '';
$whatsapp_droid_link = $_POST['whatsapp_droid_link'] ?? '';

// Auto-append required trigger paths if missing
if (!empty($sms_droid_link)) {
    $sms_droid_link = rtrim($sms_droid_link, '/');
    if (!preg_match('/\/sms_trigger$/', $sms_droid_link)) {
        $sms_droid_link .= '/sms_trigger';
    }
}

if (!empty($whatsapp_droid_link)) {
    $whatsapp_droid_link = rtrim($whatsapp_droid_link, '/');
    if (!preg_match('/\/whatsapp_trigger$/', $whatsapp_droid_link)) {
        $whatsapp_droid_link .= '/whatsapp_trigger';
    }
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

    // Get workpoint name
    $stmt = $pdo->prepare("SELECT name_of_the_place FROM working_points WHERE unic_id = ?");
    $stmt->execute([$wp_id]);
    $wp = $stmt->fetch();
    $workpoint_name = $wp ? $wp['name_of_the_place'] : '';

    // Update or insert SMS config
    $stmt = $pdo->prepare("SELECT idd FROM macro_droid_sms WHERE workpoint_id = ?");
    $stmt->execute([$wp_id]);
    $sms_exists = $stmt->fetch();

    if ($sms_exists) {
        $stmt = $pdo->prepare("
            UPDATE macro_droid_sms
            SET active = ?, sms_phone_nr = ?, droid_link = ?, workpoint_name = ?
            WHERE workpoint_id = ?
        ");
        $stmt->execute([$sms_active, $sms_phone_nr, $sms_droid_link, $workpoint_name, $wp_id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO macro_droid_sms (workpoint_id, workpoint_name, active, sms_phone_nr, droid_link)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$wp_id, $workpoint_name, $sms_active, $sms_phone_nr, $sms_droid_link]);
    }

    // Update or insert WhatsApp config
    $stmt = $pdo->prepare("SELECT idd FROM macro_droid_whatsapp WHERE workpoint_id = ?");
    $stmt->execute([$wp_id]);
    $whatsapp_exists = $stmt->fetch();

    if ($whatsapp_exists) {
        $stmt = $pdo->prepare("
            UPDATE macro_droid_whatsapp
            SET active = ?, whatsapp_phone_nr = ?, droid_link = ?, workpoint_name = ?
            WHERE workpoint_id = ?
        ");
        $stmt->execute([$whatsapp_active, $whatsapp_phone_nr, $whatsapp_droid_link, $workpoint_name, $wp_id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO macro_droid_whatsapp (workpoint_id, workpoint_name, active, whatsapp_phone_nr, droid_link)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$wp_id, $workpoint_name, $whatsapp_active, $whatsapp_phone_nr, $whatsapp_droid_link]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'MacroDroid configuration updated successfully'
    ]);

} catch (PDOException $e) {
    error_log("Database error in update_droid_config.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
}
?>
