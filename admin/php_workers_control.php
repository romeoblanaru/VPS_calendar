<?php
// Ensure we always return JSON no matter what
ob_start();
header('Content-Type: application/json');

// Include sudo control helper
require_once 'php_workers_sudo_control.php';

try {
    session_start();
    
    // Check if user is logged in (admin area already requires login)
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Unauthorized - Please log in'
        ]);
        exit;
    }
    
    // Check action
    if (!isset($_POST['action']) || $_POST['action'] !== 'control') {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    
    $worker = $_POST['worker'] ?? '';
    $command = $_POST['command'] ?? '';
    $sudoPassword = $_POST['sudo_password'] ?? '';
    
    // Validate inputs
    $validWorkers = ['booking-event', 'google-calendar', 'sms'];
    $validCommands = ['start', 'stop', 'restart', 'start-systemd'];
    
    if (!in_array($worker, $validWorkers)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid worker: ' . $worker]);
        exit;
    }
    
    if (!in_array($command, $validCommands)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid command: ' . $command]);
        exit;
    }
    
    // Worker configurations
    $workers = [
        'booking-event' => [
            'service' => 'booking-event-worker',
            'script' => '/srv/project_1/calendar/workers/booking_event_worker.php',
            'name' => 'Booking Event Worker'
        ],
        'google-calendar' => [
            'service' => 'google-calendar-worker',
            'script' => '/srv/project_1/calendar/process_google_calendar_queue_enhanced.php',
            'name' => 'Google Calendar Worker',
            'args' => '--signal-loop'
        ],
        'sms' => [
            'service' => 'sms-worker',
            'script' => '/srv/project_1/calendar/workers/sms_worker.php',
            'name' => 'SMS Worker'
        ]
    ];
    
    $workerInfo = $workers[$worker];
    $response = ['success' => false];
    
    // Use the control script with sudo support if password provided
    if (!empty($sudoPassword)) {
        $controlScript = '/srv/project_1/calendar/workers/control_workers_with_sudo.sh';
    } else {
        $controlScript = '/srv/project_1/calendar/workers/control_workers.sh';
    }
    
    // Execute commands
    switch ($command) {
        case 'start-systemd':
            // Start as systemd service (requires sudo)
            if (!empty($sudoPassword)) {
                try {
                    $sudoResult = controlServiceWithSudo($workerInfo['service'], 'start', $sudoPassword);
                    if ($sudoResult['success']) {
                        $output = "Started {$workerInfo['service']} as systemd service";
                        $response['success'] = true;
                        // Don't show SSH output on success
                    } else {
                        // Clean up the error message
                        $error_msg = $sudoResult['error'] ?: $sudoResult['output'];
                        // Remove sudo prompt from error message
                        $error_msg = preg_replace('/\[sudo\] password for.*?:/', '', $error_msg);
                        $output = "Failed to start systemd service: " . trim($error_msg);
                        $response['success'] = false;
                    }
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = "Error: " . $e->getMessage();
                    ob_clean();
                    echo json_encode($response);
                    exit;
                }
            } else {
                $response['success'] = false;
                $response['message'] = "Sudo password required to start systemd service";
                ob_clean();
                echo json_encode($response);
                exit;
            }
            
            sleep(2);
            $status = trim(shell_exec("$controlScript $worker status 2>&1") ?? '');
            $isRunning = (strpos($status, 'active') === 0);
            
            if (isset($output)) {
                $response['message'] = $output;
            }
            $response['is_running'] = $isRunning;
            $response['status_type'] = $status;
            break;
            
        case 'start':
            // If sudo password provided, use sudo directly for systemd
            if (!empty($sudoPassword)) {
                $sudoResult = controlServiceWithSudo($workerInfo['service'], 'start', $sudoPassword);
                if ($sudoResult['success']) {
                    $output = "Started {$workerInfo['service']} via systemd with sudo";
                } else {
                    $output = "Failed to start via sudo: " . ($sudoResult['error'] ?: $sudoResult['output']);
                }
            } else {
                $output = shell_exec("$controlScript $worker start 2>&1");
            }
            sleep(2);
            
            // Check status
            $status = trim(shell_exec("$controlScript $worker status 2>&1") ?? '');
            $isRunning = (strpos($status, 'active') === 0);
            
            $response['success'] = $isRunning;
            $response['message'] = $isRunning ? "Started {$workerInfo['name']}" : "Failed to start {$workerInfo['name']}";
            
            if (!$isRunning) {
                $response['debug'] = [
                    'output' => $output,
                    'status' => $status,
                    'worker' => $worker
                ];
            }
            break;
            
        case 'stop':
            // If sudo password provided and it's a systemd service, use sudo directly
            if (!empty($sudoPassword)) {
                $sudoResult = controlServiceWithSudo($workerInfo['service'], 'stop', $sudoPassword);
                if ($sudoResult['success']) {
                    $output = "Stopped {$workerInfo['service']} via systemd with sudo";
                } else {
                    $output = "Failed to stop via sudo: " . ($sudoResult['error'] ?: $sudoResult['output']);
                }
                sleep(1);
            } else {
                $output = shell_exec("$controlScript $worker stop 2>&1");
                sleep(3); // Give more time for systemd to restart
            }
            
            // Check status AFTER waiting
            $status = trim(shell_exec("$controlScript $worker status 2>&1") ?? '');
            $isStopped = (strpos($status, 'active') !== 0);
            
            // Check if systemd warning was issued
            $systemdWarning = (strpos($output, 'sudo systemctl') !== false);
            
            // For systemd services, we need to check more carefully
            $isSystemdService = (strpos($status, 'systemd') !== false);
            
            // Only include debug info if there's an issue
            if (!$isStopped && !$sudoPassword) {
                $response['debug'] = [
                    'output' => $output,
                    'status' => $status,
                    'systemd' => $isSystemdService
                ];
            }
            
            // If sudo password was provided and it worked
            if (!empty($sudoPassword) && $isStopped) {
                $response['success'] = true;
                $response['message'] = "Successfully stopped {$workerInfo['name']} via systemd";
            } 
            // If it's a systemd service and we don't have sudo password, it will fail
            elseif ($isSystemdService && empty($sudoPassword)) {
                $response['success'] = false;
                $response['message'] = "Warning: Process killed but systemd auto-restarted it. Use: sudo systemctl stop {$workerInfo['service']}";
            } elseif ($isStopped && !$systemdWarning) {
                $response['success'] = true;
                $response['message'] = "Stopped {$workerInfo['name']}";
            } else {
                $response['success'] = false;
                
                // If it's still running or we got a systemd warning
                if ($systemdWarning || $isSystemdService) {
                    // Use the warning message that contains "sudo systemctl"
                    $response['message'] = trim($output);
                    if (!strpos($response['message'], 'sudo systemctl')) {
                        $response['message'] = "Warning: Process killed but systemd auto-restarted it. Use: sudo systemctl stop {$workerInfo['service']}";
                    }
                } else {
                    $response['message'] = "Failed to stop {$workerInfo['name']}";
                }
            }
            
            if (!$isStopped) {
                $response['debug'] = [
                    'output' => $output,
                    'status' => $status,
                    'worker' => $worker
                ];
            }
            break;
            
        case 'restart':
            if (!empty($sudoPassword)) {
                $output = shell_exec("$controlScript $worker restart " . escapeshellarg($sudoPassword) . " 2>&1");
            } else {
                $output = shell_exec("$controlScript $worker restart 2>&1");
            }
            sleep(2);
            
            // Check status
            $status = trim(shell_exec("$controlScript $worker status 2>&1") ?? '');
            $isRunning = (strpos($status, 'active') === 0);
            
            $response['success'] = $isRunning;
            $response['message'] = $isRunning ? "Restarted {$workerInfo['name']}" : "Failed to restart {$workerInfo['name']}";
            
            if (!$isRunning) {
                $response['debug'] = [
                    'output' => $output,
                    'status' => $status,
                    'worker' => $worker
                ];
            }
            break;
    }
    
    // Check new status using control script
    $status = trim(shell_exec("$controlScript $worker status 2>&1") ?? '');
    $response['is_running'] = (strpos($status, 'active') === 0);
    $response['status_type'] = $status; // Will be "active:systemd", "active:direct", or "inactive"
    
    ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
exit;
?>