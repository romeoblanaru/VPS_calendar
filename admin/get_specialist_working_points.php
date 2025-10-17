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

if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
    exit;
}

$specialist_id = trim($_POST['specialist_id']);

try {
    // Get working points assignments for the specialist from working_program table (only if at least one non-zero shift exists)
    $stmt = $pdo->prepare("
        SELECT 
            wp.unic_id as wp_id,
            wp.name_of_the_place as wp_name,
            wp.address as wp_address,
            wp.organisation_id,
            GROUP_CONCAT(
                CONCAT(
                    wp2.day_of_week, ': ', 
                    TIME_FORMAT(wp2.shift1_start, '%H:%i'), '-', TIME_FORMAT(wp2.shift1_end, '%H:%i')
                ) ORDER BY 
                    CASE wp2.day_of_week 
                        WHEN 'Monday' THEN 1 
                        WHEN 'Tuesday' THEN 2 
                        WHEN 'Wednesday' THEN 3 
                        WHEN 'Thursday' THEN 4 
                        WHEN 'Friday' THEN 5 
                        WHEN 'Saturday' THEN 6 
                        WHEN 'Sunday' THEN 7 
                    END
                SEPARATOR '; '
            ) as working_program
        FROM working_points wp
        JOIN working_program wp2 ON wp.unic_id = wp2.working_place_id 
            AND wp.organisation_id = wp2.organisation_id
        WHERE wp2.specialist_id = ?
          AND ((wp2.shift1_start <> '00:00:00' AND wp2.shift1_end <> '00:00:00')
            OR (wp2.shift2_start <> '00:00:00' AND wp2.shift2_end <> '00:00:00')
            OR (wp2.shift3_start <> '00:00:00' AND wp2.shift3_end <> '00:00:00'))
        GROUP BY wp.unic_id, wp.name_of_the_place, wp.address, wp.organisation_id
        ORDER BY wp.name_of_the_place
    ");
    $stmt->execute([$specialist_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'assignments' => $assignments
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_specialist_working_points.php (working_program table): " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 