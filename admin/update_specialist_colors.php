<?php
require_once '../includes/db.php';
require_once '../includes/specialist_settings.php';

header('Content-Type: application/json');

try {
    $specialist_id = $_POST['specialist_id'] ?? null;
    $back_color = $_POST['back_color'] ?? null;
    $foreground_color = $_POST['foreground_color'] ?? null;
    
    if (!$specialist_id || !$back_color || !$foreground_color) {
        throw new Exception('Missing required parameters');
    }
    
    // Validate hex colors
    if (!preg_match('/^#[0-9A-F]{6}$/i', $back_color) || !preg_match('/^#[0-9A-F]{6}$/i', $foreground_color)) {
        throw new Exception('Invalid color format');
    }
    
    // Update the colors
    $success = updateSpecialistSettings($specialist_id, [
        'back_color' => $back_color,
        'foreground_color' => $foreground_color
    ]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Colors updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update colors');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 