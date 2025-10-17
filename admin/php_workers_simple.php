<?php
session_start();
require_once '../includes/db.php';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: text/html; charset=utf-8');
    
    if ($_GET['action'] === 'get_log' && isset($_GET['file'])) {
        $logFile = $_GET['file'];
        
        // Security check - ensure log file is within allowed paths
        $allowedPaths = [
            '/srv/project_1/calendar/logs/',
            '/srv/project_1/calendar/workers/logs/'
        ];
        
        $allowed = false;
        foreach ($allowedPaths as $path) {
            if (strpos($logFile, $path) === 0) {
                $allowed = true;
                break;
            }
        }
        
        if (!$allowed) {
            echo '<p style="color: red;">Access denied</p>';
            exit;
        }
        
        if (file_exists($logFile)) {
            $logs = shell_exec("tail -n 50 " . escapeshellarg($logFile) . " | tac 2>&1");
            echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 10px; border-radius: 4px; max-height: 400px; overflow-y: auto; font-size: 12px;">';
            echo htmlspecialchars($logs ?: 'No log content available');
            echo '</pre>';
        } else {
            echo '<p style="color: red;">Log file not found</p>';
        }
        exit;
    }
    
    if ($_GET['action'] === 'clear_log' && isset($_GET['file']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        $logFile = $_GET['file'];
        
        // Security check
        $allowedPaths = [
            '/srv/project_1/calendar/logs/',
            '/srv/project_1/calendar/workers/logs/'
        ];
        
        $allowed = false;
        foreach ($allowedPaths as $path) {
            if (strpos($logFile, $path) === 0) {
                $allowed = true;
                break;
            }
        }
        
        if (!$allowed) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        if (file_exists($logFile)) {
            // Clear the log file
            $result = file_put_contents($logFile, '');
            if ($result !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to clear log file']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Log file not found']);
        }
        exit;
    }
}

// Simple worker status check
$workers = [
    'booking-event' => [
        'name' => 'Refresh Event Worker',
        'service' => 'booking-event-worker',
        'log' => '/srv/project_1/calendar/logs/booking-event-worker.log'
    ],
    'google-calendar' => [
        'name' => 'Google Calendar Worker',
        'service' => 'google-calendar-worker',
        'log' => '/srv/project_1/calendar/logs/google-calendar-worker.log',
        'error_log' => '/srv/project_1/calendar/logs/google-calendar-worker-error.log'
    ],
    'sms' => [
        'name' => 'SMS Worker',
        'service' => 'sms-worker',
        'log' => '/srv/project_1/calendar/workers/logs/sms_worker.log'
    ]
];
?>

<style>
.worker-control-btn {
    width: 32px !important;
    height: 32px !important;
    padding: 0 !important;
    margin: 0 2px !important;
    border: none !important;
    border-radius: 50% !important;
    cursor: pointer !important;
    font-size: 14px !important;
    transition: all 0.3s !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.worker-control-btn:hover:not(:disabled) {
    transform: scale(1.1);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.worker-control-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed !important;
}
.worker-control-btn.start { background: #28a745 !important; color: white !important; }
.worker-control-btn.start-systemd { background: #0056b3 !important; color: white !important; }
.worker-control-btn.stop { background: #dc3545 !important; color: white !important; }
.worker-control-btn.restart { background: #ffc107 !important; color: #212529 !important; }
.info-box {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 15px;
}
.warning-box {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 15px;
    border-radius: 5px;
}
.dropdown-btn {
    transition: all 0.3s ease;
}
.dropdown-btn:hover {
    background: #f0f0f0 !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<!-- Define the simple function first -->
<script>
function showWorkerContent(worker, type) {
    // Show the info box
    document.getElementById('worker-info-box').style.display = 'block';
    
    // Get content from hidden divs
    var content = document.getElementById(worker + '-' + type + '-content');
    if (content) {
        // Display in the main content area
        document.getElementById('worker-info-content').innerHTML = content.innerHTML;
    }
}
</script>

<!-- Define functions immediately -->
<script>
// Define functions globally as soon as possible
if (typeof window.showWorkerInfo === 'undefined') {
    window.showWorkerInfo = function(worker, type) {
        const infoBox = document.getElementById('worker-info-box');
        const contentDiv = document.getElementById('worker-info-content');
        
        // Show the info box
        infoBox.style.display = 'block';
        
        // Load content based on type
        if (type === 'logs') {
            contentDiv.innerHTML = `
                <h3>${worker === 'booking-event' ? 'Booking Event Worker' : 'Google Calendar Worker'} - Logs</h3>
                <div style="margin-bottom: 10px;">
                    <select id="${worker}-log-lines" style="padding: 5px;">
                        <option value="20">Last 20 lines</option>
                        <option value="50" selected>Last 50 lines</option>
                        <option value="100">Last 100 lines</option>
                    </select>
                    <button onclick="window.loadWorkerLogs('${worker}')" style="padding: 5px 10px; margin-left: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-sync"></i> Load Logs
                    </button>
                </div>
                <pre id="${worker}-log-content" style="background: #1e1e1e; color: #d4d4d4; padding: 10px; border-radius: 4px; max-height: 400px; overflow-y: auto; font-size: 12px; white-space: pre-wrap;">Click Load Logs to view...</pre>
            `;
            // Auto-load logs
            window.loadWorkerLogs(worker);
        } else if (type === 'queue') {
            contentDiv.innerHTML = `
                <h3>${worker === 'booking-event' ? 'Booking Event Worker' : 'Google Calendar Worker'} - Queue Statistics</h3>
                <div id="${worker}-queue-content">Loading...</div>
            `;
            // Load queue stats
            window.refreshQueueStats(worker);
        } else if (type === 'debug') {
            let debugContent = `<h3>${worker === 'booking-event' ? 'Booking Event Worker' : 'Google Calendar Worker'} - Debug Tools</h3>`;
            
            if (worker === 'booking-event') {
                debugContent += `
                    <p><strong>Force process queue:</strong></p>
                    <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px; margin: 10px 0;">
                        cd /srv/project_1/calendar && php workers/booking_event_worker.php --once
                    </code>
                    <button onclick="if(confirm('Clear processed booking events?')) window.clearQueue('booking_event_queue')" style="margin-top: 10px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-trash"></i> Clear Processed Events
                    </button>
                `;
            } else {
                debugContent += `
                    <p><strong>Force process queue:</strong></p>
                    <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px; margin: 10px 0;">
                        cd /srv/project_1/calendar && php process_google_calendar_queue_enhanced.php --manual
                    </code>
                    <button onclick="if(confirm('Clear completed sync items?')) window.clearQueue('google_calendar_sync_queue')" style="margin-top: 10px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-trash"></i> Clear Completed Items
                    </button>
                `;
            } else if (worker === 'sms') {
                debugContent += `
                    <p><strong>Force process queue:</strong></p>
                    <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px; margin: 10px 0;">
                        cd /srv/project_1/calendar && php workers/sms_worker.php --once
                    </code>
                    <button onclick="if(confirm('Clear completed SMS items older than 7 days?')) window.clearQueue('booking_sms_queue')" style="margin-top: 10px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-trash"></i> Clear Old Completed Items
                    </button>
                `;
            }
            
            debugContent += `
                <p style="margin-top: 20px;"><strong>Check process:</strong></p>
                <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px;">
                    ps aux | grep "${worker === 'booking-event' ? 'booking_event_worker' : (worker === 'google-calendar' ? 'process_google_calendar_queue' : 'sms_worker')}"
                </code>
            `;
            
            contentDiv.innerHTML = debugContent;
        }
    };
}

if (typeof window.loadWorkerLogs === 'undefined') {
    window.loadWorkerLogs = function(worker) {
        const lines = document.getElementById(worker + '-log-lines').value;
        const contentDiv = document.getElementById(worker + '-log-content');
        
        contentDiv.textContent = 'Loading logs...';
        
        // Use AJAX to fetch logs
        fetch('php_workers_dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_logs&worker=${worker}-worker&lines=${lines}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                contentDiv.textContent = 'Error: ' + data.error;
            } else {
                contentDiv.textContent = data.content || 'No log content available';
            }
        })
        .catch(error => {
            contentDiv.textContent = 'Error loading logs: ' + error.message;
        });
    };
}

if (typeof window.refreshQueueStats === 'undefined') {
    window.refreshQueueStats = function(worker) {
        const contentDiv = document.getElementById(worker + '-queue-content');
        if (!contentDiv) {
            console.error('Queue content div not found for worker:', worker);
            return;
        }
        
        contentDiv.innerHTML = '<p>Loading queue statistics...</p>';
        
        // Use AJAX to fetch updated stats
        fetch('php_workers_dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_queue_stats'
        })
        .then(response => response.json())
        .then(data => {
            let html = '<div style="padding: 10px;">';
            
            if (worker === 'booking-event' && data.booking_events) {
                html += '<div style="background: #e8f4f8; padding: 15px; border-radius: 4px; margin-bottom: 10px;">';
                html += `<p style="margin: 5px 0;"><strong>Total Events:</strong> ${data.booking_events.total || 0}</p>`;
                html += `<p style="margin: 5px 0;"><strong>Pending:</strong> <span style="color: #ff6b00;">${data.booking_events.pending || 0}</span></p>`;
                html += `<p style="margin: 5px 0;"><strong>Processed:</strong> <span style="color: #28a745;">${data.booking_events.processed || 0}</span></p>`;
                html += '</div>';
            } else if (worker === 'google-calendar') {
                if (data.google_calendar && Array.isArray(data.google_calendar)) {
                    html += '<div style="background: #e8f4f8; padding: 15px; border-radius: 4px; margin-bottom: 10px;">';
                    html += '<h4 style="margin-top: 0;">Sync Queue Status</h4>';
                    data.google_calendar.forEach(stat => {
                        const color = stat.status === 'pending' ? '#ff6b00' : (stat.status === 'done' ? '#28a745' : '#dc3545');
                        html += `<p style="margin: 5px 0;"><strong>${stat.status.charAt(0).toUpperCase() + stat.status.slice(1)}:</strong> <span style="color: ${color};">${stat.count}</span></p>`;
                    });
                    html += '</div>';
                }
                
                if (data.gcal_signals) {
                    html += '<div style="background: #fff3cd; padding: 15px; border-radius: 4px;">';
                    html += '<h4 style="margin-top: 0;">Signal Status</h4>';
                    html += `<p style="margin: 5px 0;"><strong>Total Signals:</strong> ${data.gcal_signals.total || 0}</p>`;
                    html += `<p style="margin: 5px 0;"><strong>Pending Signals:</strong> <span style="color: #ff6b00;">${data.gcal_signals.pending || 0}</span></p>`;
                    html += '</div>';
                }
            } else if (worker === 'sms' && data.sms) {
                html += '<div style="background: #e8f4f8; padding: 15px; border-radius: 4px; margin-bottom: 10px;">';
                html += '<h4 style="margin-top: 0;">SMS Queue Status</h4>';
                const smsStats = data.sms || {};
                html += `<p style="margin: 5px 0;"><strong>Pending:</strong> <span style="color: #ff6b00;">${smsStats.pending || 0}</span></p>`;
                html += `<p style="margin: 5px 0;"><strong>Processing:</strong> <span style="color: #007bff;">${smsStats.processing || 0}</span></p>`;
                html += `<p style="margin: 5px 0;"><strong>Completed:</strong> <span style="color: #28a745;">${smsStats.completed || 0}</span></p>`;
                html += `<p style="margin: 5px 0;"><strong>Failed:</strong> <span style="color: #dc3545;">${smsStats.failed || 0}</span></p>`;
                html += '</div>';
                
                if (data.sms_today) {
                    html += '<div style="background: #fff3cd; padding: 15px; border-radius: 4px;">';
                    html += '<h4 style="margin-top: 0;">Today\'s Activity</h4>';
                    html += `<p style="margin: 5px 0;"><strong>SMS Sent Today:</strong> ${data.sms_today || 0}</p>`;
                    html += '</div>';
                }
            }
            
            html += '</div>';
            html += `<button onclick="window.refreshQueueStats('${worker}')" style="margin-top: 10px; padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">`;
            html += '<i class="fas fa-sync"></i> Refresh</button>';
            
            contentDiv.innerHTML = html;
        })
        .catch(error => {
            contentDiv.innerHTML = `<p style="color: red;">Error loading queue stats: ${error.message}</p>`;
            console.error('Error refreshing stats:', error);
        });
    };
}

if (typeof window.controlWorkerService === 'undefined') {
    window.controlWorkerService = function(worker, action) {
        const button = event.target;
        const originalText = button.innerHTML;
        const statusElement = button.closest('.worker-card')?.querySelector('.worker-status');
        
        button.disabled = true;
        button.innerHTML = '<span style="font-size: 0.8em;">Wait...</span>';
        
        // Show status as updating
        if (statusElement) {
            statusElement.innerHTML = '<span style="color: #007bff;">Updating...</span>';
        }
        
        fetch('php_workers_dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=control_worker&worker=${worker}&command=${action}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update status immediately if possible
                if (statusElement && data.new_status) {
                    const isActive = data.new_status.includes('active');
                    statusElement.innerHTML = isActive ? 
                        '<span style="color: green; font-weight: bold;">✓ Active</span>' : 
                        '<span style="color: red; font-weight: bold;">✗ Inactive</span>';
                }
                // Reload after a short delay to ensure everything is updated
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(data.message || 'Operation failed');
            }
        })
        .catch(error => {
            console.error('Worker control error:', error);
            alert('Failed to ' + action + ' worker: ' + error.message);
            
            // Restore original button state
            button.disabled = false;
            button.innerHTML = originalText;
            
            // Restore status
            if (statusElement) {
                // Try to reload just the status
                location.reload();
            }
        });
    };
}

if (typeof window.clearQueue === 'undefined') {
    window.clearQueue = function(queueTable) {
        let queue;
        if (queueTable === 'booking_event_queue') {
            queue = 'booking_events';
        } else if (queueTable === 'google_calendar_sync_queue') {
            queue = 'gcal_pending';
        } else if (queueTable === 'booking_sms_queue') {
            queue = 'sms_completed';
        } else {
            queue = queueTable;
        }
        
        fetch('php_workers_dashboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=clear_queue&queue=${queue}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                // Refresh the page or just the queue stats
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to clear queue'));
            }
        })
        .catch(error => {
            alert('Error clearing queue: ' + error.message);
        });
    };
}
</script>

<div style="padding: 20px; background: white;">
    <h2 style="color: #333; margin-bottom: 20px;">
        <i class="fas fa-cogs"></i> PHP Workers Status 
        <span style="font-weight: normal; font-size: 0.7em; color: #666;">
            (php_workers_simple.php)
        </span>
    </h2>
    
    <!-- Worker cards container -->
    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <?php foreach ($workers as $key => $worker): ?>
            <?php
            // Check worker status using multiple methods
            $processName = '';
            $serviceName = '';
            
            switch($key) {
                case 'booking-event':
                    $processName = 'booking_event_worker.php';
                    $serviceName = 'booking-event-worker';
                    break;
                case 'google-calendar':
                    $processName = 'process_google_calendar_queue_enhanced.php';
                    $serviceName = 'google-calendar-worker';
                    break;
                case 'sms':
                    $processName = 'sms_worker.php';
                    $serviceName = 'sms-worker';
                    break;
            }
            
            // Check if process is running
            $processRunning = false;
            $psOutput = shell_exec("ps aux | grep '$processName' | grep -v grep");
            if (!empty($psOutput)) {
                $processRunning = true;
            }
            
            // Check systemd status
            $systemdActive = false;
            $systemdStatus = trim(shell_exec("systemctl is-active $serviceName 2>&1"));
            if ($systemdStatus === 'active') {
                $systemdActive = true;
            }
            
            $isActive = $processRunning || $systemdActive;
            $isSystemd = $systemdActive;
            
            $logExists = file_exists($worker['log']);
            $lastLog = $logExists ? date('Y-m-d H:i:s', filemtime($worker['log'])) : 'N/A';
            ?>
            
            <div class="worker-card" style="flex: 1; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background: #f8f9fa; position: relative;">
                <div style="position: absolute; top: 15px; right: 15px;">
                    <?php if (!$isActive): ?>
                        <!-- When inactive, show both start options -->
                        <button onclick="window.controlWorkerService('<?php echo $key; ?>', 'start-systemd')" 
                                class="worker-control-btn start-systemd"
                                title="Start as Systemd Service (requires sudo)">
                            ▶
                        </button>
                        <button onclick="window.controlWorkerService('<?php echo $key; ?>', 'start')" 
                                class="worker-control-btn start"
                                title="Start as Normal Process">
                            ▶
                        </button>
                    <?php else: ?>
                        <!-- When active, only show stop -->
                        <button onclick="window.controlWorkerService('<?php echo $key; ?>', 'stop')" 
                                class="worker-control-btn stop"
                                title="Stop Worker">
                            ■
                        </button>
                        <?php if (!$isSystemd): ?>
                        <!-- Only show restart for non-systemd processes -->
                        <button onclick="window.controlWorkerService('<?php echo $key; ?>', 'restart')" 
                                class="worker-control-btn restart"
                                title="Restart Worker">
                            ↻
                        </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <h3 style="margin: 0 0 10px 0;">
                    <?php echo htmlspecialchars($worker['name']); ?>
                </h3>
                <p style="margin: 5px 0;">Service: <code><?php echo htmlspecialchars($worker['service']); ?></code></p>
                <p style="margin: 5px 0;">Status: <span class="worker-status" style="color: <?php echo $isActive ? 'green' : 'red'; ?>; font-weight: bold;">
                    <?php echo $isActive ? '✓ Active' : '✗ Inactive'; ?>
                    <?php if ($isActive): ?>
                        <?php if ($isSystemd): ?>
                            <span style="color: #0056b3; font-size: 0.9em;">(systemd)</span>
                        <?php else: ?>
                            <span style="color: #ff8c42; font-size: 0.9em; font-style: italic; font-weight: normal;">(simple process)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
                </p>
                
                <!-- Dropdown buttons -->
                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <button onclick="(function() {
                        window.currentWorkerKey = '<?php echo $key; ?>';
                        window.currentLogFile = <?php 
                            if ($key === 'google-calendar' && isset($worker['error_log']) && file_exists($worker['error_log'])) {
                                echo "'" . $worker['error_log'] . "'";
                            } else {
                                echo "'" . $worker['log'] . "'";
                            }
                        ?>;
                        document.getElementById('worker-info-box').style.display = 'block';
                        document.getElementById('worker-info-content').innerHTML = document.getElementById('<?php echo $key; ?>-logs-content').innerHTML;
                        document.getElementById('refresh-log-btn').style.display = 'inline-block';
                        document.getElementById('clear-log-btn').style.display = 'inline-block';
                    })()" style="padding: 5px 10px; background: white; color: #666; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-file-alt"></i> Logs ▼
                    </button>
                    <button onclick="(function() {
                        window.currentWorkerKey = null;
                        window.currentLogFile = null;
                        document.getElementById('worker-info-box').style.display = 'block';
                        document.getElementById('worker-info-content').innerHTML = document.getElementById('<?php echo $key; ?>-queue-content').innerHTML;
                        document.getElementById('refresh-log-btn').style.display = 'none';
                        document.getElementById('clear-log-btn').style.display = 'none';
                    })()" style="padding: 5px 10px; background: white; color: #666; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-list"></i> Queue Stats ▼
                    </button>
                    <button onclick="(function() {
                        window.currentWorkerKey = null;
                        window.currentLogFile = null;
                        document.getElementById('worker-info-box').style.display = 'block';
                        document.getElementById('worker-info-content').innerHTML = document.getElementById('<?php echo $key; ?>-debug-content').innerHTML;
                        document.getElementById('refresh-log-btn').style.display = 'none';
                        document.getElementById('clear-log-btn').style.display = 'none';
                    })()" style="padding: 5px 10px; background: white; color: #666; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-bug"></i> Debug ▼
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Single info box that shows content based on button clicks -->
    <div id="worker-info-box" style="display: none; padding: 20px; background: #f1f1f1; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
        <div style="float: right; display: flex; gap: 5px;">
            <button id="refresh-log-btn" onclick="(function() {
                if (!window.currentWorkerKey || !window.currentLogFile) return;
                const contentDiv = document.getElementById('worker-info-content');
                const originalContent = contentDiv.innerHTML;
                contentDiv.innerHTML = '<div style=\'text-align: center; padding: 20px;\'><i class=\'fas fa-spinner fa-spin\'></i> Refreshing log...</div>';
                
                fetch('php_workers_simple.php?action=get_log&file=' + encodeURIComponent(window.currentLogFile))
                    .then(response => response.text())
                    .then(data => {
                        contentDiv.innerHTML = '<h3>' + window.currentWorkerKey.charAt(0).toUpperCase() + window.currentWorkerKey.slice(1).replace(/-/g, ' ') + ' Worker - Recent Logs (Newest First)</h3>' + data;
                    })
                    .catch(error => {
                        console.error('Error refreshing log:', error);
                        contentDiv.innerHTML = originalContent;
                        alert('Failed to refresh log');
                    });
            })()" style="background: #17a2b8; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; display: none;">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button id="clear-log-btn" onclick="(function() {
                if (!window.currentLogFile) return;
                
                if (confirm('Are you sure you want to clear this log file? This action cannot be undone.')) {
                    fetch('php_workers_simple.php?action=clear_log&file=' + encodeURIComponent(window.currentLogFile), {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Refresh the log display by clicking refresh
                            document.getElementById('refresh-log-btn').click();
                            alert('Log file cleared successfully');
                        } else {
                            alert('Failed to clear log: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error clearing log:', error);
                        alert('Failed to clear log');
                    });
                }
            })()" style="background: #ffc107; color: #212529; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; display: none;">
                <i class="fas fa-broom"></i> Clear
            </button>
            <button onclick="document.getElementById('worker-info-box').style.display='none'" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        <div id="worker-info-content">
            <!-- Content will be loaded here -->
        </div>
    </div>
    
    <!-- Hidden content templates for each worker -->
    <?php foreach ($workers as $key => $worker): ?>
        <!-- Logs content -->
        <div id="<?php echo $key; ?>-logs-content" style="display: none;">
            <h3><?php echo htmlspecialchars($worker['name']); ?> - Recent Logs (Newest First)</h3>
            <?php
            // For Google Calendar worker, check error log first as it contains the actual logs
            if ($key === 'google-calendar' && isset($worker['error_log']) && file_exists($worker['error_log'])) {
                $logFile = $worker['error_log'];
                echo '<p style="color: #ffc107; margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Showing enhanced logs from error log file</p>';
            } else {
                $logFile = $worker['log'];
            }
            
            if (file_exists($logFile)) {
                // Get logs and reverse the order to show newest first
                $logs = shell_exec("tail -n 50 " . escapeshellarg($logFile) . " | tac 2>&1");
                echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 10px; border-radius: 4px; max-height: 400px; overflow-y: auto; font-size: 12px;">';
                echo htmlspecialchars($logs ?: 'No log content available');
                echo '</pre>';
            } else {
                echo '<p style="color: red;">Log file not found: ' . htmlspecialchars($logFile) . '</p>';
            }
            ?>
        </div>
        
        <!-- Queue Stats content -->
        <div id="<?php echo $key; ?>-queue-content" style="display: none;">
            <h3><?php echo htmlspecialchars($worker['name']); ?> - Queue Statistics</h3>
            <?php if ($key == 'booking-event'): ?>
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(processed = 0) as pending FROM booking_event_queue");
                    $stats = $stmt->fetch();
                    ?>
                    <div style="background: #e8f4f8; padding: 15px; border-radius: 4px;">
                        <p><strong>Total Events:</strong> <?php echo $stats['total'] ?? 0; ?></p>
                        <p><strong>Pending:</strong> <span style="color: #ff6b00;"><?php echo $stats['pending'] ?? 0; ?></span></p>
                        <p><strong>Processed:</strong> <span style="color: #28a745;"><?php echo ($stats['total'] - $stats['pending']) ?? 0; ?></span></p>
                    </div>
                <?php } catch (Exception $e) { ?>
                    <p style="color: red;">Error loading stats: <?php echo htmlspecialchars($e->getMessage()); ?></p>
                <?php } ?>
            <?php elseif ($key == 'sms'): ?>
                <?php
                try {
                    // Get SMS queue stats
                    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM booking_sms_queue GROUP BY status");
                    $stats = $stmt->fetchAll();
                    ?>
                    <div style="background: #e8f4f8; padding: 15px; border-radius: 4px; margin-bottom: 10px;">
                        <h4>SMS Queue Status</h4>
                        <?php 
                        $statusCounts = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
                        foreach ($stats as $stat) {
                            $statusCounts[$stat['status']] = $stat['count'];
                        }
                        ?>
                        <p><strong>Pending:</strong> <span style="color: #ff6b00;"><?php echo $statusCounts['pending']; ?></span></p>
                        <p><strong>Processing:</strong> <span style="color: #007bff;"><?php echo $statusCounts['processing']; ?></span></p>
                        <p><strong>Completed:</strong> <span style="color: #28a745;"><?php echo $statusCounts['completed']; ?></span></p>
                        <p><strong>Failed:</strong> <span style="color: #dc3545;"><?php echo $statusCounts['failed']; ?></span></p>
                    </div>
                    
                    <?php
                    // Get recent SMS activity
                    $stmt = $pdo->query("SELECT COUNT(*) as total_today FROM booking_sms_queue WHERE created_at >= CURDATE()");
                    $todayStats = $stmt->fetch();
                    ?>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 4px;">
                        <h4>Today's Activity</h4>
                        <p><strong>SMS Sent Today:</strong> <?php echo $todayStats['total_today'] ?? 0; ?></p>
                    </div>
                <?php } catch (Exception $e) { ?>
                    <p style="color: red;">Error loading stats: <?php echo htmlspecialchars($e->getMessage()); ?></p>
                <?php } ?>
            <?php else: ?>
                <?php
                try {
                    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM google_calendar_sync_queue GROUP BY status");
                    $stats = $stmt->fetchAll();
                    ?>
                    <div style="background: #e8f4f8; padding: 15px; border-radius: 4px; margin-bottom: 10px;">
                        <h4>Sync Queue Status</h4>
                        <?php foreach ($stats as $stat): ?>
                            <?php $color = $stat['status'] === 'pending' ? '#ff6b00' : ($stat['status'] === 'done' ? '#28a745' : '#dc3545'); ?>
                            <p><strong><?php echo ucfirst($stat['status']); ?>:</strong> <span style="color: <?php echo $color; ?>;"><?php echo $stat['count']; ?></span></p>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php
                    // Signals
                    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(processed = 0) as pending FROM gcal_worker_signals");
                    $signals = $stmt->fetch();
                    ?>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 4px;">
                        <h4>Signal Status</h4>
                        <p><strong>Total Signals:</strong> <?php echo $signals['total'] ?? 0; ?></p>
                        <p><strong>Pending Signals:</strong> <span style="color: #ff6b00;"><?php echo $signals['pending'] ?? 0; ?></span></p>
                    </div>
                <?php } catch (Exception $e) { ?>
                    <p style="color: red;">Error loading stats: <?php echo htmlspecialchars($e->getMessage()); ?></p>
                <?php } ?>
            <?php endif; ?>
        </div>
        
        <!-- Debug content -->
        <div id="<?php echo $key; ?>-debug-content" style="display: none;">
            <h3><?php echo htmlspecialchars($worker['name']); ?> - Debug Tools</h3>
            <?php if ($key == 'booking-event'): ?>
                <p><strong>Force process queue:</strong></p>
                <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px; margin: 10px 0;">
                    cd /srv/project_1/calendar && php workers/booking_event_worker.php --once
                </code>
                <p style="margin-top: 20px;"><strong>Clear processed events:</strong></p>
                <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px;">
                    mysql -u root -p calendar -e "DELETE FROM booking_event_queue WHERE processed = 1"
                </code>
            <?php elseif ($key == 'sms'): ?>
                <p><strong>Force process queue:</strong></p>
                <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px; margin: 10px 0;">
                    cd /srv/project_1/calendar && php workers/sms_worker.php --once
                </code>
                <p style="margin-top: 20px;"><strong>Clear completed SMS items:</strong></p>
                <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px;">
                    mysql -u root -p calendar -e "DELETE FROM booking_sms_queue WHERE status = 'completed' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
                </code>
            <?php else: ?>
                <p><strong>Force process queue:</strong></p>
                <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px; margin: 10px 0;">
                    cd /srv/project_1/calendar && php process_google_calendar_queue_enhanced.php --manual
                </code>
                <p style="margin-top: 20px;"><strong>Clear completed sync items:</strong></p>
                <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px;">
                    mysql -u root -p calendar -e "DELETE FROM google_calendar_sync_queue WHERE status = 'done'"
                </code>
            <?php endif; ?>
            <p style="margin-top: 20px;"><strong>Check process:</strong></p>
            <code style="display: block; background: #333; color: #fff; padding: 10px; border-radius: 4px;">
                ps aux | grep "<?php echo $key == 'booking-event' ? 'booking_event_worker' : ($key == 'google-calendar' ? 'process_google_calendar_queue' : 'sms_worker'); ?>"
            </code>
        </div>
    <?php endforeach; ?>
    
    <div style="margin-top: 30px; padding: 15px; background: #d1ecf1; border-radius: 5px;">
        <h4>Manual Commands</h4>
        <p>To install/start workers as services:</p>
        <code>sudo /srv/project_1/calendar/workers/install_workers.sh</code>
        
        <p style="margin-top: 10px;">Manual start/stop commands:</p>
        <code># Start workers</code><br>
        <code>/srv/project_1/calendar/start_workers.sh booking-event</code><br>
        <code>/srv/project_1/calendar/start_workers.sh google-calendar</code><br>
        <code>/srv/project_1/calendar/start_workers.sh sms</code><br><br>
        <code># Stop workers</code><br>
        <code>pkill -f booking_event_worker.php</code><br>
        <code>pkill -f process_google_calendar_queue_enhanced.php</code><br>
        <code>pkill -f sms_worker.php</code>
        
        <p style="margin-top: 10px;">To check service status:</p>
        <code>sudo systemctl status booking-event-worker</code><br>
        <code>sudo systemctl status google-calendar-worker</code><br>
        <code>sudo systemctl status sms-worker</code>
        
        <p style="margin-top: 10px;">To view logs:</p>
        <code>tail -f /srv/project_1/calendar/logs/booking-event-worker.log</code><br>
        <code>tail -f /srv/project_1/calendar/logs/google-calendar-worker.log</code><br>
        <code>tail -f /srv/project_1/calendar/workers/logs/sms_worker.log</code>
    </div>
    
    <div style="margin-top: 30px;">
        <h3>PHP Workers Documentation</h3>
        
        <div style="margin-bottom: 30px;">
            <h4>Overview</h4>
            <p>The calendar system uses two background workers to handle different aspects of the booking system:</p>
        </div>
        
        <div style="margin-bottom: 30px;">
            <h4><i class="fas fa-sync"></i> Booking Event Worker</h4>
            <div class="info-box">
                <p><strong>Purpose:</strong> Provides real-time UI updates when bookings change</p>
                <p><strong>Technology:</strong> Uses nchan (nginx module) for Server-Sent Events (SSE)</p>
                <p><strong>Trigger:</strong> MySQL triggers on booking table (INSERT, UPDATE, DELETE)</p>
                <p><strong>Speed:</strong> Instant (< 1 second)</p>
                <p><strong>What it does:</strong></p>
                <ul>
                    <li>Monitors <code>booking_event_queue</code> table</li>
                    <li>Publishes events to nchan channels</li>
                    <li>Enables auto-refresh on booking view pages</li>
                    <li>Works for specialist, workpoint, and admin views</li>
                </ul>
            </div>
        </div>
        
        <div style="margin-bottom: 30px;">
            <h4><i class="fas fa-calendar"></i> Google Calendar Worker</h4>
            <div class="info-box">
                <p><strong>Purpose:</strong> Syncs bookings with Google Calendar</p>
                <p><strong>Technology:</strong> Google Calendar API with OAuth2</p>
                <p><strong>Trigger:</strong> MySQL triggers + PHP function calls</p>
                <p><strong>Speed:</strong> Near real-time (3-5 seconds with signals, 2 minutes fallback)</p>
                <p><strong>What it does:</strong></p>
                <ul>
                    <li>Monitors <code>google_calendar_sync_queue</code> table</li>
                    <li>Creates, updates, and deletes Google Calendar events</li>
                    <li>Handles OAuth token refresh automatically</li>
                    <li>Uses signal system for faster processing</li>
                </ul>
            </div>
        </div>
        
        <div style="margin-bottom: 30px;">
            <h4><i class="fas fa-sms"></i> SMS Worker</h4>
            <div class="info-box">
                <p><strong>Purpose:</strong> Sends SMS notifications for booking changes</p>
                <p><strong>Technology:</strong> Bot1 SMS API with IP-based routing</p>
                <p><strong>Trigger:</strong> MySQL triggers on booking table (INSERT, UPDATE, DELETE)</p>
                <p><strong>Speed:</strong> Near real-time (5-10 seconds)</p>
                <p><strong>What it does:</strong></p>
                <ul>
                    <li>Monitors <code>booking_sms_queue</code> table</li>
                    <li>Checks channel exclusion settings</li>
                    <li>Processes customizable SMS templates</li>
                    <li>Routes SMS through Bot1 API</li>
                    <li>Respects force_sms overrides from UI</li>
                </ul>
            </div>
        </div>
        
        <div style="margin-bottom: 30px;">
            <h4>Architecture</h4>
            <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
Database Change (booking table)
    ├── MySQL Trigger: booking_after_insert/update/delete
    │   └── Inserts to: booking_event_queue
    │       └── Booking Event Worker → nchan → Browser (SSE)
    │
    ├── MySQL Trigger: booking_gcal_after_insert/update/delete
    │   └── Inserts to: google_calendar_sync_queue + gcal_worker_signals
    │       └── Google Calendar Worker → Google API → Google Calendar
    │
    └── MySQL Trigger: booking_after_insert/update_sms/before_delete_sms
        └── Inserts to: booking_sms_queue
            └── SMS Worker → Bot1 API → SMS Gateway → Client Phone
            </pre>
        </div>
        
        <div style="margin-bottom: 30px;">
            <h4>Troubleshooting</h4>
            <div class="warning-box">
                <p><strong>Worker not starting?</strong></p>
                <ul>
                    <li>Check logs in <code>/srv/project_1/calendar/logs/</code></li>
                    <li>Ensure MySQL is running: <code>sudo systemctl status mysql</code></li>
                    <li>Check file permissions: workers should run as www-data</li>
                </ul>
                
                <p style="margin-top: 15px;"><strong>Events not syncing?</strong></p>
                <ul>
                    <li>Check queue tables in database</li>
                    <li>Verify specialist has Google Calendar connected</li>
                    <li>Look for failed items in sync queue</li>
                    <li>Check Google API quotas</li>
                </ul>
            </div>
        </div>
        
        <div style="margin-bottom: 30px;">
            <h4>Maintenance</h4>
            <p>The system automatically cleans up old queue entries:</p>
            <ul>
                <li>Booking events: Deleted after 1 hour when processed</li>
                <li>Google Calendar sync: Kept for 7 days (success) or 30 days (failed)</li>
                <li>Signals: Deleted after 24 hours when processed</li>
            </ul>
        </div>
    </div>
</div>

<script>
// Global variables to track current log - just declare them, no functions needed
window.currentWorkerKey = null;
window.currentLogFile = null;

// Auto-refresh every 15 seconds only if on workers page
setInterval(() => {
    if (typeof loadBottomPanel === 'function') {
        // We're in the admin dashboard, check if we're on PHP Workers page
        const content = document.getElementById('bottom_panel_content');
        if (content && content.innerHTML.includes('PHP Workers Status')) {
            loadBottomPanel('php_workers');
        }
    }
}, 15000);

// Log that functions are loaded
console.log('PHP Workers functions loaded:', {
    toggleDropdown: typeof window.toggleDropdown,
    loadWorkerLogs: typeof window.loadWorkerLogs,
    refreshQueueStats: typeof window.refreshQueueStats,
    clearQueue: typeof window.clearQueue,
    controlWorkerService: typeof window.controlWorkerService
});
</script>