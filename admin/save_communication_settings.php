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

$workpoint_id = trim($_POST['workpoint_id']);

// Log received data for debugging
error_log("Received communication settings save request for workpoint_id: " . $workpoint_id);
error_log("POST data: " . print_r($_POST, true));

try {
    $pdo->beginTransaction();
    
    // Process WhatsApp Business settings
    if (isset($_POST['whatsapp_active'])) {
        $whatsapp_active = 1;
        
        // Check if WhatsApp settings already exist
        $stmt = $pdo->prepare("SELECT id FROM workpoint_social_media WHERE workpoint_id = ? AND platform = 'whatsapp_business'");
        $stmt->execute([$workpoint_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing WhatsApp settings
            $update_sql = "
                UPDATE workpoint_social_media SET
                    is_active = ?,
                    whatsapp_phone_number = ?,
                    whatsapp_phone_number_id = ?,
                    whatsapp_business_account_id = ?,
                    whatsapp_access_token = ?,
                    updated_at = NOW()
                WHERE workpoint_id = ? AND platform = 'whatsapp_business'
            ";
            $update_params = [
                $whatsapp_active,
                $_POST['whatsapp_phone_number'] ?? '',
                $_POST['whatsapp_phone_number_id'] ?? '',
                $_POST['whatsapp_business_account_id'] ?? '',
                $_POST['whatsapp_access_token'] ?? '',
                $workpoint_id
            ];
            
            error_log("WhatsApp UPDATE SQL: " . $update_sql);
            error_log("WhatsApp UPDATE params: " . print_r($update_params, true));
            
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute($update_params);
            
            error_log("WhatsApp settings UPDATED for workpoint_id: " . $workpoint_id . ", active: " . $whatsapp_active);
        } else {
            // Insert new WhatsApp settings
            $insert_sql = "
                INSERT INTO workpoint_social_media (
                    workpoint_id, platform, is_active,
                    whatsapp_phone_number, whatsapp_phone_number_id, whatsapp_business_account_id, whatsapp_access_token,
                    created_at, updated_at
                ) VALUES (?, 'whatsapp_business', ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            $insert_params = [
                $workpoint_id,
                $whatsapp_active,
                $_POST['whatsapp_phone_number'] ?? '',
                $_POST['whatsapp_phone_number_id'] ?? '',
                $_POST['whatsapp_business_account_id'] ?? '',
                $_POST['whatsapp_access_token'] ?? ''
            ];
            
            error_log("WhatsApp INSERT SQL: " . $insert_sql);
            error_log("WhatsApp INSERT params: " . print_r($insert_params, true));
            
            $stmt = $pdo->prepare($insert_sql);
            $stmt->execute($insert_params);
            
            error_log("WhatsApp settings INSERTED for workpoint_id: " . $workpoint_id . ", active: " . $whatsapp_active);
        }
    } else {
        // Disable WhatsApp
        $stmt = $pdo->prepare("UPDATE workpoint_social_media SET is_active = 0 WHERE workpoint_id = ? AND platform = 'whatsapp_business'");
        $stmt->execute([$workpoint_id]);
        
        error_log("WhatsApp settings DISABLED for workpoint_id: " . $workpoint_id);
    }
    
    // Process Facebook Messenger settings
    if (isset($_POST['facebook_active'])) {
        $facebook_active = 1;
        
        // Check if Facebook settings already exist
        $stmt = $pdo->prepare("SELECT id FROM workpoint_social_media WHERE workpoint_id = ? AND platform = 'facebook_messenger'");
        $stmt->execute([$workpoint_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing Facebook settings
            $stmt = $pdo->prepare("
                UPDATE workpoint_social_media SET
                    is_active = ?,
                    facebook_page_id = ?,
                    facebook_page_access_token = ?,
                    facebook_app_id = ?,
                    facebook_app_secret = ?,
                    updated_at = NOW()
                WHERE workpoint_id = ? AND platform = 'facebook_messenger'
            ");
            $stmt->execute([
                $facebook_active,
                $_POST['facebook_page_id'] ?? '',
                $_POST['facebook_page_access_token'] ?? '',
                $_POST['facebook_app_id'] ?? '',
                $_POST['facebook_app_secret'] ?? '',
                $workpoint_id
            ]);
        } else {
            // Insert new Facebook settings
            $stmt = $pdo->prepare("
                INSERT INTO workpoint_social_media (
                    workpoint_id, platform, is_active,
                    facebook_page_id, facebook_page_access_token, facebook_app_id,
                    facebook_app_secret, created_at, updated_at
                ) VALUES (?, 'facebook_messenger', ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $workpoint_id,
                $facebook_active,
                $_POST['facebook_page_id'] ?? '',
                $_POST['facebook_page_access_token'] ?? '',
                $_POST['facebook_app_id'] ?? '',
                $_POST['facebook_app_secret'] ?? ''
            ]);
        }
    } else {
        // Disable Facebook
        $stmt = $pdo->prepare("UPDATE workpoint_social_media SET is_active = 0 WHERE workpoint_id = ? AND platform = 'facebook_messenger'");
        $stmt->execute([$workpoint_id]);
    }
    
    $pdo->commit();
    
    // Log successful save for debugging
    error_log("Communication settings saved successfully for workpoint_id: " . $workpoint_id);
    
    echo json_encode(['success' => true, 'message' => 'Communication settings saved successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error in save_communication_settings: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
