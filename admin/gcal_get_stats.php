<?php
// Session should already be started by parent, but start if not
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only include if not already included
if (!isset($_SESSION)) {
    include '../includes/session.php';
}

if (!isset($pdo)) {
    include __DIR__ . '/../includes/db.php';
}

header('Content-Type: application/json');

try {
    $stats = [];
    
    // Check if queue table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'google_calendar_sync_queue'");
    if ($stmt->rowCount() > 0) {
        // Get queue statistics
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM google_calendar_sync_queue GROUP BY status");
        $status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stats['pending'] = $status_counts['pending'] ?? 0;
        $stats['completed'] = $status_counts['completed'] ?? 0;
        $stats['failed'] = $status_counts['failed'] ?? 0;
        $stats['processing'] = $status_counts['processing'] ?? 0;
        
        // Get today's completed count
        $stmt = $pdo->query("SELECT COUNT(*) FROM google_calendar_sync_queue WHERE status = 'completed' AND DATE(processed_at) = CURDATE()");
        $stats['completed'] = $stmt->fetchColumn();
    } else {
        $stats['pending'] = 0;
        $stats['completed'] = 0;
        $stats['failed'] = 0;
        $stats['processing'] = 0;
    }
    
    // Get connected specialists count
    $stmt = $pdo->query("SHOW TABLES LIKE 'google_calendar_credentials'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM google_calendar_credentials WHERE status = 'active'");
        $stats['connected_specialists'] = $stmt->fetchColumn();
    } else {
        $stats['connected_specialists'] = 0;
    }
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 