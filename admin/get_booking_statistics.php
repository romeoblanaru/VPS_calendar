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
        'total_bookings' => 0,
        'avg_per_day' => 0,
        'popular_services' => [],
        'revenue' => [
            'period' => '0.00',
            'total' => '0.00'
        ],
        'avg_booking_value' => '0.00',
        'recent_trends' => [],
        'peak_hours' => []
    ];

    // Get total bookings in the period
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM booking b
        WHERE b.id_work_place = ?
        AND DATE(b.booking_start_datetime) BETWEEN ? AND ?
    ");
    $stmt->execute([$workpoint_id, $start_date, $end_date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['total_bookings'] = intval($result['count']);

    // Calculate average per day
    $days = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400 + 1);
    $response['avg_per_day'] = round($response['total_bookings'] / $days, 1);

    // Get popular services (top 5) with revenue
    $stmt = $pdo->prepare("
        SELECT
            s.unic_id,
            s.name_of_service,
            COUNT(b.unic_id) as booking_count,
            SUM(s.price_of_service) as service_revenue
        FROM booking b
        JOIN services s ON b.service_id = s.unic_id
        WHERE b.id_work_place = ?
        AND DATE(b.booking_start_datetime) BETWEEN ? AND ?
        AND s.deleted = 0
        GROUP BY s.unic_id, s.name_of_service
        ORDER BY booking_count DESC
        LIMIT 5
    ");
    $stmt->execute([$workpoint_id, $start_date, $end_date]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($services as $service) {
        $response['popular_services'][] = [
            'id' => $service['unic_id'],
            'name' => $service['name_of_service'],
            'booking_count' => intval($service['booking_count']),
            'revenue' => number_format($service['service_revenue'] ?: 0, 2, '.', '')
        ];
    }

    // Calculate revenue for the period
    $stmt = $pdo->prepare("
        SELECT
            SUM(s.price_of_service) as total_revenue
        FROM booking b
        JOIN services s ON b.service_id = s.unic_id
        WHERE b.id_work_place = ?
        AND DATE(b.booking_start_datetime) BETWEEN ? AND ?
        AND s.deleted = 0
    ");
    $stmt->execute([$workpoint_id, $start_date, $end_date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['revenue']['period'] = number_format($result['total_revenue'] ?: 0, 2, '.', '');

    // Calculate average booking value
    if ($response['total_bookings'] > 0) {
        $response['avg_booking_value'] = number_format(($result['total_revenue'] ?: 0) / $response['total_bookings'], 2, '.', '');
    }

    // Get booking trends for the period (daily breakdown, limited to 30 days max)
    $trend_days = min(30, $days);
    $trend_start = date('Y-m-d', strtotime($end_date . " -$trend_days days"));

    $stmt = $pdo->prepare("
        SELECT
            DATE(booking_start_datetime) as booking_date,
            COUNT(*) as count
        FROM booking
        WHERE id_work_place = ?
        AND DATE(booking_start_datetime) BETWEEN ? AND ?
        GROUP BY DATE(booking_start_datetime)
        ORDER BY booking_date DESC
        LIMIT 30
    ");
    $stmt->execute([$workpoint_id, max($start_date, $trend_start), $end_date]);
    $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['recent_trends'] = [];
    foreach ($trends as $trend) {
        $response['recent_trends'][] = [
            'date' => $trend['booking_date'],
            'count' => intval($trend['count'])
        ];
    }

    // Get peak hours for the period
    $stmt = $pdo->prepare("
        SELECT
            HOUR(booking_start_datetime) as hour,
            COUNT(*) as count
        FROM booking
        WHERE id_work_place = ?
        AND DATE(booking_start_datetime) BETWEEN ? AND ?
        GROUP BY HOUR(booking_start_datetime)
        ORDER BY count DESC
        LIMIT 3
    ");
    $stmt->execute([$workpoint_id, $start_date, $end_date]);
    $peak_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['peak_hours'] = [];
    foreach ($peak_hours as $hour) {
        $response['peak_hours'][] = [
            'hour' => sprintf('%02d:00', $hour['hour']),
            'count' => intval($hour['count'])
        ];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error in get_booking_statistics: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load statistics'
    ]);
}
?>