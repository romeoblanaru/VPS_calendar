<?php
session_start();

// Check authentication
if ((!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) || $_SESSION['role'] !== 'admin_user') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$tools_file = __DIR__ . '/../../data/custom_tools.json';
$data_dir = dirname($tools_file);

// Create data directory if it doesn't exist
if (!file_exists($data_dir)) {
    mkdir($data_dir, 0755, true);
}

// Handle GET request - load tools
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($tools_file)) {
        $tools = json_decode(file_get_contents($tools_file), true);
        echo json_encode($tools ?: []);
    } else {
        echo json_encode([]);
    }
    exit;
}

// Handle POST request - save tools
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['tools'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid data']));
    }
    
    // Save to file
    file_put_contents($tools_file, json_encode($input['tools'], JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
exit(json_encode(['error' => 'Method not allowed']));
?>