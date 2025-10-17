<?php
/**
 * Lightweight endpoint for polling fallback
 * 
 * Returns the current booking version from Redis.
 * This is a minimal endpoint that only checks version numbers,
 * avoiding expensive database queries.
 */

session_start();

// Verify authentication
if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include Redis configuration
require_once '../includes/redis_config.php';

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Get parameters
$specialist_id = isset($_GET['specialist_id']) ? intval($_GET['specialist_id']) : null;
$workpoint_id = isset($_GET['workpoint_id']) ? intval($_GET['workpoint_id']) : null;
$supervisor_mode = isset($_GET['supervisor_mode']) && $_GET['supervisor_mode'] === 'true';

// Initialize response
$response = [
    'version' => 0,
    'specialist_version' => 0,
    'workpoint_version' => 0,
    'timestamp' => time(),
    'redis_connected' => false
];

// Get Redis instance
$redis = RedisManager::getInstance();

if ($redis->isConnected()) {
    $response['redis_connected'] = true;
    
    // Get global version
    $response['version'] = $redis->getVersion();
    
    // Get specific versions based on mode
    if (!$supervisor_mode && $specialist_id) {
        $response['specialist_version'] = $redis->getVersion('specialist:' . $specialist_id);
    }
    
    if ($supervisor_mode && $workpoint_id) {
        $response['workpoint_version'] = $redis->getVersion('workpoint:' . $workpoint_id);
    }
}

// Send response
echo json_encode($response);