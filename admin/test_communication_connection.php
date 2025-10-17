<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['workpoint_user', 'organisation_user', 'admin_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_POST['workpoint_id']) || empty($_POST['workpoint_id'])) {
    echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
    exit;
}

if (!isset($_POST['test_platform']) || empty($_POST['test_platform'])) {
    echo json_encode(['success' => false, 'message' => 'Platform to test is required']);
    exit;
}

$workpoint_id = trim($_POST['workpoint_id']);
$platform = trim($_POST['test_platform']);

try {
    // Get the platform settings
    $stmt = $pdo->prepare("SELECT * FROM workpoint_social_media WHERE workpoint_id = ? AND platform = ?");
    $stmt->execute([$workpoint_id, $platform]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        echo json_encode(['success' => false, 'message' => 'Platform settings not found']);
        exit;
    }
    
    $test_result = '';
    $test_status = 'failed';
    
    if ($platform === 'whatsapp_business') {
        // Test WhatsApp Business API connection
        $test_result = testWhatsAppConnection($settings);
    } elseif ($platform === 'facebook_messenger') {
        // Test Facebook Messenger API connection
        $test_result = testFacebookConnection($settings);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid platform']);
        exit;
    }
    
    // Update test results in database
    $stmt = $pdo->prepare("
        UPDATE workpoint_social_media SET
            last_test_at = NOW(),
            last_test_status = ?,
            last_test_message = ?
        WHERE workpoint_id = ? AND platform = ?
    ");
    $stmt->execute([$test_status, $test_result, $workpoint_id, $platform]);
    
    if ($test_status === 'success') {
        echo json_encode(['success' => true, 'message' => 'Connection test successful: ' . $test_result]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Connection test failed: ' . $test_result]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in test_communication_connection: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function testWhatsAppConnection($settings) {
    // Basic validation of WhatsApp settings
    if (empty($settings['whatsapp_access_token'])) {
        return 'Access token is required';
    }
    
    if (empty($settings['whatsapp_business_account_id'])) {
        return 'Business Account ID is required';
    }
    
    // Test WhatsApp Business API endpoint (basic validation)
    $url = 'https://graph.facebook.com/v17.0/' . $settings['whatsapp_business_account_id'] . '/phone_numbers';
    $headers = [
        'Authorization: Bearer ' . $settings['whatsapp_access_token'],
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $GLOBALS['test_status'] = 'success';
        return 'WhatsApp Business API connection successful';
    } else {
        $GLOBALS['test_status'] = 'failed';
        return 'WhatsApp Business API connection failed (HTTP ' . $http_code . ')';
    }
}

function testFacebookConnection($settings) {
    // Basic validation of Facebook settings
    if (empty($settings['facebook_page_access_token'])) {
        return 'Page Access Token is required';
    }
    
    if (empty($settings['facebook_page_id'])) {
        return 'Page ID is required';
    }
    
    // Test Facebook Graph API endpoint (basic validation)
    $url = 'https://graph.facebook.com/v17.0/' . $settings['facebook_page_id'] . '?fields=id,name,access_token&access_token=' . $settings['facebook_page_access_token'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $GLOBALS['test_status'] = 'success';
        return 'Facebook Messenger API connection successful';
    } else {
        $GLOBALS['test_status'] = 'failed';
        return 'Facebook Messenger API connection failed (HTTP ' . $http_code . ')';
    }
}
?>
