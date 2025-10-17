<?php
/**
 * Enhanced Google Calendar Background Worker with Near-Real-Time Signals
 * VERSION 2.5 - Fixed credentials lookup to use global credentials fallback - 2025-09-12
 * Processes the sync queue with 3-5 second delay via database signals
 * Falls back to 2-minute cycle for reliability
 * 
 * Usage:
 * - Cron job (2-min backup): php process_google_calendar_queue_enhanced.php
 * - Manual: php process_google_calendar_queue_enhanced.php --manual
 * - Signal mode: php process_google_calendar_queue_enhanced.php --signal-loop
 * - Specific specialist: php process_google_calendar_queue_enhanced.php --specialist=123
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Log errors, don't display them
ini_set('log_errors', 1);

require_once 'includes/db.php';
require_once 'includes/google_calendar_event_manager.php';
require_once 'admin/google_oauth_config.php';
require_once 'includes/google_calendar_logger.php';

// Parse command line arguments first
$options = getopt('', ['manual', 'specialist:', 'verbose', 'help', 'signal-loop', 'once']);
$is_manual = isset($options['manual']);
$specific_specialist = isset($options['specialist']) ? (int)$options['specialist'] : null;
$verbose = isset($options['verbose']) || $is_manual;
$signal_loop = isset($options['signal-loop']);
$run_once = isset($options['once']);

// Initialize enhanced logger with verbose flag
$logger = new GoogleCalendarLogger($verbose);

if (isset($options['help'])) {
    echo "Enhanced Google Calendar Background Worker\n\n";
    echo "Usage:\n";
    echo "  php process_google_calendar_queue_enhanced.php                    # Normal cron mode (2-min cycle)\n";
    echo "  php process_google_calendar_queue_enhanced.php --manual           # Manual mode with output\n";
    echo "  php process_google_calendar_queue_enhanced.php --signal-loop      # Signal monitoring mode (3-5s)\n";
    echo "  php process_google_calendar_queue_enhanced.php --once             # Process queue once and exit\n";
    echo "  php process_google_calendar_queue_enhanced.php --specialist=123   # Process specific specialist\n";
    echo "  php process_google_calendar_queue_enhanced.php --verbose          # Verbose output\n";
    exit(0);
}

function log_message($message, $force_output = false, $category = 'INFO') {
    global $logger, $verbose;
    $logger->log($message, $category);
    if ($verbose || $force_output) {
        echo "[" . date('Y-m-d H:i:s') . "] [$category] $message\n";
    }
}

function refresh_access_token($pdo, $credential_id, $refresh_token) {
    try {
        $oauth_config = new GoogleOAuthConfig();
        $new_tokens = $oauth_config->refreshAccessToken($refresh_token);
        
        // Update the database with new tokens
        $stmt = $pdo->prepare("UPDATE google_calendar_credentials SET 
            access_token = ?, 
            expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
            updated_at = NOW() 
            WHERE id = ?");
        
        $expires_in = $new_tokens['expires_in'] ?? 3600;
        $stmt->execute([$new_tokens['access_token'], $expires_in, $credential_id]);
        
        return $new_tokens['access_token'];
    } catch (Exception $e) {
        log_message("Failed to refresh access token for credential $credential_id: " . $e->getMessage());
        return null;
    }
}

function sync_booking_to_google($pdo, $booking_data, $credentials, $action) {
    global $logger;
    try {
        // Initialize professional Event Manager with verbose mode
        $eventManager = new GoogleCalendarEventManager($pdo, true);
        
        // Check if token is expired (refresh 5 minutes before expiry)
        $access_token = $credentials['access_token'];
        if ($credentials['expires_at']) {
            $expires_at = new DateTime($credentials['expires_at']);
            $now = new DateTime();
            $now->add(new DateInterval('PT5M')); // Add 5 minutes buffer
            
            if ($now >= $expires_at) {
                log_message("Access token expired for specialist {$credentials['specialist_id']}, refreshing...");
                $access_token = refresh_access_token($pdo, $credentials['id'], $credentials['refresh_token']);
                if (!$access_token) {
                    throw new Exception('Failed to refresh expired access token');
                }
                // Update credentials array with new token
                $credentials['access_token'] = $access_token;
            }
        }
        
        $booking_id = (int)$booking_data['unic_id'];
        
        // Log the operation details
        $logger->logOperation(strtoupper($action), $booking_id, [
            'client_name' => $booking_data['client_full_name'] ?? 'N/A',
            'phone' => $booking_data['client_phone_nr'] ?? 'N/A',
            'service' => $booking_data['service_name'] ?? 'N/A',
            'specialist_id' => $credentials['specialist_id'] ?? 'global',
            'start_time' => $booking_data['booking_start_datetime'] ?? 'N/A'
        ]);
        
        if ($action === 'delete' || $action === 'deleted') {
            $result = $eventManager->deleteEvent($booking_id, $credentials);
            // Extra delay for delete operations to prevent conflicts
            usleep(500000); // 0.5 second delay after deletions
            return $result;
        } else {
            // Build event data using Event Manager
            $eventData = $eventManager->buildEventData(
                $booking_data, 
                $booking_data['service_name'] ?? null,
                $booking_data['country'] ?? null
            );
            
            if ($action === 'update' || $action === 'updated') {
                return $eventManager->updateEvent($booking_id, $credentials, $eventData);
            } else {
                return $eventManager->createEvent($booking_id, $credentials, $eventData);
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $logger->logError('SYNC_EXCEPTION', $error_message, [
            'booking_id' => $booking_data['unic_id'] ?? 'unknown',
            'action' => $action,
            'trace' => $e->getTraceAsString()
        ]);
        return ['success' => false, 'error' => $error_message];
    }
}

/**
 * Check for pending signals and process queue if found
 * Returns number of signals processed
 */
function check_and_process_signals($pdo, $specific_specialist = null) {
    try {
        // Check if signals table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'gcal_worker_signals'");
        if ($stmt->rowCount() == 0) {
            return 0; // No signals table, skip signal processing
        }
        
        // Check for unprocessed signals
        $where_clause = "processed = FALSE";
        $params = [];
        
        if ($specific_specialist) {
            $where_clause .= " AND specialist_id = ?";
            $params[] = $specific_specialist;
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM gcal_worker_signals WHERE $where_clause");
        $stmt->execute($params);
        $signal_count = $stmt->fetchColumn();
        
        if ($signal_count > 0) {
            log_message("Found $signal_count unprocessed signals - triggering immediate queue processing");
            
            // Process the queue
            $result = process_queue($pdo, $specific_specialist);
            
            // Mark signals as processed
            // Build WHERE clause for UPDATE (processed = FALSE becomes processed = TRUE)
            $update_where_clause = "processed = FALSE";
            $update_params = [];
            
            if ($specific_specialist) {
                $update_where_clause .= " AND specialist_id = ?";
                $update_params[] = $specific_specialist;
            }
            
            $stmt = $pdo->prepare("UPDATE gcal_worker_signals SET processed = TRUE, processed_at = NOW() WHERE $update_where_clause");
            $stmt->execute($params);
            
            // Clean up old processed signals (older than 24 hours)
            $pdo->query("DELETE FROM gcal_worker_signals WHERE processed = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            
            return $signal_count;
        }
        
        return 0;
    } catch (Exception $e) {
        log_message("Signal check error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Main queue processing function
 */
function process_queue($pdo, $specific_specialist = null) {
    // Build query conditions - process pending items AND failed items with less than 5 attempts
    $where_conditions = ["(status = 'pending' OR (status = 'failed' AND attempts < 5))"];
    $params = [];
    
    if ($specific_specialist) {
        $where_conditions[] = "specialist_id = ?";
        $params[] = $specific_specialist;
    }
    
    // Get pending queue items
    $where_clause = implode(' AND ', $where_conditions);
    $stmt = $pdo->prepare("
        SELECT q.*, q.event_type as action,
               COALESCE(b.unic_id, bc.unic_id) as unic_id,
               COALESCE(b.booking_start_datetime, bc.booking_start_datetime) as booking_start_datetime,
               COALESCE(b.booking_end_datetime, bc.booking_end_datetime) as booking_end_datetime,
               COALESCE(b.client_full_name, bc.client_full_name) as client_full_name,
               COALESCE(b.client_phone_nr, bc.client_phone_nr) as client_phone_nr,
               COALESCE(b.received_through, bc.received_through) as received_through,
               COALESCE(b.day_of_creation, bc.day_of_creation) as day_of_creation,
               COALESCE(b.google_event_id, bc.google_event_id) as google_event_id,
               COALESCE(s.name_of_service, s2.name_of_service) as service_name,
               COALESCE(wp.country, wp2.country) as country
        FROM google_calendar_sync_queue q
        LEFT JOIN booking b ON q.booking_id = b.unic_id
        LEFT JOIN booking_canceled bc ON q.booking_id = bc.unic_id
        LEFT JOIN services s ON b.service_id = s.unic_id
        LEFT JOIN services s2 ON bc.service_id = s2.unic_id
        LEFT JOIN working_points wp ON b.id_work_place = wp.unic_id
        LEFT JOIN working_points wp2 ON bc.id_work_place = wp2.unic_id
        WHERE $where_clause
        ORDER BY q.created_at ASC
        LIMIT 50
    ");
    $stmt->execute($params);
    $queue_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($queue_items)) {
        return ['processed' => 0, 'success' => 0, 'failed' => 0];
    }
    
    log_message("Processing " . count($queue_items) . " queue items");
    
    $processed_count = 0;
    $success_count = 0;
    $failed_count = 0;
    
    foreach ($queue_items as $item) {
        $processed_count++;
        
        // Mark as processing and increment attempts
        $pdo->prepare("UPDATE google_calendar_sync_queue SET status = 'processing', attempts = attempts + 1, processed_at = NOW() WHERE id = ?")
            ->execute([$item['id']]);
        
        $current_attempt = $item['attempts'] + 1;
        log_message("Processing item {$item['id']}: {$item['action']} booking {$item['booking_id']} for specialist {$item['specialist_id']} (attempt $current_attempt)");
        
        // Get Google Calendar credentials for this specialist, fallback to global credentials
        $stmt = $pdo->prepare("SELECT * FROM google_calendar_credentials WHERE specialist_id = ? AND status = 'active'");
        $stmt->execute([$item['specialist_id']]);
        $credentials = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no specialist-specific credentials, try global credentials
        if (!$credentials) {
            log_message("No specialist-specific credentials for specialist {$item['specialist_id']}, trying global credentials");
            $stmt = $pdo->prepare("SELECT * FROM google_calendar_credentials WHERE status = 'active' ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $credentials = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$credentials) {
            log_message("No active Google Calendar credentials found (specialist or global), skipping");
            $pdo->prepare("UPDATE google_calendar_sync_queue SET status = 'failed', error_message = 'No active credentials', processed_at = NOW() WHERE id = ?")
                ->execute([$item['id']]);
            $failed_count++;
            continue;
        } else {
            log_message("Using Google Calendar credentials for specialist {$credentials['specialist_id']}");
        }
        
        // Sync to Google Calendar
        $sync_result = sync_booking_to_google($pdo, $item, $credentials, $item['action']);
        
        if ($sync_result['success']) {
            $pdo->prepare("UPDATE google_calendar_sync_queue SET status = 'done', processed_at = NOW() WHERE id = ?")
                ->execute([$item['id']]);
            log_message("Item {$item['id']} processed successfully");
            $success_count++;
        } else {
            $detailed_error = $sync_result['error'] ?? 'Unknown error';
            $attempt_count = $current_attempt;
            
            if ($attempt_count >= 5) {
                $pdo->prepare("UPDATE google_calendar_sync_queue SET status = 'permanently_failed', error_message = 'Max retries exceeded', last_error = ? WHERE id = ?")
                    ->execute(["Final attempt failed: $detailed_error", $item['id']]);
                log_message("Item {$item['id']} permanently failed after 5 attempts. Error: $detailed_error");
            } else {
                $pdo->prepare("UPDATE google_calendar_sync_queue SET status = 'failed', error_message = 'Google API sync failed', last_error = ? WHERE id = ?")
                    ->execute(["Attempt $attempt_count of 5: $detailed_error", $item['id']]);
                log_message("Item {$item['id']} failed (attempt $attempt_count/5), will retry. Error: $detailed_error");
                
                // If it's a rate limit error, add extra delay before retry
                if (strpos($detailed_error, "rate") !== false || strpos($detailed_error, "quota") !== false) {
                    log_message("Rate limit detected, adding extra delay");
                    sleep(2); // 2 second delay for rate limit errors
                }
            }
            
            $failed_count++;
        }
        
        // Small delay to avoid overwhelming Google API
        usleep(100000); // 0.1 second delay
    }
    
    return ['processed' => $processed_count, 'success' => $success_count, 'failed' => $failed_count];
}

// Main execution
try {
    $logger->log("Enhanced Google Calendar Worker VERSION 2.5 - Fixed credentials lookup to use global credentials fallback", 'STARTUP');
    log_message("Enhanced Google Calendar Worker VERSION 2.5 - Fixed credentials lookup to use global credentials fallback", true);
    
    if ($signal_loop) {
        $logger->log("Starting signal monitoring loop (near-real-time mode)", 'STARTUP');
        log_message("Starting signal monitoring loop (near-real-time mode)", true);
        
        // Ensure signals table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'gcal_worker_signals'");
        if ($stmt->rowCount() == 0) {
            log_message("Creating signals table for near-real-time sync...", true);
            $schema_sql = file_get_contents(__DIR__ . '/database/google_calendar_signals_schema.sql');
            if ($schema_sql) {
                $pdo->exec($schema_sql);
                log_message("Signals table created successfully", true);
            } else {
                log_message("Error: Could not create signals table", true);
                exit(1);
            }
        }
        
        // Signal monitoring loop (for daemon mode)
        while (true) {
            $signals_processed = check_and_process_signals($pdo, $specific_specialist);
            if ($signals_processed > 0) {
                $logger->logQueue('SIGNALS_PROCESSED', ['count' => $signals_processed]);
                log_message("Processed $signals_processed signals, queue updated");
            }
            
            // Sleep for 4 seconds before checking again
            sleep(4);
        }
    } else {
        // Regular mode (cron job or manual)
        log_message("Google Calendar worker started" . ($is_manual ? " (manual mode)" : "") . " with signal support", true);
        
        // Check if queue table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'google_calendar_sync_queue'");
        if ($stmt->rowCount() == 0) {
            log_message("Queue table doesn't exist yet, nothing to process", true);
            exit(0);
        }
        
        // Check for signals first (near-real-time processing)
        $signals_processed = check_and_process_signals($pdo, $specific_specialist);
        
        // If no signals were processed, do regular queue processing (2-minute backup)
        if ($signals_processed == 0) {
            $result = process_queue($pdo, $specific_specialist);
            if ($result['processed'] == 0) {
                log_message("No items in queue to process", $is_manual);
            } else {
                log_message("Processing complete: {$result['processed']} processed, {$result['success']} successful, {$result['failed']} failed", true);
            }
        }
    }
    
} catch (Exception $e) {
    $logger->logError('WORKER_FATAL_ERROR', $e->getMessage(), [
        'trace' => $e->getTraceAsString()
    ]);
    log_message("Worker error: " . $e->getMessage(), true);
    exit(1);
}
?> 