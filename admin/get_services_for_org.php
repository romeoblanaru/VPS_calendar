<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/session.php';

// Check session
checkSession();

header('Content-Type: application/json');

if (!isset($_GET['org_id'])) {
    echo json_encode([]);
    exit;
}

$org_id = (int)$_GET['org_id'];

try {
    $stmt = $pdo->prepare("
        SELECT unic_id, name_of_service 
        FROM services 
        WHERE organisation_id = ?
        ORDER BY name_of_service ASC
    ");
    $stmt->execute([$org_id]);
    
    $services = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $services[] = [
            'unic_id' => $row['unic_id'],
            'name_of_service' => $row['name_of_service']
        ];
    }
    
    echo json_encode($services);
    
} catch (Exception $e) {
    error_log("Error fetching services: " . $e->getMessage());
    echo json_encode([]);
}
?>