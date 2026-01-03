<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check authentication - allow workpoint_user and organisation_user
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

switch ($action) {
    case 'add_full_day':
        addFullDayOff();
        break;
    case 'remove_day':
        removeDayOff();
        break;
    case 'convert_to_partial':
        convertToPartial();
        break;
    case 'convert_to_full':
        convertToFull();
        break;
    case 'update_working_hours':
        updateWorkingHours();
        break;
    case 'get_time_off_details':
        getTimeOffDetails();
        break;
    case 'update_recurring':
        updateRecurring();
        break;
    case 'update_description':
        updateDescription();
        break;
    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

function addFullDayOff() {
    global $pdo;

    if (!isset($_POST['workingpoint_id']) || !isset($_POST['date'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $workingpoint_id = trim($_POST['workingpoint_id']);
    $date = trim($_POST['date']);
    $is_recurring = isset($_POST['is_recurring']) ? (int)$_POST['is_recurring'] : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    try {
        // Check if already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM workingpoint_time_off WHERE workingpoint_id = ? AND date_off = ?");
        $stmt->execute([$workingpoint_id, $date]);

        if ($stmt->fetchColumn() > 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Day off already exists']);
            exit;
        }

        // Insert full day off
        $stmt = $pdo->prepare("INSERT INTO workingpoint_time_off (workingpoint_id, date_off, start_time, end_time, is_recurring, description) VALUES (?, ?, '00:01:00', '23:59:00', ?, ?)");
        $stmt->execute([$workingpoint_id, $date, $is_recurring, $description]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Full day off added']);

    } catch (PDOException $e) {
        error_log("Error in addFullDayOff: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function removeDayOff() {
    global $pdo;

    if (!isset($_POST['workingpoint_id']) || !isset($_POST['date'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $workingpoint_id = trim($_POST['workingpoint_id']);
    $date = trim($_POST['date']);

    try {
        // Delete all records for this date (both full and partial)
        $stmt = $pdo->prepare("DELETE FROM workingpoint_time_off WHERE workingpoint_id = ? AND date_off = ?");
        $stmt->execute([$workingpoint_id, $date]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Day off removed', 'rows_deleted' => $stmt->rowCount()]);

    } catch (PDOException $e) {
        error_log("Error in removeDayOff: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function convertToPartial() {
    global $pdo;

    if (!isset($_POST['workingpoint_id']) || !isset($_POST['date'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $workingpoint_id = trim($_POST['workingpoint_id']);
    $date = trim($_POST['date']);

    try {
        $pdo->beginTransaction();

        // Check current state
        $stmt = $pdo->prepare("SELECT id, start_time, end_time, is_recurring, description FROM workingpoint_time_off WHERE workingpoint_id = ? AND date_off = ? ORDER BY id");
        $stmt->execute([$workingpoint_id, $date]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($records) === 1 && $records[0]['start_time'] === '00:01:00' && $records[0]['end_time'] === '23:59:00') {
            // It's a full day, create second record for split
            $stmt = $pdo->prepare("INSERT INTO workingpoint_time_off (workingpoint_id, date_off, start_time, end_time, is_recurring, description) VALUES (?, ?, '00:01:00', '23:59:00', ?, ?)");
            $stmt->execute([$workingpoint_id, $date, $records[0]['is_recurring'], $records[0]['description']]);

            $pdo->commit();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Converted to partial (2 records created)']);
        } else {
            $pdo->rollBack();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Already in partial mode or invalid state']);
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in convertToPartial: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function convertToFull() {
    global $pdo;

    if (!isset($_POST['workingpoint_id']) || !isset($_POST['date'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $workingpoint_id = trim($_POST['workingpoint_id']);
    $date = trim($_POST['date']);

    try {
        $pdo->beginTransaction();

        // Get all records for this date
        $stmt = $pdo->prepare("SELECT id, is_recurring, description FROM workingpoint_time_off WHERE workingpoint_id = ? AND date_off = ? ORDER BY id");
        $stmt->execute([$workingpoint_id, $date]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($records) >= 2) {
            // Delete all but keep the first one
            $firstId = $records[0]['id'];

            // Delete all except first
            $stmt = $pdo->prepare("DELETE FROM workingpoint_time_off WHERE workingpoint_id = ? AND date_off = ? AND id != ?");
            $stmt->execute([$workingpoint_id, $date, $firstId]);

            // Update first record to full day
            $stmt = $pdo->prepare("UPDATE workingpoint_time_off SET start_time = '00:01:00', end_time = '23:59:00' WHERE id = ?");
            $stmt->execute([$firstId]);

            $pdo->commit();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Converted to full day off']);
        } else if (count($records) === 1) {
            // Already single record, just update it
            $stmt = $pdo->prepare("UPDATE workingpoint_time_off SET start_time = '00:01:00', end_time = '23:59:00' WHERE workingpoint_id = ? AND date_off = ?");
            $stmt->execute([$workingpoint_id, $date]);

            $pdo->commit();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Updated to full day off']);
        } else {
            $pdo->rollBack();
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'No records found']);
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in convertToFull: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateWorkingHours() {
    global $pdo;

    if (!isset($_POST['workingpoint_id']) || !isset($_POST['date']) || !isset($_POST['work_start']) || !isset($_POST['work_end'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $workingpoint_id = trim($_POST['workingpoint_id']);
    $date = trim($_POST['date']);
    $workStart = trim($_POST['work_start']);
    $workEnd = trim($_POST['work_end']);

    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}$/', $workStart) || !preg_match('/^\d{2}:\d{2}$/', $workEnd)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Get all records for this date
        $stmt = $pdo->prepare("SELECT id FROM workingpoint_time_off WHERE workingpoint_id = ? AND date_off = ? ORDER BY id");
        $stmt->execute([$workingpoint_id, $date]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($records) < 2) {
            $pdo->rollBack();
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Not in partial mode. Convert to partial first.']);
            exit;
        }

        // Calculate off periods
        $workStartTime = strtotime("1970-01-01 $workStart:00");
        $offEnd1 = date('H:i:s', max(0, $workStartTime - 60)); // 1 minute before work starts

        $workEndTime = strtotime("1970-01-01 $workEnd:00");
        $offStart2 = date('H:i:s', min(86399, $workEndTime + 60)); // 1 minute after work ends

        // Update first record (morning off)
        $stmt = $pdo->prepare("UPDATE workingpoint_time_off SET start_time = '00:01:00', end_time = ? WHERE id = ?");
        $stmt->execute([$offEnd1, $records[0]['id']]);

        // Update second record (evening off)
        $stmt = $pdo->prepare("UPDATE workingpoint_time_off SET start_time = ?, end_time = '23:59:00' WHERE id = ?");
        $stmt->execute([$offStart2, $records[1]['id']]);

        $pdo->commit();
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Working hours updated',
            'period1' => '00:01:00 - ' . $offEnd1,
            'period2' => $offStart2 . ' - 23:59:00'
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in updateWorkingHours: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getTimeOffDetails() {
    global $pdo;

    if (!isset($_POST['workingpoint_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Workingpoint ID required']);
        exit;
    }

    $workingpoint_id = trim($_POST['workingpoint_id']);

    try {
        $stmt = $pdo->prepare("
            SELECT date_off, start_time, end_time, id, is_recurring, description
            FROM workingpoint_time_off
            WHERE workingpoint_id = ?
            ORDER BY date_off, id
        ");
        $stmt->execute([$workingpoint_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by date
        $grouped = [];
        foreach ($records as $record) {
            $date = $record['date_off'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $record;
        }

        // Determine type and working hours for each date
        $details = [];
        foreach ($grouped as $date => $dateRecords) {
            if (count($dateRecords) === 1 &&
                $dateRecords[0]['start_time'] === '00:01:00' &&
                $dateRecords[0]['end_time'] === '23:59:00') {
                // Full day off
                $details[$date] = [
                    'type' => 'full',
                    'workStart' => null,
                    'workEnd' => null,
                    'isRecurring' => (bool)$dateRecords[0]['is_recurring'],
                    'description' => $dateRecords[0]['description']
                ];
            } else if (count($dateRecords) >= 2) {
                // Partial day off - calculate working hours from off periods
                $record1 = $dateRecords[0];
                $record2 = $dateRecords[1];

                // Work start is 1 minute after first period ends
                $workStartTime = strtotime("1970-01-01 " . $record1['end_time']) + 60;
                $workStart = date('H:i', $workStartTime);

                // Work end is 1 minute before second period starts
                $workEndTime = strtotime("1970-01-01 " . $record2['start_time']) - 60;
                $workEnd = date('H:i', $workEndTime);

                $details[$date] = [
                    'type' => 'partial',
                    'workStart' => $workStart,
                    'workEnd' => $workEnd,
                    'isRecurring' => (bool)$record1['is_recurring'],
                    'description' => $record1['description']
                ];
            }
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'details' => $details,
            'dates' => array_keys($details)
        ]);

    } catch (PDOException $e) {
        error_log("Error in getTimeOffDetails: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateRecurring() {
    global $pdo;

    if (!isset($_POST['workingpoint_id']) || !isset($_POST['date']) || !isset($_POST['is_recurring'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $workingpoint_id = trim($_POST['workingpoint_id']);
    $date = trim($_POST['date']);
    $is_recurring = (int)$_POST['is_recurring'];

    try {
        // Update all records for this date
        $stmt = $pdo->prepare("UPDATE workingpoint_time_off SET is_recurring = ? WHERE workingpoint_id = ? AND date_off = ?");
        $stmt->execute([$is_recurring, $workingpoint_id, $date]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Recurring status updated']);

    } catch (PDOException $e) {
        error_log("Error in updateRecurring: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateDescription() {
    global $pdo;

    if (!isset($_POST['workingpoint_id']) || !isset($_POST['date']) || !isset($_POST['description'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $workingpoint_id = trim($_POST['workingpoint_id']);
    $date = trim($_POST['date']);
    $description = trim($_POST['description']);

    try {
        // Update all records for this date
        $stmt = $pdo->prepare("UPDATE workingpoint_time_off SET description = ? WHERE workingpoint_id = ? AND date_off = ?");
        $stmt->execute([$description, $workingpoint_id, $date]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Description updated']);

    } catch (PDOException $e) {
        error_log("Error in updateDescription: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
