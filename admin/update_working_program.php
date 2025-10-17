<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$required_fields = ['specialist_id', 'working_point_id', 'day_of_week'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

$specialist_id = trim($_POST['specialist_id']);
$working_point_id = trim($_POST['working_point_id']);
$day_of_week = trim($_POST['day_of_week']);

// Get shift times (optional fields)
$shift1_start = trim($_POST['shift1_start'] ?? '00:00');
$shift1_end = trim($_POST['shift1_end'] ?? '00:00');
$shift2_start = trim($_POST['shift2_start'] ?? '00:00');
$shift2_end = trim($_POST['shift2_end'] ?? '00:00');
$shift3_start = trim($_POST['shift3_start'] ?? '00:00');
$shift3_end = trim($_POST['shift3_end'] ?? '00:00');

try {
    // Check if this specialist and working point combination exists
    $stmt = $pdo->prepare("SELECT organisation_id FROM specialists WHERE unic_id = ?");
    $stmt->execute([$specialist_id]);
    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$specialist) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist not found']);
        exit;
    }
    
    $organisation_id = $specialist['organisation_id'];
    
    // Check if working point belongs to the same organisation
    $stmt = $pdo->prepare("SELECT unic_id FROM working_points WHERE unic_id = ? AND organisation_id = ?");
    $stmt->execute([$working_point_id, $organisation_id]);
    if (!$stmt->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Working point not found or does not belong to the same organisation']);
        exit;
    }
    
    // Check if a record already exists for this day
    $stmt = $pdo->prepare("SELECT unic_id FROM working_program WHERE specialist_id = ? AND working_place_id = ? AND day_of_week = ?");
    $stmt->execute([$specialist_id, $working_point_id, $day_of_week]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE working_program 
            SET shift1_start = ?, shift1_end = ?, shift2_start = ?, shift2_end = ?, shift3_start = ?, shift3_end = ?
            WHERE specialist_id = ? AND working_place_id = ? AND day_of_week = ?
        ");
        $result = $stmt->execute([
            $shift1_start, $shift1_end, $shift2_start, $shift2_end, $shift3_start, $shift3_end,
            $specialist_id, $working_point_id, $day_of_week
        ]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO working_program (specialist_id, working_place_id, organisation_id, day_of_week, shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $specialist_id, $working_point_id, $organisation_id, $day_of_week,
            $shift1_start, $shift1_end, $shift2_start, $shift2_end, $shift3_start, $shift3_end
        ]);
    }
    
    if ($result) {
        error_log("Admin updated working program: specialist_id=$specialist_id, working_point_id=$working_point_id, day=$day_of_week");
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Working program updated successfully'
        ]);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to update working program']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update_working_program.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 