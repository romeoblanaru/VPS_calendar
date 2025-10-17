<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin_user', 'organisation_user', 'workpoint_user'])) {
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

$action = $_POST['action'] ?? '';

if ($action !== 'reactivate_specialist') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
    exit;
}

if (!isset($_POST['workpoint_id']) || empty($_POST['workpoint_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
    exit;
}

if (!isset($_POST['organisation_id']) || empty($_POST['organisation_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Organisation ID is required']);
    exit;
}

$specialist_id = trim($_POST['specialist_id']);
$workpoint_id = trim($_POST['workpoint_id']);
$organisation_id = trim($_POST['organisation_id']);



try {
    // Verify that the specialist exists and belongs to this organisation
    $stmt = $pdo->prepare("SELECT * FROM specialists WHERE unic_id = ? AND organisation_id = ?");
    $stmt->execute([$specialist_id, $organisation_id]);
    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$specialist) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist not found or does not belong to this organisation']);
        exit;
    }
    
    // Verify that the workpoint exists and belongs to this organisation
    $stmt = $pdo->prepare("SELECT * FROM working_points WHERE unic_id = ? AND organisation_id = ?");
    $stmt->execute([$workpoint_id, $organisation_id]);
    $workpoint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workpoint) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Workpoint not found or does not belong to this organisation']);
        exit;
    }
    
    // Check if specialist already has a working program at this workpoint
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM working_program WHERE specialist_id = ? AND working_place_id = ?");
    $stmt->execute([$specialist_id, $workpoint_id]);
    $existing_count = $stmt->fetchColumn();
    
    if ($existing_count > 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist already has a working program at this workpoint']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert working program entries for the schedule
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $inserted_count = 0;
    
    foreach ($days as $day) {
        // Get shift times from POST data - access the nested array correctly
        $shift1_start = trim($_POST["schedule"][$day]["shift1_start"] ?? '');
        $shift1_end = trim($_POST["schedule"][$day]["shift1_end"] ?? '');
        $shift2_start = trim($_POST["schedule"][$day]["shift2_start"] ?? '');
        $shift2_end = trim($_POST["schedule"][$day]["shift2_end"] ?? '');
        $shift3_start = trim($_POST["schedule"][$day]["shift3_start"] ?? '');
        $shift3_end = trim($_POST["schedule"][$day]["shift3_end"] ?? '');
        
        // Convert time format to include seconds if needed
        $shift1_start = $shift1_start ? $shift1_start . ':00' : '00:00:00';
        $shift1_end = $shift1_end ? $shift1_end . ':00' : '00:00:00';
        $shift2_start = $shift2_start ? $shift2_start . ':00' : '00:00:00';
        $shift2_end = $shift2_end ? $shift2_end . ':00' : '00:00:00';
        $shift3_start = $shift3_start ? $shift3_start . ':00' : '00:00:00';
        $shift3_end = $shift3_end ? $shift3_end . ':00' : '00:00:00';
        
        // Check if any shift has valid times
        $has_valid_shift = false;
        if ($shift1_start !== '00:00:00' && $shift1_end !== '00:00:00' && $shift1_start !== '' && $shift1_end !== '') {
            $has_valid_shift = true;
        }
        if ($shift2_start !== '00:00:00' && $shift2_end !== '00:00:00' && $shift2_start !== '' && $shift2_end !== '') {
            $has_valid_shift = true;
        }
        if ($shift3_start !== '00:00:00' && $shift3_end !== '00:00:00' && $shift3_start !== '' && $shift3_end !== '') {
            $has_valid_shift = true;
        }
        
        if ($has_valid_shift) {
            $stmt = $pdo->prepare("
                INSERT INTO working_program (specialist_id, working_place_id, organisation_id, day_of_week, shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $specialist_id, $workpoint_id, $organisation_id, ucfirst($day),
                $shift1_start, $shift1_end, $shift2_start, $shift2_end, $shift3_start, $shift3_end
            ]);
            $inserted_count++;
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Specialist reactivated successfully',
        'days_updated' => $inserted_count
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Database error in reactivate_specialist: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 