<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../includes/db.php';

// Set JSON response headers
header('Content-Type: application/json');

try {
    // Get filter parameters
    $webhook_name = $_POST['webhook_name'] ?? '';
    $status = $_POST['status'] ?? '';
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    
    // Build WHERE clause (same logic as in webhook_logs.php)
    $whereConditions = [];
    $params = [];
    
    if ($webhook_name) {
        $whereConditions[] = "webhook_name = ?";
        $params[] = $webhook_name;
    }
    
    if ($status !== '') {
        $whereConditions[] = "is_successful = ?";
        $params[] = (int)$status;
    }
    
    if ($date_from) {
        $whereConditions[] = "created_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    
    if ($date_to) {
        $whereConditions[] = "created_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // First, get the count of records that will be deleted
    $countSql = "SELECT COUNT(*) as total FROM webhook_logs $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // If no filters are applied, require additional confirmation
    if (empty($whereConditions)) {
        echo json_encode([
            'success' => false, 
            'message' => 'No filters applied. To delete all records, please add at least one filter.'
        ]);
        exit();
    }
    
    // Build the DELETE query
    $deleteSql = "DELETE FROM webhook_logs $whereClause";
    $stmt = $pdo->prepare($deleteSql);
    $stmt->execute($params);
    
    $deletedCount = $stmt->rowCount();
    
    // Log the deletion action
    $logSql = "INSERT INTO logs (user, action_time, action_type, table_name, sql_query, old_data) VALUES (?, NOW(), 'DELETE', 'webhook_logs', ?, ?)";
    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute([
        $_SESSION['user_id'],
        $deleteSql,
        json_encode([
            'deleted_count' => $deletedCount,
            'filters' => [
                'webhook_name' => $webhook_name,
                'status' => $status,
                'date_from' => $date_from,
                'date_to' => $date_to
            ]
        ])
    ]);
    
    echo json_encode([
        'success' => true,
        'deleted_count' => $deletedCount,
        'message' => "Successfully deleted $deletedCount webhook log(s)"
    ]);
    
} catch (Exception $e) {
    error_log("Error clearing webhook logs: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 