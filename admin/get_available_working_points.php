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

if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
    exit;
}

$specialist_id = trim($_POST['specialist_id']);
$is_orphaned = isset($_POST['is_orphaned']) && $_POST['is_orphaned'] === 'true';

try {
    // Get the specialist's organisation_id first
    $stmt = $pdo->prepare("SELECT organisation_id FROM specialists WHERE unic_id = ?");
    $stmt->execute([$specialist_id]);
    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$specialist) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist not found']);
        exit;
    }
    
    $organisation_id = $specialist['organisation_id'];
    
    // For orphaned specialists, show all working points in the organization
    // For regular specialists, exclude already assigned working points
    if ($is_orphaned) {
        $stmt = $pdo->prepare("
            SELECT 
                wp.unic_id,
                wp.name_of_the_place,
                wp.address
            FROM working_points wp
            WHERE wp.organisation_id = ?
            ORDER BY wp.name_of_the_place
        ");
        $stmt->execute([$organisation_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                wp.unic_id,
                wp.name_of_the_place,
                wp.address
            FROM working_points wp
            WHERE wp.organisation_id = ?
            AND wp.unic_id NOT IN (
                SELECT DISTINCT working_place_id 
                FROM working_program 
                WHERE specialist_id = ? AND organisation_id = ?
            )
            ORDER BY wp.name_of_the_place
        ");
        $stmt->execute([$organisation_id, $specialist_id, $organisation_id]);
    }
    $working_points = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'working_points' => $working_points,
        'debug' => [
            'is_orphaned' => $is_orphaned,
            'organisation_id' => $organisation_id,
            'specialist_id' => $specialist_id,
            'count' => count($working_points)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_available_working_points.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 