<?php
session_start();
// Check authentication
if ((!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) || $_SESSION['role'] !== 'admin_user') {
    http_response_code(403);
    exit('Unauthorized - Access denied');
}

require_once '../includes/db.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'quick_stats':
        showQuickStats($pdo);
        break;
        
    case 'queue_monitor':
        showQueueMonitor($pdo);
        break;
        
    case 'sync_logs':
        showSyncLogs($pdo);
        break;
        
    case 'database_records':
        showDatabaseRecords($pdo);
        break;
        
    case 'system_status':
        showSystemStatus($pdo);
        break;
        
    case 'process_manually':
        processQueueManually();
        break;
        
    default:
        echo '<p style="color: red;">Invalid action</p>';
}

function showQuickStats($pdo) {
    try {
        // Get queue stats
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM google_calendar_sync_queue GROUP BY status");
        $queueStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = 0;
        $pending = 0;
        $done = 0;
        $failed = 0;
        
        foreach ($queueStats as $stat) {
            $total += $stat['count'];
            switch ($stat['status']) {
                case 'pending':
                    $pending = $stat['count'];
                    break;
                case 'done':
                    $done = $stat['count'];
                    break;
                case 'failed':
                    $failed = $stat['count'];
                    break;
            }
        }
        
        // Get signal stats
        $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(processed = 0) as pending FROM gcal_worker_signals");
        $signals = $stmt->fetch();
        
        // Get worker status
        $workerRunning = false;
        $serviceStatus = trim(shell_exec("systemctl is-active google-calendar-worker 2>&1") ?? 'unknown');
        if ($serviceStatus === 'active') {
            $workerRunning = true;
        } else {
            $processCount = (int)trim(shell_exec("ps aux | grep 'process_google_calendar_queue' | grep -v grep | wc -l") ?? '0');
            $workerRunning = $processCount > 0;
        }
        
        echo '<div class="row">';
        echo '<div class="col-6"><strong>Queue Total:</strong> ' . $total . '</div>';
        echo '<div class="col-6"><strong>Pending:</strong> <span class="badge bg-warning">' . $pending . '</span></div>';
        echo '</div>';
        echo '<div class="row mt-2">';
        echo '<div class="col-6"><strong>Completed:</strong> <span class="badge bg-success">' . $done . '</span></div>';
        echo '<div class="col-6"><strong>Failed:</strong> <span class="badge bg-danger">' . $failed . '</span></div>';
        echo '</div>';
        echo '<div class="row mt-3">';
        echo '<div class="col-6"><strong>Active Signals:</strong> <span class="badge bg-info">' . ($signals['pending'] ?? 0) . '</span></div>';
        echo '<div class="col-6"><strong>Worker:</strong> ';
        if ($workerRunning) {
            echo '<span class="badge bg-success">Running</span>';
        } else {
            echo '<span class="badge bg-danger">Stopped</span>';
        }
        echo '</div>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<p class="text-danger">Error loading statistics: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

function showQueueMonitor($pdo) {
    try {
        // Get recent queue items with specific columns
        $stmt = $pdo->query("
            SELECT 
                id,
                event_type,
                booking_id,
                specialist_id,
                status,
                attempts,
                created_at,
                error_message
            FROM google_calendar_sync_queue 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            echo '<p class="text-muted">No items in queue</p>';
            return;
        }
        
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Booking</th>';
        echo '<th>Event Type</th>';
        echo '<th>Status</th>';
        echo '<th>Created</th>';
        echo '<th>Error</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($items as $item) {
            $statusClass = '';
            switch ($item['status']) {
                case 'pending':
                    $statusClass = 'text-warning';
                    break;
                case 'done':
                    $statusClass = 'text-success';
                    break;
                case 'failed':
                    $statusClass = 'text-danger';
                    break;
            }
            
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
                    $eventTypeBadge = '<span class="badge bg-secondary">' . $item['event_type'] . '</span>';
            }
            
            echo '<tr>';
            echo '<td>' . $item['id'] . '</td>';
            echo '<td>' . ($item['booking_id'] ?? 'N/A') . '</td>';
            echo '<td>' . $eventTypeBadge . '</td>';
            echo '<td class="' . $statusClass . '">' . ucfirst($item['status']) . '</td>';
            echo '<td>' . date('H:i:s', strtotime($item['created_at'])) . '</td>';
            echo '<td>' . (empty($item['error_message']) ? '-' : '<small>' . htmlspecialchars(substr($item['error_message'], 0, 50)) . '</small>') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<p class="text-danger">Error loading queue: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

function showSyncLogs($pdo) {
    $logFile = '/srv/project_1/calendar/logs/google-calendar-worker.log';
    
    if (!file_exists($logFile)) {
        echo '<p class="text-warning">Log file not found</p>';
        return;
    }
    
    // Get last 100 lines
    $logs = shell_exec("tail -n 100 " . escapeshellarg($logFile) . " 2>&1");
    
    if ($logs) {
        // Split into lines and reverse the array to show newest first
        $lines = explode("\n", trim($logs));
        $lines = array_reverse($lines);
        $logs = implode("\n", $lines);
    }
    
    echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; max-height: 400px; overflow-y: auto; font-size: 0.875rem; border-radius: 4px; font-family: \'Consolas\', \'Monaco\', \'Courier New\', monospace;">';
    echo htmlspecialchars($logs ?: 'No log content available');
    echo '</pre>';
}

function showDatabaseRecords($pdo) {
    try {
        // Show recent sync queue records
        $stmt = $pdo->query("
            SELECT 
                id,
                event_type,
                booking_id,
                specialist_id,
                status,
                attempts,
                created_at,
                processed_at,
                error_message
            FROM google_calendar_sync_queue 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($records)) {
            echo '<p class="text-muted">No sync queue records found</p>';
            return;
        }
        
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Booking ID</th>';
        echo '<th>Specialist</th>';
        echo '<th>Event Type</th>';
        echo '<th>Status</th>';
        echo '<th>Attempts</th>';
        echo '<th>Created</th>';
        echo '<th>Processed</th>';
        echo '<th>Error</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($records as $record) {
            $statusClass = '';
            $statusBadge = '';
            
            switch ($record['status']) {
                case 'pending':
                    $statusClass = 'text-warning';
                    $statusBadge = '<span class="badge bg-warning">Pending</span>';
                    break;
                case 'done':
                    $statusClass = 'text-success';
                    $statusBadge = '<span class="badge bg-success">Done</span>';
                    break;
                case 'failed':
                    $statusClass = 'text-danger';
                    $statusBadge = '<span class="badge bg-danger">Failed</span>';
                    break;
                default:
                    $statusBadge = '<span class="badge bg-secondary">' . htmlspecialchars($record['status']) . '</span>';
            }
            
            // Event type badge
            $eventTypeBadge = '';
            switch ($record['event_type']) {
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
                    $eventTypeBadge = '<span class="badge bg-secondary">' . $record['event_type'] . '</span>';
            }
            
            echo '<tr>';
            echo '<td>' . $record['id'] . '</td>';
            echo '<td>' . ($record['booking_id'] ?? 'N/A') . '</td>';
            echo '<td>' . ($record['specialist_id'] ?? 'N/A') . '</td>';
            echo '<td>' . $eventTypeBadge . '</td>';
            echo '<td>' . $statusBadge . '</td>';
            echo '<td>' . $record['attempts'] . '</td>';
            echo '<td>' . date('Y-m-d H:i:s', strtotime($record['created_at'])) . '</td>';
            echo '<td>' . ($record['processed_at'] ? date('Y-m-d H:i:s', strtotime($record['processed_at'])) : '-') . '</td>';
            echo '<td>';
            if (!empty($record['error_message'])) {
                echo '<small class="text-danger" title="' . htmlspecialchars($record['error_message']) . '">';
                echo htmlspecialchars(substr($record['error_message'], 0, 30));
                if (strlen($record['error_message']) > 30) echo '...';
                echo '</small>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<p class="text-danger">Error loading records: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

function showSystemStatus($pdo) {
    try {
        // Check worker status
        $serviceStatus = trim(shell_exec("systemctl is-active google-calendar-worker 2>&1") ?? 'unknown');
        $processCount = (int)trim(shell_exec("ps aux | grep 'process_google_calendar_queue' | grep -v grep | wc -l") ?? '0');
        
        echo '<p><strong>Service:</strong> ';
        if ($serviceStatus === 'active') {
            echo '<span class="badge bg-success">Active</span>';
        } else {
            echo '<span class="badge bg-danger">Inactive</span>';
        }
        echo '</p>';
        
        echo '<p><strong>Process:</strong> ';
        if ($processCount > 0) {
            echo '<span class="badge bg-success">' . $processCount . ' running</span>';
        } else {
            echo '<span class="badge bg-secondary">Not running</span>';
        }
        echo '</p>';
        
        // Check last signal
        $stmt = $pdo->query("SELECT MAX(created_at) as last_signal FROM gcal_worker_signals");
        $lastSignal = $stmt->fetch();
        if ($lastSignal['last_signal']) {
            echo '<p><strong>Last Signal:</strong><br>' . date('Y-m-d H:i:s', strtotime($lastSignal['last_signal'])) . '</p>';
        }
        
    } catch (Exception $e) {
        echo '<p class="text-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

function processQueueManually() {
    header('Content-Type: application/json');
    
    try {
        // Send signal to worker
        require_once '../includes/db.php';
        global $pdo;
        
        $stmt = $pdo->prepare("INSERT INTO gcal_worker_signals (signal_type, created_at) VALUES ('manual', NOW())");
        $stmt->execute();
        
        // Try to run the script directly as well
        $output = shell_exec("cd /srv/project_1/calendar && php process_google_calendar_queue_enhanced.php --manual 2>&1");
        
        echo json_encode([
            'success' => true,
            'message' => 'Queue processing triggered manually',
            'output' => $output
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}
?>