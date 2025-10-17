<?php
require_once '../includes/db.php';
require_once '../includes/specialist_settings.php';

header('Content-Type: application/json');

try {
    $specialist_id = $_POST['specialist_id'] ?? null;
    $daily_email_enabled = $_POST['daily_email_enabled'] ?? null;
    
    if (!$specialist_id || $daily_email_enabled === null) {
        throw new Exception('Missing required parameters');
    }
    
    // Validate boolean value
    $daily_email_enabled = (int)$daily_email_enabled;
    if (!in_array($daily_email_enabled, [0, 1])) {
        throw new Exception('Invalid daily_email_enabled value');
    }
    
    // Update the settings
    $success = updateSpecialistSettings($specialist_id, [
        'daily_email_enabled' => $daily_email_enabled
    ]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update settings');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 