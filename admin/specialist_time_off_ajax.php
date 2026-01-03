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
    if ($supervisor_mode) {
        // Allow access in supervisor mode
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
    case 'get_time_off':
        getTimeOff();
        break;
    case 'save_time_off':
        saveTimeOff();
        break;
    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

function getTimeOff() {
    global $pdo;
    
    if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
        exit;
    }
    
    $specialist_id = trim($_POST['specialist_id']);
    
    try {
        // Get all time off dates for this specialist
        $stmt = $pdo->prepare("
            SELECT date_off FROM specialist_time_off 
            WHERE specialist_id = ? 
            ORDER BY date_off
        ");
        $stmt->execute([$specialist_id]);
        $time_off_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format dates as YYYY-MM-DD
        $dates = array_map(function($record) {
            return date('Y-m-d', strtotime($record['date_off']));
        }, $time_off_records);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'dates' => $dates
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in getTimeOff: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function saveTimeOff() {
    global $pdo;

    if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
        exit;
    }

    if (!isset($_POST['dates'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Dates are required']);
        exit;
    }

    $specialist_id = trim($_POST['specialist_id']);
    $dates = json_decode($_POST['dates'], true);
    $details = isset($_POST['details']) ? json_decode($_POST['details'], true) : [];

    if (!is_array($dates)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid dates format']);
        exit;
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // First, delete all existing time off records for this specialist
        $stmt = $pdo->prepare("DELETE FROM specialist_time_off WHERE specialist_id = ?");
        $stmt->execute([$specialist_id]);

        // Insert new time off records
        if (!empty($dates)) {
            $stmt = $pdo->prepare("
                INSERT INTO specialist_time_off (specialist_id, date_off, start_time, end_time)
                VALUES (?, ?, ?, ?)
            ");

            $recordsInserted = 0;
            foreach ($dates as $date) {
                // Validate date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new Exception("Invalid date format: $date");
                }

                // Check if this date has details (partial day off)
                $dayDetails = isset($details[$date]) ? $details[$date] : null;

                if ($dayDetails && isset($dayDetails['type']) && $dayDetails['type'] === 'partial' &&
                    !empty($dayDetails['workStart']) && !empty($dayDetails['workEnd'])) {
                    // Partial day off - create two records
                    $workStart = $dayDetails['workStart'];
                    $workEnd = $dayDetails['workEnd'];

                    // Validate time format
                    if (!preg_match('/^\d{2}:\d{2}$/', $workStart) || !preg_match('/^\d{2}:\d{2}$/', $workEnd)) {
                        throw new Exception("Invalid time format for date: $date");
                    }

                    // Calculate the off periods
                    // Period 1: 00:01:00 to (workStart - 1 minute)
                    $workStartTime = strtotime("1970-01-01 $workStart:00");
                    $offEnd1 = date('H:i:s', $workStartTime - 60); // 1 minute before work starts

                    // Period 2: (workEnd + 1 minute) to 23:59:00
                    $workEndTime = strtotime("1970-01-01 $workEnd:00");
                    $offStart2 = date('H:i:s', $workEndTime + 60); // 1 minute after work ends

                    // Insert first off period (morning off)
                    if ($offEnd1 >= '00:01:00') {
                        $stmt->execute([$specialist_id, $date, '00:01:00', $offEnd1]);
                        $recordsInserted++;
                    }

                    // Insert second off period (evening off)
                    if ($offStart2 <= '23:59:00') {
                        $stmt->execute([$specialist_id, $date, $offStart2, '23:59:00']);
                        $recordsInserted++;
                    }
                } else {
                    // Full day off - single record
                    $stmt->execute([$specialist_id, $date, '00:01:00', '23:59:00']);
                    $recordsInserted++;
                }
            }
        }

        // Commit transaction
        $pdo->commit();

        error_log("Updated time off for specialist_id=$specialist_id: " . count($dates) . " days, $recordsInserted records inserted");

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Time off dates saved successfully',
            'days_saved' => count($dates),
            'records_inserted' => $recordsInserted
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error in saveTimeOff: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>