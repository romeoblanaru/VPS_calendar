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

try {
    // Try to get logs from webhook_logs table if it exists and has Google Calendar entries
    $stmt = $pdo->query("SHOW TABLES LIKE 'webhook_logs'");
    if ($stmt->rowCount() > 0) {
        // Get Google Calendar related logs
        $stmt = $pdo->prepare("
            SELECT * FROM webhook_logs 
            WHERE (webhook_name LIKE '%google%' OR webhook_name LIKE '%calendar%' OR webhook_name LIKE '%gcal%' OR webhook_name LIKE '%sync%'
                   OR request_body LIKE '%google%' OR request_body LIKE '%calendar%' OR request_body LIKE '%gcal%' OR request_body LIKE '%sync%')
            ORDER BY created_at DESC 
            LIMIT 30
        ");
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($logs)) {
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Time</th>';
            echo '<th>Status</th>';
            echo '<th>Webhook</th>';
            echo '<th>Details</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($logs as $log) {
                $level_class = '';
                switch (strtolower($log['level'] ?? 'info')) {
                    case 'error':
                        $level_class = 'danger';
                        break;
                    case 'warning':
                        $level_class = 'warning';
                        break;
                    case 'success':
                        $level_class = 'success';
                        break;
                    default:
                        $level_class = 'info';
                        break;
                }
                
                echo '<tr>';
                echo '<td><small>' . htmlspecialchars($log['created_at']) . '</small></td>';
                echo '<td><span class="badge bg-' . ($log['is_successful'] ? 'success' : 'danger') . '">' . ($log['is_successful'] ? 'OK' : 'FAIL') . '</span></td>';
                echo '<td>' . htmlspecialchars($log['webhook_name'] ?? 'N/A') . '</td>';
                echo '<td><small>' . htmlspecialchars(substr($log['error_message'] ?? $log['request_body'] ?? '', 0, 100)) . '...</small></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-info">No Google Calendar sync logs found in webhook_logs table.</div>';
        }
    } else {
        echo '<div class="alert alert-warning">webhook_logs table not found. Logs may be stored in system error logs.</div>';
    }
    
    // Additional information about where logs might be
    echo '<div class="mt-3">';
    echo '<h6>Additional Log Sources:</h6>';
    echo '<ul>';
    echo '<li><strong>System Error Log:</strong> Check your hosting panel for PHP error logs</li>';
    echo '<li><strong>Cron Logs:</strong> <code>grep CRON /var/log/syslog</code> (if you have server access)</li>';
    echo '<li><strong>Google API Logs:</strong> Enable debug mode in the background worker for detailed API responses</li>';
    echo '</ul>';
    echo '</div>';
    
    // Show sample recent sync queue errors
    $stmt = $pdo->query("SHOW TABLES LIKE 'google_calendar_sync_queue'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT booking_id, specialist_id, event_type, error_message, created_at, processed_at 
            FROM google_calendar_sync_queue 
            WHERE status = 'failed' AND error_message IS NOT NULL
            ORDER BY processed_at DESC 
            LIMIT 10
        ");
        $failed_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($failed_items)) {
            echo '<div class="mt-4">';
            echo '<h6>Recent Sync Errors:</h6>';
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Booking ID</th>';
            echo '<th>Specialist</th>';
            echo '<th>Event Type</th>';
            echo '<th>Error</th>';
            echo '<th>Time</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($failed_items as $item) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($item['booking_id'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($item['specialist_id']) . '</td>';
                $eventTypeBadge = '';
                switch ($item['event_type']) {
                    case 'created':
                        $eventTypeBadge = '<span class="badge bg-success">Create</span>';
                        break;
                    case 'updated':
                        $eventTypeBadge = '<span class="badge bg-info">Update</span>';
                        break;
                    case 'deleted':
                        $eventTypeBadge = '<span class="badge bg-danger">Delete</span>';
                        break;
                    default:
                        $eventTypeBadge = '<span class="badge bg-secondary">' . htmlspecialchars($item['event_type']) . '</span>';
                }
                echo '<td>' . $eventTypeBadge . '</td>';
                echo '<td><small class="text-danger">' . htmlspecialchars($item['error_message'] ?? 'Unknown error') . '</small></td>';
                echo '<td><small>' . htmlspecialchars($item['processed_at'] ?? $item['created_at']) . '</small></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        }
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading logs: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?> 