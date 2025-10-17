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
    echo '<div class="row">';
    
    // Google Calendar Credentials Table
    echo '<div class="col-md-6">';
    echo '<h6><i class="fas fa-key"></i> Google Calendar Credentials</h6>';
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'google_calendar_credentials'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT id, specialist_id, specialist_name, calendar_id, calendar_name, status, created_at, updated_at 
            FROM google_calendar_credentials 
            ORDER BY updated_at DESC 
            LIMIT 25
        ");
        $credentials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($credentials)) {
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>Specialist</th>';
            echo '<th>Calendar</th>';
            echo '<th>Status</th>';
            echo '<th>Updated</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($credentials as $cred) {
                $status_class = '';
                switch ($cred['status']) {
                    case 'active':
                        $status_class = 'success';
                        break;
                    case 'pending':
                        $status_class = 'warning';
                        break;
                    case 'disabled':
                        $status_class = 'secondary';
                        break;
                    case 'error':
                        $status_class = 'danger';
                        break;
                }
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($cred['id']) . '</td>';
                echo '<td title="ID: ' . htmlspecialchars($cred['specialist_id']) . '">' . htmlspecialchars($cred['specialist_name']) . '</td>';
                echo '<td><small>' . htmlspecialchars($cred['calendar_name'] ?? $cred['calendar_id'] ?? 'N/A') . '</small></td>';
                echo '<td><span class="badge bg-' . $status_class . '">' . htmlspecialchars($cred['status']) . '</span></td>';
                echo '<td><small>' . htmlspecialchars($cred['updated_at']) . '</small></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-info">No credentials found.</div>';
        }
    } else {
        echo '<div class="alert alert-warning">google_calendar_credentials table not found.</div>';
    }
    
    echo '</div>';
    
    // Google Calendar Sync Queue Table
    echo '<div class="col-md-6">';
    echo '<h6><i class="fas fa-list"></i> Sync Queue (Last 25)</h6>';
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'google_calendar_sync_queue'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT id, booking_id, specialist_id, event_type, status, created_at, processed_at 
            FROM google_calendar_sync_queue 
            ORDER BY created_at DESC 
            LIMIT 25
        ");
        $queue_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($queue_items)) {
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>Booking</th>';
            echo '<th>Specialist</th>';
            echo '<th>Event Type</th>';
            echo '<th>Status</th>';
            echo '<th>Created</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($queue_items as $item) {
                $status_class = '';
                switch ($item['status']) {
                    case 'pending':
                        $status_class = 'warning';
                        break;
                    case 'processing':
                        $status_class = 'info';
                        break;
                    case 'done':
                        $status_class = 'success';
                        break;
                    case 'failed':
                        $status_class = 'danger';
                        break;
                }
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($item['id']) . '</td>';
                echo '<td><small>' . htmlspecialchars($item['booking_id'] ?? 'N/A') . '</small></td>';
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
                echo '<td><span class="badge bg-' . $status_class . '">' . htmlspecialchars($item['status']) . '</span></td>';
                echo '<td><small>' . htmlspecialchars($item['created_at']) . '</small></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-info">No queue items found.</div>';
        }
    } else {
        echo '<div class="alert alert-warning">google_calendar_sync_queue table not found.</div>';
    }
    
    echo '</div>';
    echo '</div>';
    
    // Recent Bookings with Google Calendar Status
    echo '<div class="mt-4">';
    echo '<h6><i class="fas fa-calendar-check"></i> Recent Bookings (Last 25) - Google Calendar Sync Status</h6>';
    
    $stmt = $pdo->query("
        SELECT b.unic_id, b.booking_start_datetime, b.client_full_name, b.id_specialist, 
               s.name as specialist_name, srv.name_of_service as service_name,
               (SELECT COUNT(*) FROM google_calendar_sync_queue q WHERE q.booking_id = b.unic_id AND q.status = 'done') as synced,
               (SELECT COUNT(*) FROM google_calendar_sync_queue q WHERE q.booking_id = b.unic_id AND q.status = 'failed') as failed,
               (SELECT COUNT(*) FROM google_calendar_sync_queue q WHERE q.booking_id = b.unic_id AND q.status = 'pending') as pending
        FROM booking b
        LEFT JOIN specialists s ON b.id_specialist = s.unic_id
        LEFT JOIN services srv ON b.service_id = srv.unic_id
        WHERE b.booking_start_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY b.booking_start_datetime DESC
        LIMIT 25
    ");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($bookings)) {
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Booking ID</th>';
        echo '<th>Date/Time</th>';
        echo '<th>Customer</th>';
        echo '<th>Specialist</th>';
        echo '<th>Service</th>';
        echo '<th>Sync Status</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($bookings as $booking) {
            $sync_status = '';
            if ($booking['synced'] > 0) {
                $sync_status = '<span class="badge bg-success">Synced</span>';
            } elseif ($booking['failed'] > 0) {
                $sync_status = '<span class="badge bg-danger">Failed</span>';
            } elseif ($booking['pending'] > 0) {
                $sync_status = '<span class="badge bg-warning">Pending</span>';
            } else {
                $sync_status = '<span class="badge bg-secondary">Not Queued</span>';
            }
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($booking['unic_id']) . '</td>';
            echo '<td><small>' . htmlspecialchars($booking['booking_start_datetime']) . '</small></td>';
            echo '<td>' . htmlspecialchars($booking['client_full_name'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($booking['specialist_name'] ?? 'ID: ' . $booking['id_specialist']) . '</td>';
            echo '<td><small>' . htmlspecialchars($booking['service_name'] ?? 'N/A') . '</small></td>';
            echo '<td>' . $sync_status . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-info">No recent bookings found.</div>';
    }
    
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading database records: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?> 