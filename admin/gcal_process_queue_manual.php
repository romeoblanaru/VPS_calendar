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
    // Check if worker script exists
    $worker_script = __DIR__ . '/../process_google_calendar_queue_enhanced.php';
    if (!file_exists($worker_script)) {
        echo json_encode(['success' => false, 'message' => 'Worker script not found']);
        exit;
    }
    
    // Run the worker script manually
    $command = "php " . escapeshellarg($worker_script) . " --manual 2>&1";
    $output = [];
    $return_code = 0;
    
    exec($command, $output, $return_code);
    
    if ($return_code === 0) {
        $output_text = implode("\n", $output);
        
        // Parse the output for useful information
        $processed_count = 0;
        $success_count = 0;
        $failed_count = 0;
        
        foreach ($output as $line) {
            if (preg_match('/Processing complete: (\d+) processed, (\d+) successful, (\d+) failed/', $line, $matches)) {
                $processed_count = (int)$matches[1];
                $success_count = (int)$matches[2];
                $failed_count = (int)$matches[3];
                break;
            }
        }
        
        if ($processed_count > 0) {
            $message = "Queue processed successfully: {$processed_count} items processed, {$success_count} successful, {$failed_count} failed";
        } else {
            $message = "Queue processing completed. No items found to process.";
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'processed' => $processed_count,
            'successful' => $success_count,
            'failed' => $failed_count,
            'output' => $output_text
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Worker script failed with return code ' . $return_code,
            'output' => implode("\n", $output)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 