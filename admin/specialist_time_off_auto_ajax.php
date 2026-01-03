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
    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

function addFullDayOff() {
    global $pdo;

    if (!isset($_POST['specialist_id']) || !isset($_POST['date'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $specialist_id = trim($_POST['specialist_id']);
    $date = trim($_POST['date']);

    try {
        // Check if already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM specialist_time_off WHERE specialist_id = ? AND date_off = ?");
        $stmt->execute([$specialist_id, $date]);

        if ($stmt->fetchColumn() > 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Day off already exists']);
            exit;
        }

        // Insert full day off
        $stmt = $pdo->prepare("INSERT INTO specialist_time_off (specialist_id, date_off, start_time, end_time) VALUES (?, ?, '00:01:00', '23:59:00')");
        $stmt->execute([$specialist_id, $date]);

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

    if (!isset($_POST['specialist_id']) || !isset($_POST['date'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $specialist_id = trim($_POST['specialist_id']);
    $date = trim($_POST['date']);

    try {
        // Delete all records for this date (both full and partial)
        $stmt = $pdo->prepare("DELETE FROM specialist_time_off WHERE specialist_id = ? AND date_off = ?");
        $stmt->execute([$specialist_id, $date]);

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

    if (!isset($_POST['specialist_id']) || !isset($_POST['date'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $specialist_id = trim($_POST['specialist_id']);
    $date = trim($_POST['date']);

    try {
        $pdo->beginTransaction();

        // Check current state
        $stmt = $pdo->prepare("SELECT id, start_time, end_time FROM specialist_time_off WHERE specialist_id = ? AND date_off = ? ORDER BY id");
        $stmt->execute([$specialist_id, $date]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($records) === 1 && $records[0]['start_time'] === '00:01:00' && $records[0]['end_time'] === '23:59:00') {
            // It's a full day, create second record for split
            $stmt = $pdo->prepare("INSERT INTO specialist_time_off (specialist_id, date_off, start_time, end_time) VALUES (?, ?, '00:01:00', '23:59:00')");
            $stmt->execute([$specialist_id, $date]);

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

    if (!isset($_POST['specialist_id']) || !isset($_POST['date'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $specialist_id = trim($_POST['specialist_id']);
    $date = trim($_POST['date']);

    try {
        $pdo->beginTransaction();

        // Get all records for this date
        $stmt = $pdo->prepare("SELECT id FROM specialist_time_off WHERE specialist_id = ? AND date_off = ? ORDER BY id");
        $stmt->execute([$specialist_id, $date]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($records) >= 2) {
            // Delete all but keep the first one
            $firstId = $records[0]['id'];

            // Delete all except first
            $stmt = $pdo->prepare("DELETE FROM specialist_time_off WHERE specialist_id = ? AND date_off = ? AND id != ?");
            $stmt->execute([$specialist_id, $date, $firstId]);

            // Update first record to full day
            $stmt = $pdo->prepare("UPDATE specialist_time_off SET start_time = '00:01:00', end_time = '23:59:00' WHERE id = ?");
            $stmt->execute([$firstId]);

            $pdo->commit();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Converted to full day off']);
        } else if (count($records) === 1) {
            // Already single record, just update it
            $stmt = $pdo->prepare("UPDATE specialist_time_off SET start_time = '00:01:00', end_time = '23:59:00' WHERE specialist_id = ? AND date_off = ?");
            $stmt->execute([$specialist_id, $date]);

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

    if (!isset($_POST['specialist_id']) || !isset($_POST['date']) || !isset($_POST['work_start']) || !isset($_POST['work_end'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $specialist_id = trim($_POST['specialist_id']);
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
        $stmt = $pdo->prepare("SELECT id FROM specialist_time_off WHERE specialist_id = ? AND date_off = ? ORDER BY id");
        $stmt->execute([$specialist_id, $date]);
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
        $stmt = $pdo->prepare("UPDATE specialist_time_off SET start_time = '00:01:00', end_time = ? WHERE id = ?");
        $stmt->execute([$offEnd1, $records[0]['id']]);

        // Update second record (evening off)
        $stmt = $pdo->prepare("UPDATE specialist_time_off SET start_time = ?, end_time = '23:59:00' WHERE id = ?");
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

    if (!isset($_POST['specialist_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist ID required']);
        exit;
    }

    $specialist_id = trim($_POST['specialist_id']);

    try {
        $stmt = $pdo->prepare("
            SELECT date_off, start_time, end_time, id
            FROM specialist_time_off
            WHERE specialist_id = ?
            ORDER BY date_off, id
        ");
        $stmt->execute([$specialist_id]);
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
                    'workEnd' => null
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
                    'workEnd' => $workEnd
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
?>
