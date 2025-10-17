<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if organisation_id is provided
if (!isset($_GET['organisation_id'])) {
    echo json_encode(['success' => false, 'error' => 'Organisation ID is required']);
    exit;
}

$organisation_id = $_GET['organisation_id'];

try {
    // Get working points for this organisation
    $stmt = $pdo->prepare("SELECT * FROM working_points WHERE organisation_id = ? ORDER BY name_of_the_place");
    $stmt->execute([$organisation_id]);
    $working_points = $stmt->fetchAll();
    
    echo json_encode($working_points);
    
} catch (Exception $e) {
    error_log("Error getting working points: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 