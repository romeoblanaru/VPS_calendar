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
    // Check if queue table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'google_calendar_sync_queue'");
    if ($stmt->rowCount() == 0) {
        echo '<div class="alert alert-info">Queue table not created yet. It will be created when first booking is made.</div>';
        exit;
    }
    
    // Get recent queue items
    $stmt = $pdo->query("
        SELECT q.*, s.name as specialist_name 
        FROM google_calendar_sync_queue q
        LEFT JOIN specialists s ON q.specialist_id = s.unic_id
        ORDER BY q.created_at DESC 
        LIMIT 20
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Queue is empty - all syncs up to date!</div>';
        exit;
    }
    
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Booking ID</th>';
    echo '<th>Specialist</th>';
    echo '<th>Action</th>';
    echo '<th>Status</th>';
    echo '<th>Created</th>';
    echo '<th>Processed</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($items as $item) {
        $status_class = '';
        switch ($item['status']) {
            case 'pending':
                $status_class = 'warning';
                break;
            case 'processing':
                $status_class = 'info';
                break;
            case 'completed':
                $status_class = 'success';
                break;
            case 'failed':
                $status_class = 'danger';
                break;
        }
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['id']) . '</td>';
        echo '<td>' . htmlspecialchars($item['booking_id'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($item['specialist_name'] ?? 'ID: ' . $item['specialist_id']) . '</td>';
        echo '<td><span class="badge bg-secondary">' . htmlspecialchars($item['action']) . '</span></td>';
        echo '<td><span class="badge bg-' . $status_class . '">' . htmlspecialchars($item['status']) . '</span></td>';
        echo '<td><small>' . htmlspecialchars($item['created_at']) . '</small></td>';
        echo '<td><small>' . htmlspecialchars($item['processed_at'] ?? 'Not processed') . '</small></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    // Add summary
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM google_calendar_sync_queue GROUP BY status");
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($status_counts)) {
        echo '<div class="mt-3">';
        echo '<h6>Queue Summary:</h6>';
        echo '<div class="d-flex gap-3">';
        foreach ($status_counts as $status) {
            $class = '';
            switch ($status['status']) {
                case 'pending':
                    $class = 'warning';
                    break;
                case 'processing':
                    $class = 'info';
                    break;
                case 'completed':
                    $class = 'success';
                    break;
                case 'failed':
                    $class = 'danger';
                    break;
            }
            echo '<span class="badge bg-' . $class . '">' . $status['status'] . ': ' . $status['count'] . '</span>';
        }
        echo '</div>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading queue: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?> 