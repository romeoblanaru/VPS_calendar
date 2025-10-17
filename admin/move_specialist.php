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

$required_fields = ['specialist_id', 'target_wp_id'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

$specialist_id = trim($_POST['specialist_id']);
$target_wp_id = trim($_POST['target_wp_id']);

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
    
    // Verify the target working point belongs to the same organisation
    $stmt = $pdo->prepare("SELECT unic_id FROM working_points WHERE unic_id = ? AND organisation_id = ?");
    $stmt->execute([$target_wp_id, $organisation_id]);
    if (!$stmt->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Target working point not found or does not belong to the same organisation']);
        exit;
    }
    
    // Check if specialist is already assigned to this working point
    $stmt = $pdo->prepare("SELECT unic_id FROM working_program WHERE specialist_id = ? AND working_place_id = ?");
    $stmt->execute([$specialist_id, $target_wp_id]);
    if ($stmt->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist is already assigned to this working point']);
        exit;
    }
    
    // Get the specialist's current working programs to copy them to the new working point
    $stmt = $pdo->prepare("SELECT day_of_week, shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end FROM working_program WHERE specialist_id = ? LIMIT 1");
    $stmt->execute([$specialist_id]);
    $current_program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_program) {
        // Copy the working program to the new working point
        $stmt = $pdo->prepare("
            INSERT INTO working_program (specialist_id, working_place_id, organisation_id, day_of_week, shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $specialist_id, 
            $target_wp_id, 
            $organisation_id, 
            $current_program['day_of_week'],
            $current_program['shift1_start'],
            $current_program['shift1_end'],
            $current_program['shift2_start'],
            $current_program['shift2_end'],
            $current_program['shift3_start'],
            $current_program['shift3_end']
        ]);
        
        if ($result) {
            error_log("Admin moved specialist: specialist_id=$specialist_id, target_wp_id=$target_wp_id, organisation_id=$organisation_id");
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Specialist moved successfully'
            ]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to move specialist']);
        }
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist has no working program to copy']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in move_specialist.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 