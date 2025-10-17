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

if (!isset($_GET['workpoint_id']) || empty($_GET['workpoint_id'])) {
    echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
    exit;
}

$workpoint_id = trim($_GET['workpoint_id']);

try {
    // Get communication settings for this workpoint
    $stmt = $pdo->prepare("
        SELECT * FROM workpoint_social_media 
        WHERE workpoint_id = ? 
        ORDER BY platform
    ");
    $stmt->execute([$workpoint_id]);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize settings by platform
    $organized_settings = [];
    foreach ($settings as $setting) {
        $platform = $setting['platform'];
        $organized_settings[$platform] = $setting;
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $organized_settings
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_communication_settings: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
