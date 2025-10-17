<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['organisation_user', 'admin_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$specialist_id = $_POST['specialist_id'] ?? '';
$workpoint_id = $_POST['workpoint_id'] ?? '';
$schedule_data = $_POST['schedule'] ?? [];

if (!$specialist_id) {
    echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
    exit;
}

if (!$workpoint_id) {
    echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
    exit;
}

try {
    // Check if specialist exists and belongs to this organisation
    $stmt = $pdo->prepare("SELECT organisation_id FROM specialists WHERE unic_id = ?");
    $stmt->execute([$specialist_id]);
    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$specialist) {
        echo json_encode(['success' => false, 'message' => 'Specialist not found']);
        exit;
    }
    
    $organisation_id = $specialist['organisation_id'];
    
    // Check if workpoint exists and belongs to the same organisation
    $stmt = $pdo->prepare("SELECT unic_id FROM working_points WHERE unic_id = ? AND organisation_id = ?");
    $stmt->execute([$workpoint_id, $organisation_id]);
    $workpoint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workpoint) {
        echo json_encode(['success' => false, 'message' => 'Workpoint not found or does not belong to this organisation']);
        exit;
    }
    
    // Check if specialist already has a working program at this workpoint
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM working_program WHERE specialist_id = ? AND working_place_id = ?");
    $stmt->execute([$specialist_id, $workpoint_id]);
    $existing_count = $stmt->fetchColumn();
    
    if ($existing_count > 0) {
        echo json_encode(['success' => false, 'message' => 'Specialist already has a working program at this workpoint']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert working program entries for the schedule
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $inserted_count = 0;
    
    foreach ($days as $day) {
        $day_capitalized = ucfirst($day);
        
        // Get schedule data for this day
        $shift1_start = $schedule_data[$day]['shift1_start'] ?? '00:00:00';
        $shift1_end = $schedule_data[$day]['shift1_end'] ?? '00:00:00';
        $shift2_start = $schedule_data[$day]['shift2_start'] ?? '00:00:00';
        $shift2_end = $schedule_data[$day]['shift2_end'] ?? '00:00:00';
        $shift3_start = $schedule_data[$day]['shift3_start'] ?? '00:00:00';
        $shift3_end = $schedule_data[$day]['shift3_end'] ?? '00:00:00';
        
        // Insert working program entry
        $stmt = $pdo->prepare("
            INSERT INTO working_program (specialist_id, working_place_id, organisation_id, day_of_week, 
                                       shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $specialist_id, $workpoint_id, $organisation_id, $day_capitalized,
            $shift1_start, $shift1_end, $shift2_start, $shift2_end, $shift3_start, $shift3_end
        ]);
        
        if ($result) {
            $inserted_count++;
        }
    }
    
    if ($inserted_count > 0) {
        $pdo->commit();
        
        error_log("Admin assigned orphaned specialist to workpoint: specialist_id=$specialist_id, workpoint_id=$workpoint_id, organisation_id=$organisation_id, days_inserted=$inserted_count");
        
        echo json_encode([
            'success' => true,
            'message' => 'Orphaned specialist assigned to workpoint successfully'
        ]);
    } else {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to assign specialist to workpoint']);
    }
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("Database error in assign_orphaned_specialist.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 