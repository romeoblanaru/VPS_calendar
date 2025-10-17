<?php
session_start();
require_once '../includes/db.php';

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

$workpoint_id = trim($_GET['workpoint_id']);

try {
    // Get unique services for this workpoint (no duplication, no specialist assignment)
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            s.name_of_service,
            s.duration,
            s.price_of_service,
            s.procent_vat
        FROM services s
        WHERE s.id_work_place = ?
        ORDER BY s.name_of_service
    ");
    $stmt->execute([$workpoint_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($services)) {
        echo json_encode(['success' => false, 'message' => 'No services found for this workpoint']);
        exit;
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="services_workpoint_' . $workpoint_id . '_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header row
    fputcsv($output, ['name_of_service', 'duration', 'price_of_service', 'procent_vat']);
    
    // Write data rows
    foreach ($services as $service) {
        fputcsv($output, [
            $service['name_of_service'],
            $service['duration'],
            $service['price_of_service'],
            $service['procent_vat'] ?? '0.00'
        ]);
    }
    
    fclose($output);
    
} catch (PDOException $e) {
    error_log("Database error in download_services_csv: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 