<?php
session_start();
// Check both possible session variable names
if ((!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) || $_SESSION['role'] !== 'admin_user') {
    http_response_code(403);
    exit('Unauthorized - Access denied');
}

require_once '../includes/db.php';

// This file is for AJAX calls only. Redirect if accessed directly.
if (!isset($_POST['action']) || empty($_POST['action'])) {
    header('Location: admin_dashboard.php');
    exit('This page handles AJAX requests only.');
}

header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_worker_status':
            echo json_encode(getWorkerStatus());
            exit;
            
        case 'control_worker':
            $worker = $_POST['worker'] ?? '';
            $command = $_POST['command'] ?? '';
            echo json_encode(controlWorker($worker, $command));
            exit;
            
        case 'get_queue_stats':
            echo json_encode(getQueueStats($pdo));
            exit;
            
        case 'get_logs':
            $worker = $_POST['worker'] ?? '';
            $lines = (int)($_POST['lines'] ?? 50);
            echo json_encode(getWorkerLogs($worker, $lines));
            exit;
            
        case 'clear_queue':
            $queue = $_POST['queue'] ?? '';
            echo json_encode(clearQueue($pdo, $queue));
            exit;
    }
}

function getWorkerStatus() {
    try {
        $workers = [
            'booking-event-worker' => [
                'name' => 'Booking Event Worker',
                'description' => 'Real-time UI updates via nchan/SSE',
                'service' => 'booking-event-worker',
                'process' => 'booking_event_worker.php'
            ],
            'google-calendar-worker' => [
                'name' => 'Google Calendar Worker',
                'description' => 'Near real-time Google Calendar sync',
                'service' => 'google-calendar-worker',
                'process' => 'process_google_calendar_queue_enhanced.php'
            ],
            'sms-worker' => [
                'name' => 'SMS Worker',
                'description' => 'SMS notifications for booking changes',
                'service' => 'sms-worker',
                'process' => 'sms_worker.php'
            ]
        ];
    
    foreach ($workers as $key => &$worker) {
        // Check systemd service status
        $serviceStatus = @shell_exec("systemctl is-active {$worker['service']} 2>&1");
        $worker['systemd_status'] = ($serviceStatus && trim($serviceStatus) === 'active') ? 'active' : 'inactive';
        
        // Check if process is running
        $psCommand = "ps aux | grep '{$worker['process']}' | grep -v grep | wc -l";
        $processCount = (int)trim(@shell_exec($psCommand) ?? '0');
        $worker['process_running'] = $processCount > 0;
        
        // Get service details
        if ($worker['systemd_status'] === 'active') {
            $details = shell_exec("systemctl status {$worker['service']} --no-pager -n 0 2>&1");
            if (preg_match('/Main PID: (\d+)/', $details, $matches)) {
                $worker['pid'] = $matches[1];
            }
            if (preg_match('/Active: active \(running\) since (.+?);/', $details, $matches)) {
                $worker['since'] = $matches[1];
            }
        }
        
        // Get last log entry time
        $logFile = "/srv/project_1/calendar/logs/{$key}.log";
        if (file_exists($logFile)) {
            $worker['last_log'] = date('Y-m-d H:i:s', filemtime($logFile));
        }
    }
    
        return $workers;
    } catch (Exception $e) {
        error_log('PHP Workers Dashboard Error: ' . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

function controlWorker($worker, $command) {
    $allowedWorkers = ['booking-event', 'google-calendar', 'sms'];
    $allowedCommands = ['start', 'stop', 'restart', 'start-systemd'];
    
    // Map worker key to actual names
    $workerMap = [
        'booking-event' => [
            'service' => 'booking-event-worker',
            'process' => 'booking_event_worker.php',
            'script' => 'workers/booking_event_worker.php',
            'log' => 'logs/booking-event-worker.log'
        ],
        'google-calendar' => [
            'service' => 'google-calendar-worker',
            'process' => 'process_google_calendar_queue_enhanced.php',
            'script' => 'process_google_calendar_queue_enhanced.php',
            'log' => 'logs/google-calendar-worker.log'
        ],
        'sms' => [
            'service' => 'sms-worker',
            'process' => 'sms_worker.php',
            'script' => 'workers/sms_worker.php',
            'log' => 'workers/logs/sms_worker.log'
        ]
    ];
    
    if (!in_array($worker, $allowedWorkers) || !in_array($command, $allowedCommands)) {
        return ['success' => false, 'message' => 'Invalid worker or command'];
    }
    
    $workerInfo = $workerMap[$worker];
    
    if ($command === 'start-systemd') {
        // Use systemctl
        $cmd = "sudo systemctl start {$workerInfo['service']} 2>&1";
        $output = shell_exec($cmd);
        sleep(2);
        $newStatus = trim(shell_exec("systemctl is-active {$workerInfo['service']} 2>&1"));
    } else if ($command === 'start') {
        // Start as regular process
        $logFile = "/srv/project_1/calendar/{$workerInfo['log']}";
        $scriptPath = "/srv/project_1/calendar/{$workerInfo['script']}";
        
        // Make sure log directory exists
        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        touch($logFile);
        chmod($logFile, 0666);
        
        $cmd = "cd /srv/project_1/calendar && nohup php {$workerInfo['script']} >> {$workerInfo['log']} 2>&1 & echo $!";
        $pid = trim(shell_exec($cmd));
        $output = "Started with PID: $pid";
        $newStatus = is_numeric($pid) ? 'active:process' : 'failed';
    } else if ($command === 'stop') {
        // First try systemctl
        $systemctlActive = trim(shell_exec("systemctl is-active {$workerInfo['service']} 2>&1"));
        if ($systemctlActive === 'active') {
            $cmd = "sudo systemctl stop {$workerInfo['service']} 2>&1";
            $output = shell_exec($cmd);
        } else {
            // Kill process - try multiple methods
            // First, try to get the PID
            $pidCmd = "pgrep -f '{$workerInfo['process']}' | head -1";
            $pid = trim(shell_exec($pidCmd));
            
            if ($pid && is_numeric($pid)) {
                // Try graceful kill first
                $killCmd = "kill $pid 2>&1";
                $killOutput = shell_exec($killCmd);
                sleep(2);
                
                // Check if still running
                if (shell_exec("ps -p $pid > /dev/null 2>&1 && echo 'running'")) {
                    // Force kill
                    shell_exec("kill -9 $pid 2>&1");
                    $output = "Process killed forcefully (PID: $pid)";
                } else {
                    $output = "Process stopped gracefully (PID: $pid)";
                }
            } else {
                // Fallback to pkill
                $cmd = "pkill -f '{$workerInfo['process']}' 2>&1";
                $pkillOutput = shell_exec($cmd);
                
                // Also try with full path
                $cmd2 = "pkill -f '{$workerInfo['script']}' 2>&1";
                shell_exec($cmd2);
                
                $output = "Process kill attempted using pkill";
            }
        }
        sleep(2);
        
        // Verify it's actually stopped
        $checkCmd = "pgrep -f '{$workerInfo['process']}' 2>&1";
        $stillRunning = trim(shell_exec($checkCmd));
        $newStatus = empty($stillRunning) ? 'inactive' : 'failed-to-stop';
    } else if ($command === 'restart') {
        // Stop first
        $systemctlActive = trim(shell_exec("systemctl is-active {$workerInfo['service']} 2>&1"));
        if ($systemctlActive === 'active') {
            shell_exec("sudo systemctl stop {$workerInfo['service']} 2>&1");
            sleep(2);
            $cmd = "sudo systemctl start {$workerInfo['service']} 2>&1";
            $output = shell_exec($cmd);
        } else {
            shell_exec("pkill -f '{$workerInfo['process']}' 2>&1");
            sleep(1);
            $logFile = "/srv/project_1/calendar/{$workerInfo['log']}";
            $cmd = "cd /srv/project_1/calendar && nohup php {$workerInfo['script']} >> {$workerInfo['log']} 2>&1 & echo $!";
            $pid = trim(shell_exec($cmd));
            $output = "Restarted with PID: $pid";
        }
        $newStatus = 'active';
    }
    
    return [
        'success' => true,
        'message' => "Worker $command executed",
        'new_status' => $newStatus,
        'output' => $output
    ];
}

function getQueueStats($pdo) {
    $stats = [];
    
    // Booking event queue stats
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(processed = FALSE) as pending,
                SUM(processed = TRUE) as processed
            FROM booking_event_queue
        ");
        $stats['booking_events'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stats['booking_events'] = ['error' => $e->getMessage()];
    }
    
    // Google Calendar queue stats
    try {
        $stmt = $pdo->query("
            SELECT 
                status,
                COUNT(*) as count,
                MAX(created_at) as last_created,
                MIN(created_at) as oldest
            FROM google_calendar_sync_queue
            GROUP BY status
        ");
        $stats['google_calendar'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get signal stats
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(processed = FALSE) as pending,
                MAX(created_at) as last_signal
            FROM gcal_worker_signals
        ");
        $stats['gcal_signals'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stats['google_calendar'] = ['error' => $e->getMessage()];
    }
    
    // SMS queue stats
    try {
        $stmt = $pdo->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM booking_sms_queue
            GROUP BY status
        ");
        $smsStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $smsStats = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        foreach ($smsStatuses as $stat) {
            $smsStats[$stat['status']] = $stat['count'];
        }
        $stats['sms'] = $smsStats;
        
        // Today's SMS count
        $stmt = $pdo->query("
            SELECT COUNT(*) as total_today 
            FROM booking_sms_queue 
            WHERE created_at >= CURDATE()
        ");
        $todayResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['sms_today'] = $todayResult['total_today'] ?? 0;
    } catch (Exception $e) {
        $stats['sms'] = ['error' => $e->getMessage()];
    }
    
    return $stats;
}

function getWorkerLogs($worker, $lines = 50) {
    $logFiles = [
        'booking-event-worker' => '/srv/project_1/calendar/logs/booking-event-worker.log',
        'google-calendar-worker' => '/srv/project_1/calendar/logs/google-calendar-worker.log',
        'sms-worker' => '/srv/project_1/calendar/workers/logs/sms_worker.log'
    ];
    
    if (!isset($logFiles[$worker])) {
        return ['error' => 'Invalid worker'];
    }
    
    $logFile = $logFiles[$worker];
    if (!file_exists($logFile)) {
        return ['error' => 'Log file not found'];
    }
    
    // Get last N lines
    $output = shell_exec("tail -n $lines " . escapeshellarg($logFile) . " 2>&1");
    
    return [
        'worker' => $worker,
        'file' => $logFile,
        'lines' => $lines,
        'content' => $output
    ];
}

function clearQueue($pdo, $queue) {
    try {
        switch ($queue) {
            case 'booking_events':
                $pdo->exec("DELETE FROM booking_event_queue WHERE processed = TRUE");
                return ['success' => true, 'message' => 'Cleared processed booking events'];
                
            case 'gcal_pending':
                $pdo->exec("DELETE FROM google_calendar_sync_queue WHERE status = 'done'");
                return ['success' => true, 'message' => 'Cleared completed Google Calendar sync items'];
                
            case 'gcal_signals':
                $pdo->exec("DELETE FROM gcal_worker_signals WHERE processed = TRUE");
                return ['success' => true, 'message' => 'Cleared processed signals'];
                
            case 'sms_completed':
                $pdo->exec("DELETE FROM booking_sms_queue WHERE status = 'completed' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
                return ['success' => true, 'message' => 'Cleared completed SMS items older than 7 days'];
                
            default:
                return ['success' => false, 'message' => 'Invalid queue'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>