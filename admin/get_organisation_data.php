<?php
session_start();
require_once '../includes/db.php';

// Debug: Log session data
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin_user') {
    error_log("Access denied - user_id: " . ($_SESSION['user_id'] ?? 'not set') . ", role: " . ($_SESSION['role'] ?? 'not set'));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['org_id']) || empty($_POST['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization ID is required']);
    exit;
}

$org_id = trim($_POST['org_id']);
error_log("Fetching organisation data for ID: " . $org_id);

try {
    // Fetch organization data
    $stmt = $pdo->prepare("SELECT * FROM organisations WHERE unic_id = ?");
    $stmt->execute([$org_id]);
    $organisation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Organisation data: " . print_r($organisation, true));
    
    if (!$organisation) {
        echo json_encode(['success' => false, 'message' => 'Organization not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'organisation' => $organisation
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_organisation_data.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 