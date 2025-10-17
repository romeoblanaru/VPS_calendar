<?php
/**
 * View Google Calendar Logs
 * Simple script to view and monitor Google Calendar operations
 */

// Parse command line options
$options = getopt('', ['follow', 'lines:', 'type:', 'booking:', 'help']);

if (isset($options['help'])) {
    echo "Google Calendar Log Viewer\n\n";
    echo "Usage:\n";
    echo "  php view_google_calendar_logs.php                    # View last 50 lines\n";
    echo "  php view_google_calendar_logs.php --lines=100        # View last 100 lines\n";
    echo "  php view_google_calendar_logs.php --follow           # Follow log in real-time\n";
    echo "  php view_google_calendar_logs.php --type=DELETE      # Filter by operation type\n";
    echo "  php view_google_calendar_logs.php --booking=123      # Filter by booking ID\n";
    exit(0);
}

$logFile = '/srv/project_1/calendar/logs/google-calendar-worker.log';
$lines = isset($options['lines']) ? (int)$options['lines'] : 50;
$follow = isset($options['follow']);
$filterType = isset($options['type']) ? strtoupper($options['type']) : null;
$filterBooking = isset($options['booking']) ? $options['booking'] : null;

if (!file_exists($logFile)) {
    echo "Log file not found: $logFile\n";
    exit(1);
}

// Color codes for different log types
$colors = [
    'OPERATION' => "\033[36m",      // Cyan
    'SUCCESS' => "\033[32m",        // Green
    'DELETE' => "\033[33m",         // Yellow
    'ERROR' => "\033[31m",          // Red
    'API_REQUEST' => "\033[34m",    // Blue
    'API_RESPONSE' => "\033[35m",   // Magenta
    'WEBHOOK' => "\033[95m",        // Light Magenta
    'QUEUE' => "\033[90m",          // Gray
    'INFO' => "\033[37m",           // White
    'STARTUP' => "\033[92m",        // Light Green
    'RESET' => "\033[0m"
];

function formatLogLine($line) {
    global $colors, $filterType, $filterBooking;
    
    // Extract the log type from [TYPE] pattern
    if (preg_match('/\[(\w+)\]/', $line, $matches)) {
        $type = $matches[1];
        
        // Apply filters
        if ($filterType && $type !== $filterType) {
            return null;
        }
        
        if ($filterBooking && !preg_match('/Booking ID: ' . preg_quote($filterBooking) . '\b/', $line)) {
            return null;
        }
        
        $color = isset($colors[$type]) ? $colors[$type] : $colors['INFO'];
        return $color . $line . $colors['RESET'];
    }
    
    return $line;
}

if ($follow) {
    // Follow mode - tail -f
    echo "Following Google Calendar logs (Ctrl+C to stop)...\n\n";
    $handle = popen("tail -f $logFile 2>&1", 'r');
    while (!feof($handle)) {
        $line = fgets($handle);
        $formatted = formatLogLine($line);
        if ($formatted !== null) {
            echo $formatted;
        }
    }
    pclose($handle);
} else {
    // Show last N lines
    $output = shell_exec("tail -n $lines $logFile 2>&1");
    $lines = explode("\n", $output);
    
    $lineCount = isset($options['lines']) ? $options['lines'] : '50';
    echo "=== Google Calendar Logs (Last $lineCount lines) ===\n\n";
    
    foreach ($lines as $line) {
        $formatted = formatLogLine($line);
        if ($formatted !== null) {
            echo $formatted . "\n";
        }
    }
    
    echo "\n=== End of logs ===\n";
    echo "Use --follow to monitor in real-time\n";
}
?>