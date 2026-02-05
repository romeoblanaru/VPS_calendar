<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['workpoint_user', 'organisation_user', 'admin_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_GET['workpoint_id']) || empty($_GET['workpoint_id'])) {
    echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
    exit;
}

$workpoint_id = intval($_GET['workpoint_id']);

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    $response = [
        'success' => true,
        'total_specialists' => 0,
        'active_in_period' => 0,
        'total_services' => 0,
        'avg_services_per_specialist' => 0,
        'specialists' => []
    ];

    // Get total number of specialists for this workpoint (via services)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id_specialist) as count
        FROM services s
        WHERE s.id_work_place = ?
        AND s.deleted = 0
        AND s.id_specialist IS NOT NULL
    ");
    $stmt->execute([$workpoint_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['total_specialists'] = $result['count'];

    // Get specialists active in the period (have bookings in date range)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT b.id_specialist) as count
        FROM booking b
        WHERE b.id_work_place = ?
        AND DATE(b.booking_start_datetime) BETWEEN ? AND ?
    ");
    $stmt->execute([$workpoint_id, $start_date, $end_date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['active_in_period'] = $result['count'];

    // Get total services for this workpoint
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.unic_id) as count
        FROM services s
        WHERE s.id_work_place = ?
        AND s.deleted = 0
    ");
    $stmt->execute([$workpoint_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['total_services'] = $result['count'];

    // Calculate average services per specialist
    if ($response['total_specialists'] > 0) {
        $response['avg_services_per_specialist'] = round($response['total_services'] / $response['total_specialists'], 1);
    }

    // Get detailed specialist information (only those who have services in this workpoint)
    $stmt = $pdo->prepare("
        SELECT
            sp.unic_id,
            sp.name,
            sp.speciality,
            COUNT(DISTINCT s.unic_id) as service_count,
            COUNT(DISTINCT b.unic_id) as booking_count,
            COUNT(DISTINCT CASE WHEN DATE(b.booking_start_datetime) > ? THEN b.unic_id END) as future_bookings,
            COUNT(DISTINCT CASE WHEN DATE(b.booking_start_datetime) <= ? THEN b.unic_id END) as past_bookings
        FROM specialists sp
        INNER JOIN services s ON s.id_specialist = sp.unic_id AND s.id_work_place = ? AND s.deleted = 0
        LEFT JOIN booking b ON b.id_specialist = sp.unic_id
            AND b.id_work_place = ?
            AND DATE(b.booking_start_datetime) BETWEEN ? AND ?
        GROUP BY sp.unic_id, sp.name, sp.speciality
        ORDER BY booking_count DESC
    ");
    $stmt->execute([$end_date, $end_date, $workpoint_id, $workpoint_id, $start_date, $end_date]);
    $specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($specialists as &$specialist) {
        $response['specialists'][] = [
            'id' => $specialist['unic_id'],
            'name' => $specialist['name'],
            'speciality' => $specialist['speciality'] ?: 'Not specified',
            'service_count' => intval($specialist['service_count']),
            'booking_count' => intval($specialist['booking_count']),
            'future_bookings' => intval($specialist['future_bookings']),
            'past_bookings' => intval($specialist['past_bookings'])
        ];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error in get_specialist_statistics: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load statistics'
    ]);
}
?>