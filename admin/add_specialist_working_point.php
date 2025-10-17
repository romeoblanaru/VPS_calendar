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

$required_fields = ['specialist_id', 'wp_id', 'shift1_start', 'shift1_end'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

// Check if day_of_week is provided
if (!isset($_POST['day_of_week']) || empty($_POST['day_of_week'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Day of week is required']);
    exit;
}

$specialist_id = trim($_POST['specialist_id']);
$wp_id = trim($_POST['wp_id']);
$day_of_week_raw = $_POST['day_of_week'];
$days_of_week = [];

// Debug logging
error_log("Received day_of_week data: " . print_r($day_of_week_raw, true));

// Handle both single day and multiple days
if (is_array($day_of_week_raw)) {
    $days_of_week = array_map('trim', $day_of_week_raw);
} else {
    $days_of_week = [trim($day_of_week_raw)];
}

error_log("Processed days_of_week: " . print_r($days_of_week, true));
$shift1_start = trim($_POST['shift1_start']);
$shift1_end = trim($_POST['shift1_end']);
$shift2_start = trim($_POST['shift2_start'] ?? '');
$shift2_end = trim($_POST['shift2_end'] ?? '');
$shift3_start = trim($_POST['shift3_start'] ?? '');
$shift3_end = trim($_POST['shift3_end'] ?? '');

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
    

    
    // Check if this is an orphaned specialist (has no active assignments)
    $check_orphaned_stmt = $pdo->prepare("
        SELECT COUNT(*) as active_count 
        FROM working_program 
        WHERE specialist_id = ? AND organisation_id = ? 
        AND day_of_week IS NOT NULL 
        AND day_of_week != '' 
        AND shift1_start IS NOT NULL 
        AND shift1_start != ''
    ");
    $check_orphaned_stmt->execute([$specialist_id, $organisation_id]);
    $orphaned_result = $check_orphaned_stmt->fetch(PDO::FETCH_ASSOC);
    $is_orphaned = ($orphaned_result['active_count'] == 0);
    
    // Debug logging
    error_log("Specialist $specialist_id orphaned status: " . ($is_orphaned ? 'true' : 'false') . " (active_count: {$orphaned_result['active_count']})");
    
    // If orphaned specialist, clean up any existing null/empty assignments first
    if ($is_orphaned) {
        $cleanup_stmt = $pdo->prepare("
            DELETE FROM working_program 
            WHERE specialist_id = ? AND organisation_id = ? 
            AND (day_of_week IS NULL OR day_of_week = '' OR shift1_start IS NULL OR shift1_start = '')
        ");
        $cleanup_stmt->execute([$specialist_id, $organisation_id]);
        $cleanup_count = $cleanup_stmt->rowCount();
        error_log("Cleaned up $cleanup_count null/empty assignments for orphaned specialist $specialist_id");
    }
    
    // Add new assignments to working_program table for each selected day
    $stmt = $pdo->prepare("
        INSERT INTO working_program (specialist_id, working_place_id, organisation_id, day_of_week, shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $success_count = 0;
    $total_days = count($days_of_week);
    
    foreach ($days_of_week as $day_of_week) {
        // For orphaned specialists, skip duplicate check since we cleaned up existing records
        // For regular specialists, check if assignment already exists for this specific day
        if (!$is_orphaned) {
            $check_stmt = $pdo->prepare("SELECT unic_id FROM working_program WHERE specialist_id = ? AND working_place_id = ? AND organisation_id = ? AND day_of_week = ?");
            $check_stmt->execute([$specialist_id, $wp_id, $organisation_id, $day_of_week]);
            
            if ($check_stmt->fetch()) {
                continue; // Skip this day if already assigned
            }
        }
        
        $result = $stmt->execute([$specialist_id, $wp_id, $organisation_id, $day_of_week, $shift1_start, $shift1_end, $shift2_start, $shift2_end, $shift3_start, $shift3_end]);
        if ($result) {
            $success_count++;
        }
    }
    
    if ($success_count > 0) {
        error_log("Admin added working point assignments: specialist_id=$specialist_id, wp_id=$wp_id, organisation_id=$organisation_id, days_added=$success_count, is_orphaned=" . ($is_orphaned ? 'true' : 'false'));
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => "Working point assignments added successfully for $success_count day(s)"
        ]);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'All selected days already have assignments for this specialist and working point']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in add_specialist_working_point.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 