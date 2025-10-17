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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'get_booked_dates') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
    exit;
}

$specialist_id = trim($_POST['specialist_id']);
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

try {
    // Check if the appointments table exists first
    $tableExists = false;
    $possibleTables = ['booking', 'appointments', 'bookings', 'reservations', 'appointment'];
    $actualTable = null;
    $dateColumn = 'booking_date';
    $statusColumn = 'status';
    
    foreach ($possibleTables as $tableName) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM $tableName LIMIT 1");
            $tableExists = true;
            $actualTable = $tableName;
            
            // Check which columns exist
            $stmt = $pdo->query("SHOW COLUMNS FROM $tableName");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Find the date column - look for specific patterns
            $possibleDateColumns = ['booking_start_datetime', 'booking_date', 'appointment_date', 'date', 'datetime', 'start_datetime', 'start_date'];
            foreach ($possibleDateColumns as $col) {
                if (in_array($col, $columns)) {
                    $dateColumn = $col;
                    break;
                }
            }
            
            // If not found in specific list, look for any column with date/time
            if ($dateColumn == 'booking_date') {
                foreach ($columns as $col) {
                    if (strpos($col, 'date') !== false || strpos($col, 'time') !== false) {
                        $dateColumn = $col;
                        break;
                    }
                }
            }
            
            // Check if status column exists
            if (!in_array('status', $columns)) {
                $statusColumn = null;
            }
            
            break;
        } catch (PDOException $e) {
            // Table doesn't exist, try next
            continue;
        }
    }
    
    if (!$tableExists || !$actualTable) {
        // No booking table found, return empty result
        ob_clean();
        echo json_encode([
            'success' => true,
            'dates' => []
        ]);
        exit;
    }
    
    // Find the specialist column (could be specialist_id, id_specialist, etc.)
    $specialistColumn = null;
    $possibleSpecialistColumns = ['specialist_id', 'id_specialist', 'specialist', 'spec_id', 'specialist_unic_id'];
    
    $stmt = $pdo->query("SHOW COLUMNS FROM $actualTable");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($possibleSpecialistColumns as $col) {
        if (in_array($col, $columns)) {
            $specialistColumn = $col;
            break;
        }
    }
    
    if (!$specialistColumn) {
        // No specialist column found, return empty result
        ob_clean();
        echo json_encode([
            'success' => true,
            'dates' => []
        ]);
        exit;
    }
    
    // Build the query based on what we found - only future bookings
    $today = date('Y-m-d');
    $query = "SELECT DATE($dateColumn) as booking_date, 
              COUNT(*) as booking_count 
              FROM $actualTable 
              WHERE $specialistColumn = ? 
              AND YEAR($dateColumn) = ?
              AND DATE($dateColumn) >= ?";
    
    if ($statusColumn) {
        $query .= " AND $statusColumn NOT IN ('cancelled', 'rejected')";
    }
    
    $query .= " GROUP BY DATE($dateColumn) ORDER BY booking_date";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$specialist_id, $year, $today]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates with counts
    $dates = array_map(function($booking) {
        return [
            'date' => date('Y-m-d', strtotime($booking['booking_date'])),
            'count' => intval($booking['booking_count'])
        ];
    }, $bookings);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'dates' => $dates
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_booked_dates: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>