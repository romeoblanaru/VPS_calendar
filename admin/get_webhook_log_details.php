<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['log_id'])) {
    echo json_encode(['success' => false, 'message' => 'Log ID is required']);
    exit();
}

$log_id = (int)$_GET['log_id'];

try {
    $stmt = $pdo->prepare("
        SELECT * FROM webhook_logs 
        WHERE id = ?
    ");
    $stmt->execute([$log_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Log not found']);
        exit();
    }
    
    // Format the data for display
    $formattedLog = [
        'id' => $log['id'],
        'webhook_name' => $log['webhook_name'],
        'request_method' => $log['request_method'],
        'request_url' => $log['request_url'],
        'request_headers' => $log['request_headers'] ? json_decode($log['request_headers'], true) : null,
        'request_body' => $log['request_body'],
        'request_params' => $log['request_params'] ? json_decode($log['request_params'], true) : null,
        'response_status_code' => $log['response_status_code'],
        'response_body' => $log['response_body'],
        'response_headers' => $log['response_headers'] ? json_decode($log['response_headers'], true) : null,
        'processing_time_ms' => $log['processing_time_ms'],
        'client_ip' => $log['client_ip'],
        'user_agent' => $log['user_agent'],
        'is_successful' => $log['is_successful'],
        'error_message' => $log['error_message'],
        'error_trace' => $log['error_trace'],
        'created_at' => $log['created_at'],
        'processed_at' => $log['processed_at'],
        'related_booking_id' => $log['related_booking_id'],
        'related_specialist_id' => $log['related_specialist_id'],
        'related_organisation_id' => $log['related_organisation_id'],
        'related_working_point_id' => $log['related_working_point_id'],
        'additional_data' => $log['additional_data'] ? json_decode($log['additional_data'], true) : null
    ];
    
    echo json_encode([
        'success' => true,
        'log' => $formattedLog
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 