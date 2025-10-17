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

if (!isset($_POST['wp_id']) || empty($_POST['wp_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Working Point ID is required']);
    exit;
}

if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
    exit;
}

$wp_id = trim($_POST['wp_id']);
$specialist_id = trim($_POST['specialist_id']);

try {
    // Get the specialist's organisation_id
    $stmt = $pdo->prepare("SELECT organisation_id FROM specialists WHERE unic_id = ?");
    $stmt->execute([$specialist_id]);
    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$specialist) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist not found']);
        exit;
    }
    
    $organisation_id = $specialist['organisation_id'];
    
    // Remove the assignment from working_program table
    $stmt = $pdo->prepare("DELETE FROM working_program WHERE specialist_id = ? AND working_place_id = ? AND organisation_id = ?");
    $result = $stmt->execute([$specialist_id, $wp_id, $organisation_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        error_log("Admin removed working point assignment: specialist_id=$specialist_id, wp_id=$wp_id, organisation_id=$organisation_id");
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Working point assignment removed successfully'
        ]);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Assignment not found or already removed']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in remove_specialist_working_point.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 