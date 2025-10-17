<?php
// Session should already be started by parent, but start if not
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only include if not already included
if (!isset($_SESSION)) {
    include '../includes/session.php';
}

if (!isset($pdo)) {
    include __DIR__ . '/../includes/db.php';
}

header('Content-Type: application/json');

try {
    $status = [];
    
    // Check if worker script exists
    $worker_script = __DIR__ . '/../process_google_calendar_queue_enhanced.php';
    $status['worker_exists'] = file_exists($worker_script);
    
    // Check if queue table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'google_calendar_sync_queue'");
    $status['queue_table_exists'] = $stmt->rowCount() > 0;
    
    // Check if credentials table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'google_calendar_credentials'");
    $status['credentials_table_exists'] = $stmt->rowCount() > 0;
    
    // Check if google oauth config exists
    $oauth_config_files = glob(__DIR__ . '/../config/client_secret_*.json');
    $custom_config = __DIR__ . '/../config/google_oauth.json';
    $status['oauth_config_exists'] = !empty($oauth_config_files) || file_exists($custom_config);
    
    // Check if includes files exist
    $status['google_calendar_sync_exists'] = file_exists(__DIR__ . '/../includes/google_calendar_sync.php');
    $status['oauth_config_class_exists'] = file_exists(__DIR__ . '/google_oauth_config.php');
    
    echo json_encode($status);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 