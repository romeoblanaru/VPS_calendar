<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['workpoint_user', 'organisation_user', 'admin_user'])) {
    http_response_code(403);
    exit('Access denied');
}

if (!isset($_GET['workpoint_id']) || empty($_GET['workpoint_id'])) {
    http_response_code(400);
    exit('Workpoint ID is required');
}

$workpoint_id = intval($_GET['workpoint_id']);
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

try {
    // Get workpoint name
    $stmt = $pdo->prepare("SELECT name_of_the_place FROM working_points WHERE unic_id = ?");
    $stmt->execute([$workpoint_id]);
    $workpoint = $stmt->fetch(PDO::FETCH_ASSOC);
    $workpoint_name = $workpoint ? $workpoint['name_of_the_place'] : 'Unknown';

    // Gather all statistics data
    $today = date('Y-m-d');
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');

    // Get specialists statistics
    $stmt = $pdo->prepare("
        SELECT
            s.name as specialist_name,
            s.speciality,
            COUNT(DISTINCT ws.service_id) as service_count,
            COUNT(DISTINCT b.unic_id) as total_bookings,
            COUNT(DISTINCT CASE WHEN DATE(b.booking_start_datetime) >= CURDATE() THEN b.unic_id END) as future_bookings
        FROM specialists s
        LEFT JOIN working_point_services ws ON ws.id_specialist = s.unic_id AND ws.deleted = 0
        LEFT JOIN booking b ON b.id_specialist = s.unic_id
        WHERE s.id_work_point = ?
        AND s.deleted = 0
        GROUP BY s.unic_id, s.name, s.speciality
        ORDER BY s.name
    ");
    $stmt->execute([$workpoint_id]);
    $specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get services statistics
    $stmt = $pdo->prepare("
        SELECT
            ws.name_of_service,
            ws.duration,
            ws.price_of_service,
            s.name as specialist_name,
            COUNT(b.unic_id) as booking_count
        FROM working_point_services ws
        LEFT JOIN specialists s ON ws.id_specialist = s.unic_id
        LEFT JOIN booking b ON b.service_id = ws.service_id
        WHERE ws.id_work_point = ?
        AND ws.deleted = 0
        GROUP BY ws.service_id, ws.name_of_service, ws.duration, ws.price_of_service, s.name
        ORDER BY booking_count DESC
    ");
    $stmt->execute([$workpoint_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get booking summary
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_bookings,
            COUNT(CASE WHEN DATE(booking_start_datetime) = ? THEN 1 END) as today_bookings,
            COUNT(CASE WHEN DATE(booking_start_datetime) BETWEEN ? AND ? THEN 1 END) as month_bookings,
            SUM(CASE WHEN ws.price_of_service IS NOT NULL THEN ws.price_of_service ELSE 0 END) as total_revenue
        FROM booking b
        LEFT JOIN working_point_services ws ON b.service_id = ws.service_id
        WHERE b.id_work_place = ?
    ");
    $stmt->execute([$today, $month_start, $month_end, $workpoint_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($format === 'csv') {
        // Export as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="statistics_' . $workpoint_id . '_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Write header info
        fputcsv($output, ['Workpoint Statistics Report']);
        fputcsv($output, ['Workpoint:', $workpoint_name]);
        fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
        fputcsv($output, []);

        // Booking Summary
        fputcsv($output, ['BOOKING SUMMARY']);
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Bookings', $summary['total_bookings']]);
        fputcsv($output, ['Today Bookings', $summary['today_bookings']]);
        fputcsv($output, ['This Month Bookings', $summary['month_bookings']]);
        fputcsv($output, ['Total Revenue', '€' . number_format($summary['total_revenue'], 2)]);
        fputcsv($output, []);

        // Specialists
        fputcsv($output, ['SPECIALISTS']);
        fputcsv($output, ['Name', 'Speciality', 'Services', 'Total Bookings', 'Future Bookings']);
        foreach ($specialists as $specialist) {
            fputcsv($output, [
                $specialist['specialist_name'],
                $specialist['speciality'] ?: 'Not specified',
                $specialist['service_count'],
                $specialist['total_bookings'],
                $specialist['future_bookings']
            ]);
        }
        fputcsv($output, []);

        // Services
        fputcsv($output, ['SERVICES']);
        fputcsv($output, ['Service Name', 'Duration (min)', 'Price (€)', 'Specialist', 'Total Bookings']);
        foreach ($services as $service) {
            fputcsv($output, [
                $service['name_of_service'],
                $service['duration'],
                $service['price_of_service'],
                $service['specialist_name'] ?: 'Unassigned',
                $service['booking_count']
            ]);
        }

        fclose($output);
    } else {
        // For PDF format, return a simple HTML that can be printed
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Statistics Report - <?= htmlspecialchars($workpoint_name) ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; }
                h2 { color: #666; margin-top: 30px; }
                table { border-collapse: collapse; width: 100%; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .summary { background-color: #f9f9f9; padding: 15px; margin-bottom: 20px; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <h1>Workpoint Statistics Report</h1>
            <div class="summary">
                <p><strong>Workpoint:</strong> <?= htmlspecialchars($workpoint_name) ?></p>
                <p><strong>Generated:</strong> <?= date('Y-m-d H:i:s') ?></p>
            </div>

            <h2>Booking Summary</h2>
            <table>
                <tr><th>Metric</th><th>Value</th></tr>
                <tr><td>Total Bookings</td><td><?= $summary['total_bookings'] ?></td></tr>
                <tr><td>Today's Bookings</td><td><?= $summary['today_bookings'] ?></td></tr>
                <tr><td>This Month's Bookings</td><td><?= $summary['month_bookings'] ?></td></tr>
                <tr><td>Total Revenue</td><td>€<?= number_format($summary['total_revenue'], 2) ?></td></tr>
            </table>

            <h2>Specialists</h2>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Speciality</th>
                    <th>Services</th>
                    <th>Total Bookings</th>
                    <th>Future Bookings</th>
                </tr>
                <?php foreach ($specialists as $specialist): ?>
                <tr>
                    <td><?= htmlspecialchars($specialist['specialist_name']) ?></td>
                    <td><?= htmlspecialchars($specialist['speciality'] ?: 'Not specified') ?></td>
                    <td><?= $specialist['service_count'] ?></td>
                    <td><?= $specialist['total_bookings'] ?></td>
                    <td><?= $specialist['future_bookings'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h2>Services</h2>
            <table>
                <tr>
                    <th>Service Name</th>
                    <th>Duration (min)</th>
                    <th>Price (€)</th>
                    <th>Specialist</th>
                    <th>Total Bookings</th>
                </tr>
                <?php foreach ($services as $service): ?>
                <tr>
                    <td><?= htmlspecialchars($service['name_of_service']) ?></td>
                    <td><?= $service['duration'] ?></td>
                    <td><?= $service['price_of_service'] ?></td>
                    <td><?= htmlspecialchars($service['specialist_name'] ?: 'Unassigned') ?></td>
                    <td><?= $service['booking_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <div class="no-print" style="margin-top: 30px;">
                <button onclick="window.print()">Print/Save as PDF</button>
                <button onclick="window.close()">Close</button>
            </div>

            <script>
                // Auto-open print dialog
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>
        <?php
    }

} catch (PDOException $e) {
    error_log("Database error in export_statistics: " . $e->getMessage());
    http_response_code(500);
    exit('Database error occurred');
}
?>