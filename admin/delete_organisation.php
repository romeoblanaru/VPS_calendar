<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

if (!isset($_POST['org_id']) || !is_numeric($_POST['org_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid organisation ID']);
    exit();
}

if (!isset($_POST['password']) || empty(trim($_POST['password']))) {
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit();
}

$org_id = (int)$_POST['org_id'];
$password = trim($_POST['password']);

try {
    // Verify admin password first
    $stmt = $pdo->prepare("SELECT pasword FROM super_users WHERE unic_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin || $password !== $admin['pasword']) {
        echo json_encode(['success' => false, 'error' => 'Invalid password']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // First, get the organization name for logging
    $stmt = $pdo->prepare("SELECT alias_name, oficial_company_name FROM organisations WHERE unic_id = ?");
    $stmt->execute([$org_id]);
    $org = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$org) {
        throw new Exception('Organisation not found');
    }
    
    $org_name = $org['alias_name'] . ' (' . $org['oficial_company_name'] . ')';
    
    // Get all specialists for this organization
    $stmt = $pdo->prepare("SELECT unic_id FROM specialists WHERE organisation_id = ?");
    $stmt->execute([$org_id]);
    $specialists = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all working points for this organization
    $stmt = $pdo->prepare("SELECT unic_id FROM working_points WHERE organisation_id = ?");
    $stmt->execute([$org_id]);
    $working_points = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Delete in the correct order to maintain referential integrity
    
    // 1. Delete bookings related to specialists of this organization
    if (!empty($specialists)) {
        $placeholders = str_repeat('?,', count($specialists) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM booking WHERE id_specialist IN ($placeholders)");
        $stmt->execute($specialists);
    }
    
    // 2. Delete bookings related to working points of this organization
    if (!empty($working_points)) {
        $placeholders = str_repeat('?,', count($working_points) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM booking WHERE id_work_place IN ($placeholders)");
        $stmt->execute($working_points);
    }
    
    // 3. Delete working programs for specialists
    if (!empty($specialists)) {
        $placeholders = str_repeat('?,', count($specialists) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM working_program WHERE specialist_id IN ($placeholders)");
        $stmt->execute($specialists);
    }
    
    // 4. Delete working programs for working points
    if (!empty($working_points)) {
        $placeholders = str_repeat('?,', count($working_points) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM working_program WHERE working_place_id IN ($placeholders)");
        $stmt->execute($working_points);
    }
    
    // 5. Delete services for this organization
    $stmt = $pdo->prepare("DELETE FROM services WHERE id_organisation = ?");
    $stmt->execute([$org_id]);
    
    // 6. Delete services related to specialists
    if (!empty($specialists)) {
        $placeholders = str_repeat('?,', count($specialists) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM services WHERE id_specialist IN ($placeholders)");
        $stmt->execute($specialists);
    }
    
    // 7. Delete services related to working points
    if (!empty($working_points)) {
        $placeholders = str_repeat('?,', count($working_points) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM services WHERE id_work_place IN ($placeholders)");
        $stmt->execute($working_points);
    }
    
    // 8. Delete specialists
    $stmt = $pdo->prepare("DELETE FROM specialists WHERE organisation_id = ?");
    $stmt->execute([$org_id]);
    
    // 9. Delete working points
    $stmt = $pdo->prepare("DELETE FROM working_points WHERE organisation_id = ?");
    $stmt->execute([$org_id]);
    
    // 10. Finally, delete the organization
    $stmt = $pdo->prepare("DELETE FROM organisations WHERE unic_id = ?");
    $stmt->execute([$org_id]);
    
    // Try to log the deletion (but don't fail if it doesn't work)
    try {
        $log_stmt = $pdo->prepare("INSERT INTO logs (user, action_time, action_type, table_name, record_id, sql_query, old_data) VALUES (?, NOW(), 'DELETE', 'organisations', ?, ?, ?)");
        $log_stmt->execute([
            $_SESSION['user_id'] ?? 'admin',
            $org_id,
            "DELETE FROM organisations WHERE unic_id = $org_id",
            json_encode($org)
        ]);
    } catch (Exception $log_error) {
        // Log the error but don't fail the deletion
        error_log("Failed to log organisation deletion: " . $log_error->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Organisation '$org_name' and all related data have been successfully deleted."
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Error deleting organisation $org_id: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 