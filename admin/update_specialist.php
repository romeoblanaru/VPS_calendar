<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin_user') {
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$required_fields = ['specialist_id', 'name', 'speciality'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

$specialist_id = trim($_POST['specialist_id']);

try {
    $stmt = $pdo->prepare("SELECT unic_id FROM specialists WHERE unic_id = ?");
    $stmt->execute([$specialist_id]);
    if (!$stmt->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist not found']);
        exit;
    }
    
    $update_data = [
        'name' => trim($_POST['name']),
        'speciality' => trim($_POST['speciality']),
        'email' => trim($_POST['email'] ?? ''),
        'phone_nr' => trim($_POST['phone_nr'] ?? ''),
        'user' => trim($_POST['user'] ?? ''),
        'password' => trim($_POST['password'] ?? '')
    ];
    
    $sql_parts = [];
    $params = [];
    
    foreach ($update_data as $field => $value) {
        $sql_parts[] = "$field = ?";
        $params[] = $value;
    }
    
    $params[] = $specialist_id;
    
    $sql = "UPDATE specialists SET " . implode(', ', $sql_parts) . " WHERE unic_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result && $stmt->rowCount() > 0) {
        error_log("Admin updated specialist: $specialist_id - " . $update_data['name']);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Specialist updated successfully',
            'rows_affected' => $stmt->rowCount()
        ]);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'No changes were made to the specialist'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update_specialist.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 