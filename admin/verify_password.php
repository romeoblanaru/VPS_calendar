<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

if (!isset($_POST['password']) || empty($_POST['password'])) {
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit();
}

$input_password = trim($_POST['password']);

try {
    // Get the current user's password from the database
    $stmt = $pdo->prepare("SELECT pasword FROM super_users WHERE unic_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    // Compare the input password with the stored password
    if ($input_password === $user['pasword']) {
        echo json_encode(['success' => true, 'message' => 'Password verified']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Incorrect password']);
    }
    
} catch (Exception $e) {
    error_log("Error verifying password: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 