<?php
/**
 * SMS Worker - Processes booking SMS notifications from queue
 * Similar to google_calendar_worker.php
 */

require_once __DIR__ . '/../includes/db.php';

// Worker configuration
$WORKER_NAME = 'sms_worker';
$BATCH_SIZE = 10;
$SLEEP_SECONDS = 5;
$MAX_ATTEMPTS = 3;
$LOCK_FILE = "/tmp/{$WORKER_NAME}.lock";
$LOG_FILE = __DIR__ . "/logs/{$WORKER_NAME}.log";

// Ensure log directory exists
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

// Logging function
function logMessage($message) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

// Create lock file
function createLock() {
    global $LOCK_FILE, $WORKER_NAME;
    
    if (file_exists($LOCK_FILE)) {
        $pid = file_get_contents($LOCK_FILE);
        // Check if process is still running
        if (file_exists("/proc/$pid")) {
            logMessage("Another instance is already running (PID: $pid)");
            exit(1);
        }
    }
    
    file_put_contents($LOCK_FILE, getmypid());
    logMessage("$WORKER_NAME started (PID: " . getmypid() . ")");
}

// Remove lock file
function removeLock() {
    global $LOCK_FILE;
    if (file_exists($LOCK_FILE)) {
        unlink($LOCK_FILE);
    }
}

// Clean shutdown
function shutdown() {
    removeLock();
    logMessage("Worker stopped");
    exit(0);
}

// Handle signals
pcntl_signal(SIGTERM, 'shutdown');
pcntl_signal(SIGINT, 'shutdown');

// Check for stop file
function checkStopFile() {
    global $WORKER_NAME;
    $stopFile = "/tmp/{$WORKER_NAME}.stop";
    if (file_exists($stopFile)) {
        logMessage("Stop file detected, shutting down gracefully");
        unlink($stopFile);
        shutdown();
    }
}

// Get SMS template and settings for working point
function getSMSSettings($pdo, $workpoint_id, $action) {
    $setting_key = 'sms_' . $action . '_template';
    $stmt = $pdo->prepare("
        SELECT setting_value, excluded_channels 
        FROM workingpoint_settings_and_attr 
        WHERE working_point_id = ? AND setting_key = ?
    ");
    $stmt->execute([$workpoint_id, $setting_key]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Process template variables
function processTemplate($template, $booking_data, $pdo) {
    // Get additional data for template variables including SMS routing fields
    $stmt = $pdo->prepare("
        SELECT
            wp.name_of_the_place as workpoint_name,
            wp.address as workpoint_address,
            wp.workplace_phone_nr as workpoint_phone,
            wp.booking_phone_nr as booking_phone,
            wp.booking_sms_number as sms_phone,
            s.name as specialist_name,
            sv.name_of_service as service_name,
            o.alias_name as organisation_alias,
            o.oficial_company_name as organisation_name
        FROM working_points wp
        LEFT JOIN specialists s ON s.unic_id = ?
        LEFT JOIN services sv ON sv.unic_id = ?
        LEFT JOIN organisations o ON o.unic_id = s.organisation_id
        WHERE wp.unic_id = ?
    ");

    $stmt->execute([
        $booking_data['id_specialist'],
        $booking_data['service_id'],
        $booking_data['id_work_place']
    ]);

    $extra_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format dates
    $booking_datetime = new DateTime($booking_data['booking_start_datetime']);
    $booking_date = $booking_datetime->format('l j F Y');
    $start_time = $booking_datetime->format('H:i');
    $end_datetime = new DateTime($booking_data['booking_end_datetime']);
    $end_time = $end_datetime->format('H:i');
    
    // Replace template variables
    $replacements = [
        '{booking_id}' => $booking_data['unic_id'],
        '{organisation_alias}' => $extra_data['organisation_alias'] ?? 'Our Clinic',
        '{workpoint_name}' => $extra_data['workpoint_name'] ?? '',
        '{workpoint_address}' => $extra_data['workpoint_address'] ?? '',
        '{service_name}' => $extra_data['service_name'] ?? '',
        '{start_time}' => $start_time,
        '{end_time}' => $end_time,
        '{booking_date}' => $booking_date,
        '{workpoint_phone}' => $extra_data['workpoint_phone'] ?? '',
        '{client_name}' => $booking_data['client_full_name'] ?? '',
        '{specialist_name}' => $extra_data['specialist_name'] ?? ''
    ];
    
    $message = str_replace(array_keys($replacements), array_values($replacements), $template);

    // Helper function to validate phone numbers (treat '0', '00', and short numbers as invalid)
    $validatePhone = function($phone) {
        if (!$phone) return null;
        // Remove all non-digit characters to check length
        $digitsOnly = preg_replace('/\D/', '', $phone);
        // If it's just '0', '00', or has less than 10 digits, treat as null
        if ($digitsOnly === '0' || $digitsOnly === '00' || strlen($digitsOnly) < 10) {
            return null;
        }
        return $phone;
    };

    // Validate each phone number, treating '0' and '00' as null
    $validated_sms_phone = $validatePhone($extra_data['sms_phone'] ?? null);
    $validated_booking_phone = $validatePhone($extra_data['booking_phone'] ?? null);
    $validated_workpoint_phone = $validatePhone($extra_data['workpoint_phone'] ?? null);

    // Return message with routing information
    return [
        'message' => $message,
        'sender_phone' => $validated_sms_phone ?? $validated_booking_phone ?? $validated_workpoint_phone ?? '+447768261021',
        'sms_phone_number' => $validated_sms_phone,
        'booking_phone_number' => $validated_booking_phone,
        'workpoint_name' => $extra_data['workpoint_name'] ?? null,
        'organisation_name' => $extra_data['organisation_name'] ?? null
    ];
}

// Send SMS via API (with shared gateway support)
function sendSMS($to, $from, $message, $sms_phone_number = null, $booking_phone_number = null, $workpoint_name = null, $organisation_name = null) {
    // Phone numbers are already cleaned when inserted into database

    $smsApiUrl = 'http://my-bookings.co.uk:8088/api/send-sms';
    $smsData = [
        'to' => $to,
        'message' => $message,
        'from' => $from
    ];

    // Add routing fields for shared gateway support
    if ($sms_phone_number) {
        $smsData['sms_phone_number'] = $sms_phone_number;
    }
    if ($booking_phone_number) {
        $smsData['booking_phone_number'] = $booking_phone_number;
    }
    if ($workpoint_name) {
        $smsData['workpoint_name'] = $workpoint_name;
    }
    if ($organisation_name) {
        $smsData['organisation_name'] = $organisation_name;
    }

    $ch = curl_init($smsApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($smsData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log CURL errors if any
    if ($curlError) {
        logMessage("  CURL Error: " . $curlError);
    }

    // Detect permanent failures (invalid phone, number not in service, etc.)
    $isPermanentFailure = false;
    $errorType = 'temporary';

    if ($httpCode === 0 && $curlError) {
        // Network/API unavailable - temporary failure
        $errorType = 'api_unavailable';
    } elseif ($httpCode === 400) {
        // Bad request - likely invalid phone number - permanent failure
        $isPermanentFailure = true;
        $errorType = 'invalid_phone';

        // Check response for specific errors
        $responseData = json_decode($response, true);
        if (isset($responseData['error'])) {
            if (stripos($responseData['error'], 'phone') !== false ||
                stripos($responseData['error'], 'digit') !== false ||
                stripos($responseData['error'], 'invalid') !== false) {
                $errorType = 'invalid_phone';
            }
        }
    } elseif ($httpCode === 404 || $httpCode === 422) {
        // Number not found/not in service - permanent failure
        $isPermanentFailure = true;
        $errorType = 'number_not_in_service';
    }

    return [
        'success' => ($httpCode === 200),
        'response' => $response,
        'code' => $httpCode,
        'permanent_failure' => $isPermanentFailure,
        'error_type' => $errorType
    ];
}

// Process a single queue item
function processQueueItem($pdo, $item) {
    $booking_data = json_decode($item['booking_data'], true);
    
    // Get SMS settings for this working point
    $action_map = ['created' => 'creation', 'updated' => 'update', 'deleted' => 'cancellation'];
    $settings = getSMSSettings($pdo, $booking_data['id_work_place'], $action_map[$item['action']]);
    
    if (!$settings || empty($settings['setting_value'])) {
        throw new Exception("No SMS template configured for {$item['action']} action");
    }
    
    // Check if we should send SMS based on channel and force_sms setting
    $should_send = false;
    $excluded_channels = array_map('trim', explode(',', $settings['excluded_channels'] ?? 'SMS'));
    $received_through = strtoupper($booking_data['received_through'] ?? 'WEB');
    
    if ($item['force_sms'] === 'yes') {
        // Force send regardless of channel exclusion
        $should_send = true;
        logMessage("Force sending SMS for booking {$booking_data['unic_id']} (override exclusion)");
    } elseif ($item['force_sms'] === 'no') {
        // Force don't send regardless of channel settings
        $should_send = false;
        logMessage("Force skipping SMS for booking {$booking_data['unic_id']} (override to not send)");
    } else {
        // Use default channel exclusion logic
        $should_send = !in_array($received_through, $excluded_channels);
        if (!$should_send) {
            logMessage("Skipping SMS for booking {$booking_data['unic_id']} - channel {$received_through} is excluded");
        }
    }
    
    if (!$should_send) {
        // Mark as completed without sending
        return ['status' => 'completed', 'message' => 'SMS skipped based on settings'];
    }
    
    // Process template and send SMS
    $template_data = processTemplate($settings['setting_value'], $booking_data, $pdo);

    // Determine SMS mode
    $sms_mode = $template_data['sms_phone_number'] ? 'shared_gateway' : 'dedicated_pi';

    // Log SMS details before sending
    logMessage("================================================================================");
    logMessage("Preparing SMS for booking {$booking_data['unic_id']} - Action: {$item['action']}");
    logMessage("  To: {$booking_data['client_phone_nr']}");
    logMessage("  From: {$template_data['sender_phone']}");
    if ($sms_mode === 'shared_gateway') {
        logMessage("  Mode: Shared SMS Gateway");
        logMessage("  SMS Gateway: {$template_data['sms_phone_number']}");
        logMessage("  Booking Phone: {$template_data['booking_phone_number']}");
        logMessage("  Workpoint: {$template_data['workpoint_name']}");
    } else {
        logMessage("  Mode: Dedicated Pi Gateway");
    }
    logMessage("  Message: " . substr($template_data['message'], 0, 100) . (strlen($template_data['message']) > 100 ? '...' : ''));
    logMessage("  API Endpoint: http://my-bookings.co.uk:8088/api/send-sms");

    $sms_result = sendSMS(
        $booking_data['client_phone_nr'],
        $template_data['sender_phone'],
        $template_data['message'],
        $template_data['sms_phone_number'],
        $template_data['booking_phone_number'],
        $template_data['workpoint_name'],
        $template_data['organisation_name']
    );

    if ($sms_result['success']) {
        logMessage("✓ SMS sent successfully for booking {$booking_data['unic_id']}");
        logMessage("  Full API Response: " . $sms_result['response']);
        logMessage("================================================================================");
        return ['status' => 'completed', 'message' => 'SMS sent successfully'];
    } else {
        logMessage("✗ SMS FAILED for booking {$booking_data['unic_id']}");
        logMessage("  HTTP Code: " . $sms_result['code']);
        logMessage("  Error Type: " . $sms_result['error_type']);
        logMessage("  Full API Response: " . ($sms_result['response'] ?: '(empty response)'));
        logMessage("  Full API Error Details: " . json_encode([
            'http_code' => $sms_result['code'],
            'error_type' => $sms_result['error_type'],
            'response' => $sms_result['response'],
            'permanent_failure' => $sms_result['permanent_failure']
        ]));

        // Handle permanent failures gracefully
        if ($sms_result['permanent_failure']) {
            $reason = '';
            switch ($sms_result['error_type']) {
                case 'invalid_phone':
                    $reason = "Invalid phone number format";
                    logMessage("  Permanent failure: Invalid phone number - will not retry");
                    break;
                case 'number_not_in_service':
                    $reason = "Phone number not in service or unreachable";
                    logMessage("  Permanent failure: Number not in service - will not retry");
                    break;
                default:
                    $reason = "Permanent SMS delivery failure";
                    logMessage("  Permanent failure: Cannot deliver SMS - will not retry");
            }

            // Return a special status to mark as failed without retrying
            logMessage("================================================================================");
            return [
                'status' => 'failed_permanent',
                'message' => $reason . ': ' . $sms_result['response'],
                'error_type' => $sms_result['error_type']
            ];
        }

        // Temporary failure - will retry
        logMessage("  Temporary failure - will be retried");
        logMessage("================================================================================");
        throw new Exception("SMS API error: " . $sms_result['response']);
    }
}

// Main worker loop
function runWorker($pdo) {
    global $BATCH_SIZE, $SLEEP_SECONDS, $MAX_ATTEMPTS;
    
    while (true) {
        pcntl_signal_dispatch();
        
        try {
            // Get pending items
            $stmt = $pdo->prepare("
                SELECT * FROM booking_sms_queue 
                WHERE status = 'pending' 
                AND attempts < :max_attempts
                ORDER BY created_at ASC 
                LIMIT :batch_size
            ");
            $stmt->bindValue(':max_attempts', $MAX_ATTEMPTS, PDO::PARAM_INT);
            $stmt->bindValue(':batch_size', $BATCH_SIZE, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($items) > 0) {
                logMessage("Processing " . count($items) . " items");
                
                foreach ($items as $item) {
                    // Update status to processing
                    $updateStmt = $pdo->prepare("
                        UPDATE booking_sms_queue 
                        SET status = 'processing', attempts = attempts + 1 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$item['id']]);
                    
                    try {
                        $result = processQueueItem($pdo, $item);

                        // Check if this is a permanent failure
                        if ($result['status'] === 'failed_permanent') {
                            // Mark as failed immediately without retrying
                            $updateStmt = $pdo->prepare("
                                UPDATE booking_sms_queue
                                SET status = 'failed',
                                    processed_at = NOW(),
                                    error_message = ?
                                WHERE id = ?
                            ");
                            $updateStmt->execute([$result['message'], $item['id']]);
                            logMessage("Marked as permanently failed (no retry needed)");
                        } else {
                            // Update status to completed
                            $updateStmt = $pdo->prepare("
                                UPDATE booking_sms_queue
                                SET status = 'completed',
                                    processed_at = NOW(),
                                    error_message = ?
                                WHERE id = ?
                            ");
                            $updateStmt->execute([$result['message'], $item['id']]);
                        }

                    } catch (Exception $e) {
                        logMessage("Error processing item {$item['id']} (Booking: {$item['booking_id']}, Attempt: " . ($item['attempts'] + 1) . "/{$MAX_ATTEMPTS})");
                        logMessage("  Error: " . $e->getMessage());
                        
                        // Update status to failed or back to pending
                        $newStatus = ($item['attempts'] + 1 >= $MAX_ATTEMPTS) ? 'failed' : 'pending';
                        if ($newStatus === 'failed') {
                            logMessage("  Status: FAILED (max attempts reached)");
                        } else {
                            logMessage("  Status: Will retry (" . ($MAX_ATTEMPTS - $item['attempts'] - 1) . " attempts remaining)");
                        }
                        
                        $updateStmt = $pdo->prepare("
                            UPDATE booking_sms_queue 
                            SET status = ?, 
                                error_message = ?
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$newStatus, $e->getMessage(), $item['id']]);
                    }
                    
                    pcntl_signal_dispatch();
                }
            }
            
            // Clean up old completed/failed records (older than 7 days)
            $stmt = $pdo->prepare("
                DELETE FROM booking_sms_queue 
                WHERE status IN ('completed', 'failed') 
                AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            
        } catch (Exception $e) {
            logMessage("Worker error: " . $e->getMessage());
        }
        
        sleep($SLEEP_SECONDS);
    }
}

// Start worker
createLock();

try {
    logMessage("Starting SMS worker...");
    runWorker($pdo);
} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage());
    removeLock();
    exit(1);
}