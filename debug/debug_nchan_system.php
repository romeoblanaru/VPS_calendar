<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/nchan_publisher.php';

if (!isset($_SESSION['user'])) {
    die("Please log in first");
}

$specialist_id = $_SESSION['specialist_id'] ?? 2;
?>
<!DOCTYPE html>
<html>
<head>
    <title>nchan Debug Console</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .section { margin: 20px 0; padding: 10px; border: 1px solid #ccc; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        #log { height: 300px; overflow-y: scroll; border: 1px solid #999; padding: 10px; background: #f5f5f5; }
        button { padding: 10px 20px; margin: 5px; }
    </style>
</head>
<body>
    <h1>nchan Real-time Debug Console</h1>
    
    <div class="section">
        <h2>1. Connection Info</h2>
        <p>Logged in as: <?= htmlspecialchars($_SESSION['user']) ?></p>
        <p>Specialist ID: <?= $specialist_id ?></p>
        <p>SSE URL: <code id="sse-url"></code></p>
        <p>Status: <span id="connection-status" class="error">Not connected</span></p>
    </div>

    <div class="section">
        <h2>2. Test Publishing</h2>
        <button onclick="testPublish()">Publish Test Event</button>
        <button onclick="createTestBooking()">Create Test Booking</button>
        <div id="publish-result"></div>
    </div>

    <div class="section">
        <h2>3. Real-time Event Log</h2>
        <div id="log"></div>
        <button onclick="clearLog()">Clear Log</button>
    </div>

    <script>
    const specialistId = <?= json_encode($specialist_id) ?>;
    const sseUrl = '/realtime/events/specialist/' + specialistId;
    document.getElementById('sse-url').textContent = window.location.origin + sseUrl;
    
    let eventSource = null;
    
    function log(message, type = 'info') {
        const logDiv = document.getElementById('log');
        const time = new Date().toLocaleTimeString();
        const entry = document.createElement('div');
        entry.className = type;
        entry.textContent = `[${time}] ${message}`;
        logDiv.appendChild(entry);
        logDiv.scrollTop = logDiv.scrollHeight;
    }
    
    function updateStatus(text, className) {
        const status = document.getElementById('connection-status');
        status.textContent = text;
        status.className = className;
    }
    
    // Connect to SSE
    function connectSSE() {
        log('Connecting to SSE endpoint: ' + sseUrl);
        
        try {
            eventSource = new EventSource(sseUrl);
            
            eventSource.onopen = function() {
                log('SSE connection opened', 'success');
                updateStatus('Connected', 'success');
            };
            
            eventSource.onerror = function(error) {
                log('SSE error occurred', 'error');
                console.error('SSE Error:', error);
                updateStatus('Error/Disconnected', 'error');
            };
            
            eventSource.onmessage = function(event) {
                log('Default message received: ' + event.data);
                try {
                    const data = JSON.parse(event.data);
                    log('Parsed data: ' + JSON.stringify(data, null, 2));
                } catch (e) {
                    log('Raw data: ' + event.data);
                }
            };
            
            // Listen for specific event types
            eventSource.addEventListener('connected', function(event) {
                log('Connected event: ' + event.data, 'success');
            });
            
            eventSource.addEventListener('booking_update', function(event) {
                log('🔔 BOOKING UPDATE: ' + event.data, 'success');
                try {
                    const data = JSON.parse(event.data);
                    alert('Booking update received! Type: ' + data.type);
                } catch (e) {
                    log('Error parsing booking update: ' + e.message, 'error');
                }
            });
            
            eventSource.addEventListener('heartbeat', function(event) {
                log('💓 Heartbeat: ' + event.data);
            });
            
        } catch (error) {
            log('Failed to create EventSource: ' + error.message, 'error');
            updateStatus('Failed', 'error');
        }
    }
    
    // Test publish
    async function testPublish() {
        log('Testing publish to nchan...');
        
        try {
            const response = await fetch('debug_nchan_publish_test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ specialist_id: specialistId })
            });
            
            const result = await response.text();
            document.getElementById('publish-result').innerHTML = result;
            log('Publish test completed - check result above');
            
        } catch (error) {
            log('Publish test failed: ' + error.message, 'error');
        }
    }
    
    // Create test booking
    async function createTestBooking() {
        log('Creating test booking...');
        
        try {
            const response = await fetch('process_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'add_booking',
                    client: 'nchan Debug Test',
                    client_phone_nr: '+1234567890',
                    date: '<?= date('Y-m-d', strtotime('+1 day')) ?>',
                    time: '14:00',
                    specialist_id: specialistId,
                    service_id: 1,
                    workpoint_id: 1
                })
            });
            
            const result = await response.json();
            log('Booking creation result: ' + JSON.stringify(result), result.success ? 'success' : 'error');
            
        } catch (error) {
            log('Failed to create booking: ' + error.message, 'error');
        }
    }
    
    function clearLog() {
        document.getElementById('log').innerHTML = '';
        log('Log cleared');
    }
    
    // Auto-connect on load
    window.onload = function() {
        log('Page loaded, initializing...');
        connectSSE();
    };
    
    // Clean up on unload
    window.onbeforeunload = function() {
        if (eventSource) {
            eventSource.close();
        }
    };
    </script>
</body>
</html>