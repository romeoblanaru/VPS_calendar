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

if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
    exit;
}

if (!isset($_POST['working_point_id']) || empty($_POST['working_point_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Working point ID is required']);
    exit;
}

$specialist_id = trim($_POST['specialist_id']);
$working_point_id = trim($_POST['working_point_id']);

try {
    // Get working program data for the specialist at this working point
    $stmt = $pdo->prepare("
        SELECT 
            day_of_week,
            shift1_start,
            shift1_end,
            shift2_start,
            shift2_end,
            shift3_start,
            shift3_end
        FROM working_program 
        WHERE specialist_id = ? AND working_place_id = ?
        ORDER BY 
            CASE day_of_week 
                WHEN 'Monday' THEN 1 
                WHEN 'Tuesday' THEN 2 
                WHEN 'Wednesday' THEN 3 
                WHEN 'Thursday' THEN 4 
                WHEN 'Friday' THEN 5 
                WHEN 'Saturday' THEN 6 
                WHEN 'Sunday' THEN 7 
            END
    ");
    $stmt->execute([$specialist_id, $working_point_id]);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a structured array with all days of the week
    $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $schedule = [];
    
    foreach ($days_of_week as $day) {
        $day_data = [
            'day_of_week' => $day,
            'shift1_start' => '00:00',
            'shift1_end' => '00:00',
            'shift2_start' => '00:00',
            'shift2_end' => '00:00',
            'shift3_start' => '00:00',
            'shift3_end' => '00:00',
            'has_schedule' => false
        ];
        
        // Find if there's data for this day
        foreach ($programs as $program) {
            if ($program['day_of_week'] === $day) {
                $day_data['shift1_start'] = $program['shift1_start'] ?? '00:00';
                $day_data['shift1_end'] = $program['shift1_end'] ?? '00:00';
                $day_data['shift2_start'] = $program['shift2_start'] ?? '00:00';
                $day_data['shift2_end'] = $program['shift2_end'] ?? '00:00';
                $day_data['shift3_start'] = $program['shift3_start'] ?? '00:00';
                $day_data['shift3_end'] = $program['shift3_end'] ?? '00:00';
                $day_data['has_schedule'] = true;
                break;
            }
        }
        
        $schedule[] = $day_data;
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'schedule' => $schedule,
        'specialist_id' => $specialist_id,
        'working_point_id' => $working_point_id
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_working_program_details.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 