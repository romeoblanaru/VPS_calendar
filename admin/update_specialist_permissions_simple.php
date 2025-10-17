<?php
require_once '../includes/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $specialist_id = $_POST['specialist_id'] ?? null;
        $permission_field = $_POST['permission_field'] ?? null;
        $permission_value = $_POST['permission_value'] ?? null;
        
        echo json_encode([
            'success' => true,
            'message' => 'Debug info',
            'data' => [
                'specialist_id' => $specialist_id,
                'permission_field' => $permission_field,
                'permission_value' => $permission_value,
                'post_data' => $_POST
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 