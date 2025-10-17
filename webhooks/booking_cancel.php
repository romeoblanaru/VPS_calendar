<?php
/**
 * Booking Cancel Webhook
 * 
 * This webhook handles the cancellation/removal of existing bookings in the calendar system.
 * It accepts a booking ID and removes the corresponding entry from the bookings table.
 * 
 * Endpoint: /webhooks/booking_cancel.php
 * Method: GET or POST
 * 
 * Input Parameters:
 * - booking_id (required): ID of the booking to cancel/remove
 * - made_by (optional): Who or what system cancelled the booking (e.g., staff name, system name, user ID)
 * - sms (optional): yes/no - Override SMS notification settings (yes=force send, no=force don't send)
 * 
 * Output: JSON response with cancellation details or error information
 * 
 * @author Calendar System
 * @version 1.0
 * @since 2025-01-15
 */

// Include required files
require_once '../includes/db.php';
require_once '../includes/webhook_logger.php';
require_once __DIR__ . '/../includes/google_calendar_sync.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'booking_cancel');

try {
    // Get parameters from GET or POST
    $booking_id = $_GET['booking_id'] ?? $_POST['booking_id'] ?? null;
    $made_by = $_GET['made_by'] ?? $_POST['made_by'] ?? null;
    $sms = $_GET['sms'] ?? $_POST['sms'] ?? null; // Optional SMS parameter
    
    // Log the incoming request
    $requestData = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'parameters' => ['booking_id' => $booking_id, 'made_by' => $made_by],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // Validate required parameters
    if (empty($booking_id)) {
        throw new Exception("Missing required parameter: booking_id");
    }
    
    // Validate booking_id is numeric
    if (!is_numeric($booking_id)) {
        throw new Exception("Invalid booking_id. Must be a numeric value");
    }
    
    $booking_id = (int)$booking_id;
    
    // Check if booking exists and get its details before deletion
    $stmt = $pdo->prepare("
        SELECT b.*, s.name as specialist_name, wp.name_of_the_place as working_point_name, 
               sv.name_of_service as service_name, b.google_event_id
        FROM booking b
        LEFT JOIN specialists s ON b.id_specialist = s.unic_id
        LEFT JOIN working_points wp ON b.id_work_place = wp.unic_id
        LEFT JOIN services sv ON b.service_id = sv.unic_id
        WHERE b.unic_id = ?
    ");
    
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("Booking not found with ID: $booking_id");
    }
    
    // Store booking details for logging
    $bookingDetails = [
        'id' => $booking['unic_id'],
        'unic_id' => $booking['unic_id'] ?? null,
        'specialist_name' => $booking['specialist_name'],
        'working_point_name' => $booking['working_point_name'],
        'service_name' => $booking['service_name'],
        'client_full_name' => $booking['client_full_name'],
        'client_phone_nr' => $booking['client_phone_nr'],
        'booking_start_datetime' => $booking['booking_start_datetime'],
        'booking_end_datetime' => $booking['booking_end_datetime'],
        'received_through' => $booking['received_through'],
        'day_of_creation' => $booking['day_of_creation']
    ];
    
    // Backup the booking to booking_canceled table before deletion
    $cancellationTime = date('Y-m-d H:i:s');
    
    $backupStmt = $pdo->prepare("
        INSERT INTO booking_canceled (
            id_specialist, id_work_place, service_id, booking_start_datetime, 
            booking_end_datetime, client_full_name, client_phone_nr, received_through,
            received_call_date, client_transcript_conversation, day_of_creation, 
            unic_id, organisation_id, cancellation_time, made_by, google_event_id
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");
    
    $backupResult = $backupStmt->execute([
        $booking['id_specialist'],
        $booking['id_work_place'],
        $booking['service_id'],
        $booking['booking_start_datetime'],
        $booking['booking_end_datetime'],
        $booking['client_full_name'],
        $booking['client_phone_nr'],
        $booking['received_through'],
        $booking['received_call_date'],
        $booking['client_transcript_conversation'],
        $booking['day_of_creation'],
        $booking['unic_id'],
        $booking['organisation_id'],
        $cancellationTime,
        $made_by,
        $booking['google_event_id'] ?? null
    ]);
    
    if (!$backupResult) {
        throw new Exception("Failed to backup booking to booking_canceled table");
    }
    
    $backupId = $pdo->lastInsertId();
    
    // Handle SMS sending preference
    if ($sms === 'yes') {
        // Force send SMS
        $pdo->exec("SET @force_sms = 'yes'");
    } elseif ($sms === 'no') {
        // Force don't send SMS
        $pdo->exec("SET @force_sms = 'no'");
    }
    // If sms parameter not provided, use default channel exclusion logic
    
    // Delete the booking
    $deleteStmt = $pdo->prepare("DELETE FROM booking WHERE unic_id = ?");
    $deleteResult = $deleteStmt->execute([$booking_id]);
    
    if (!$deleteResult) {
        throw new Exception("Failed to delete booking from database");
    }
    
    $rowsAffected = $deleteStmt->rowCount();
    
    if ($rowsAffected === 0) {
        throw new Exception("No booking was deleted. Booking ID may have been already removed.");
    }
    
    // Queue Google Calendar sync (non-blocking)
    try {
        queue_google_calendar_sync($pdo, 'deleted', (int)$booking_id, (int)$booking['id_specialist'], build_google_booking_payload($booking));
    } catch (Throwable $t) {
        // do not block webhook
    }
    
    // Build success response
    $response = [
        'status' => 'success',
        'message' => 'Booking cancelled successfully',
        'data' => [
            'booking_id' => $booking_id,
            'rows_deleted' => $rowsAffected,
            'backup_id' => $backupId,
            'cancelled_booking' => $bookingDetails,
            'cancellation_time' => $cancellationTime,
            'made_by' => $made_by
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Return success response
    http_response_code(200);
    echo json_encode($response);
    
    // Log successful cancellation
    $logger->logSuccess(json_encode($response), null, [
        'booking_id' => $booking_id,
        'specialist_id' => $booking['id_specialist'],
        'working_point_id' => $booking['id_work_place'],
        'organisation_id' => $booking['organisation_id'] ?? null,
        'additional_data' => [
            'booking_unique_id' => $booking['unic_id'] ?? null,
            'client_name' => $booking['client_full_name'],
            'client_phone' => $booking['client_phone_nr'],
            'booking_start' => $booking['booking_start_datetime'],
            'booking_end' => $booking['booking_end_datetime'],
            'received_through' => $booking['received_through'],
            'cancellation_time' => $cancellationTime,
            'made_by' => $made_by,
            'backup_id' => $backupId
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error
    $errorData = [
        'error' => $e->getMessage(),
        'parameters' => ['booking_id' => $booking_id ?? null],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $logger->logError($e->getMessage(), $e->getTraceAsString(), 400, ['additional_data' => $errorData]);
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    // Database error
    $errorData = [
        'error' => 'Database error occurred while cancelling booking',
        'parameters' => ['booking_id' => $booking_id ?? null],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $logger->logError('Database error: ' . $e->getMessage(), $e->getTraceAsString(), 500, ['additional_data' => $errorData]);
    
    // Return database error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred while cancelling booking',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
