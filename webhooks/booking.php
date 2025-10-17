<?php
/**
 * Booking Webhook
 * 
 * This webhook handles the creation of new bookings in the calendar system.
 * It accepts booking details and creates a new entry in the bookings table.
 * 
 * Endpoint: /webhooks/booking.php
 * Method: GET or POST
 * 
 * Input Parameters:
 * - id_specialist (required): ID of the specialist handling the booking
 * - id_work_place (required): ID of the working point/place
 * - service_id (required): ID of the service being booked
 * - booking_start_datetime (required): Start date/time of the booking (Y-m-d H:i:s format)
 * - booking_end_datetime (required): End date/time of the booking (Y-m-d H:i:s format)
 * - client_full_name (required): Full name of the client
 * - client_phone_nr (required): Phone number of the client
 * - received_through (optional): Source through which the booking was received (PHONE, SMS, Facebook, Email, Whatsapp, etc.)
 * - client_transcript_conversation (optional): Transcript of client conversation
 * - sms (optional): yes/no - Override SMS notification settings (yes=force send, no=force don't send)
 * 
 * Output: JSON response with booking details or error information
 * 
 * @author Calendar System
 * @version 1.4
 * @since 2025-01-15
 * @updated 2025-01-15 - Enhanced phone number cleaning, kept client_transcript_conversation as optional
 */

// Include required files
require_once '../includes/db.php';
require_once '../includes/webhook_logger.php';
require_once __DIR__ . '/../includes/google_calendar_sync.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'booking');

try {
    // Get parameters from GET or POST
    $id_specialist = $_GET['id_specialist'] ?? $_POST['id_specialist'] ?? null;
    $id_work_place = $_GET['id_work_place'] ?? $_POST['id_work_place'] ?? null;
    $service_id = $_GET['service_id'] ?? $_POST['service_id'] ?? null;
    $booking_start_datetime = $_GET['booking_start_datetime'] ?? $_POST['booking_start_datetime'] ?? null;
    $booking_end_datetime = $_GET['booking_end_datetime'] ?? $_POST['booking_end_datetime'] ?? null;
    $client_full_name = $_GET['client_full_name'] ?? $_POST['client_full_name'] ?? null;
    $client_phone_nr = $_GET['client_phone_nr'] ?? $_POST['client_phone_nr'] ?? null;
    $received_through = $_GET['received_through'] ?? $_POST['received_through'] ?? null;
    $client_transcript_conversation = $_GET['client_transcript_conversation'] ?? $_POST['client_transcript_conversation'] ?? null;
    $sms = $_GET['sms'] ?? $_POST['sms'] ?? null; // Optional SMS parameter
    
    // Define required fields
    $required_fields = [
        'id_specialist' => $id_specialist,
        'id_work_place' => $id_work_place,
        'service_id' => $service_id,
        'booking_start_datetime' => $booking_start_datetime,
        'booking_end_datetime' => $booking_end_datetime,
        'client_full_name' => $client_full_name,
        'client_phone_nr' => $client_phone_nr
    ];
    
    // Validate required parameters
    $missing_fields = [];
    foreach ($required_fields as $field_name => $field_value) {
        if (empty($field_value)) {
            $missing_fields[] = $field_name;
        }
    }
    
    if (!empty($missing_fields)) {
        $errorResponse = [
            'error' => 'Missing required parameters: ' . implode(', ', $missing_fields),
            'status' => 'error',
            'required_fields' => array_keys($required_fields),
            'missing_fields' => $missing_fields,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(400);
        echo json_encode($errorResponse);
        
        // Log the validation error
        $logger->logError(
            'Missing required parameters: ' . implode(', ', $missing_fields),
            null,
            400,
            [
                'additional_data' => [
                    'missing_fields' => $missing_fields,
                    'provided_fields' => array_keys(array_filter($required_fields))
                ]
            ]
        );
        exit();
    }
    
    // Validate datetime formats
    $datetime_fields = [
        'booking_start_datetime' => $booking_start_datetime,
        'booking_end_datetime' => $booking_end_datetime
    ];
    
    foreach ($datetime_fields as $field_name => $datetime_value) {
        $parsed_date = DateTime::createFromFormat('Y-m-d H:i:s', $datetime_value);
        if (!$parsed_date || $parsed_date->format('Y-m-d H:i:s') !== $datetime_value) {
            $errorResponse = [
                'error' => "Invalid datetime format for {$field_name}. Expected format: Y-m-d H:i:s (e.g., 2025-01-15 14:30:00)",
                'status' => 'error',
                'field' => $field_name,
                'provided_value' => $datetime_value,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            http_response_code(400);
            echo json_encode($errorResponse);
            
            // Log the validation error
            $logger->logError(
                "Invalid datetime format for {$field_name}: {$datetime_value}",
                null,
                400,
                [
                    'additional_data' => [
                        'invalid_field' => $field_name,
                        'invalid_value' => $datetime_value,
                        'expected_format' => 'Y-m-d H:i:s'
                    ]
                ]
            );
            exit();
        }
    }
    
    // Validate that booking end time is after start time
    $start_time = new DateTime($booking_start_datetime);
    $end_time = new DateTime($booking_end_datetime);
    
    if ($end_time <= $start_time) {
        $errorResponse = [
            'error' => 'Booking end datetime must be after start datetime',
            'status' => 'error',
            'booking_start_datetime' => $booking_start_datetime,
            'booking_end_datetime' => $booking_end_datetime,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(400);
        echo json_encode($errorResponse);
        
        // Log the validation error
        $logger->logError(
            'Invalid booking time range: end time is not after start time',
            null,
            400,
            [
                'additional_data' => [
                    'start_datetime' => $booking_start_datetime,
                    'end_datetime' => $booking_end_datetime
                ]
            ]
        );
        exit();
    }
    
    // Verify that specialist exists
    $stmt = $pdo->prepare("SELECT unic_id, name FROM specialists WHERE unic_id = ?");
    $stmt->execute([$id_specialist]);
    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$specialist) {
        $errorResponse = [
            'error' => 'Specialist not found',
            'status' => 'error',
            'id_specialist' => $id_specialist,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(404);
        echo json_encode($errorResponse);
        
        // Log the error
        $logger->logError(
            "Specialist not found: {$id_specialist}",
            null,
            404,
            [
                'specialist_id' => $id_specialist,
                'additional_data' => [
                    'requested_specialist_id' => $id_specialist
                ]
            ]
        );
        exit();
    }
    
    // Verify that working point exists
    $stmt = $pdo->prepare("SELECT unic_id, name_of_the_place, organisation_id FROM working_points WHERE unic_id = ?");
    $stmt->execute([$id_work_place]);
    $working_point = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$working_point) {
        $errorResponse = [
            'error' => 'Working point not found',
            'status' => 'error',
            'id_work_place' => $id_work_place,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(404);
        echo json_encode($errorResponse);
        
        // Log the error
        $logger->logError(
            "Working point not found: {$id_work_place}",
            null,
            404,
            [
                'working_point_id' => $id_work_place,
                'additional_data' => [
                    'requested_working_point_id' => $id_work_place
                ]
            ]
        );
        exit();
    }
    
    // Verify that service exists
    $stmt = $pdo->prepare("SELECT unic_id, name_of_service, duration FROM services WHERE unic_id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        $errorResponse = [
            'error' => 'Service not found',
            'status' => 'error',
            'service_id' => $service_id,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(404);
        echo json_encode($errorResponse);
        
        // Log the error
        $logger->logError(
            "Service not found: {$service_id}",
            null,
            404,
            [
                'additional_data' => [
                    'requested_service_id' => $service_id
                ]
            ]
        );
        exit();
    }
    
    // Check for overlapping bookings for the same specialist
    $stmt = $pdo->prepare("
        SELECT unic_id, client_full_name, booking_start_datetime, booking_end_datetime 
        FROM booking 
        WHERE id_specialist = ? 
        AND (
            (booking_start_datetime <= ? AND booking_end_datetime > ?) OR
            (booking_start_datetime < ? AND booking_end_datetime >= ?) OR
            (booking_start_datetime >= ? AND booking_end_datetime <= ?)
        )
    ");
    $stmt->execute([
        $id_specialist,
        $booking_start_datetime, $booking_start_datetime,
        $booking_end_datetime, $booking_end_datetime,
        $booking_start_datetime, $booking_end_datetime
    ]);
    $overlapping_booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($overlapping_booking) {
        $errorResponse = [
            'error' => 'Time slot is already booked for this specialist',
            'status' => 'error',
            'conflict_details' => [
                'existing_booking_id' => $overlapping_booking['unic_id'],
                'existing_client' => $overlapping_booking['client_full_name'],
                'existing_start' => $overlapping_booking['booking_start_datetime'],
                'existing_end' => $overlapping_booking['booking_end_datetime']
            ],
            'requested_start' => $booking_start_datetime,
            'requested_end' => $booking_end_datetime,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(409);
        echo json_encode($errorResponse);
        
        // Log the conflict
        $logger->logError(
            "Booking time conflict for specialist {$id_specialist}",
            null,
            409,
            [
                'specialist_id' => $id_specialist,
                'working_point_id' => $id_work_place,
                'additional_data' => [
                    'requested_start' => $booking_start_datetime,
                    'requested_end' => $booking_end_datetime,
                    'conflicting_booking_id' => $overlapping_booking['unic_id'],
                    'conflicting_start' => $overlapping_booking['booking_start_datetime'],
                    'conflicting_end' => $overlapping_booking['booking_end_datetime']
                ]
            ]
        );
        exit();
    }
    
    // Clean phone number - keep the + symbol for international numbers
    $clean_phone = preg_replace('/[^0-9+]/', '', $client_phone_nr);
    
    // Process received_through parameter (optional)
    if (!empty($received_through)) {
        // Truncate to 20 characters if longer
        $received_through = substr(trim($received_through), 0, 20);
    }
    
    // Handle SMS sending preference
    if ($sms === 'yes') {
        // Force send SMS
        $pdo->exec("SET @force_sms = 'yes'");
    } elseif ($sms === 'no') {
        // Force don't send SMS
        $pdo->exec("SET @force_sms = 'no'");
    }
    // If sms parameter not provided, use default channel exclusion logic
    
    // Insert the booking (let database generate the unic_id)
    $stmt = $pdo->prepare("
        INSERT INTO booking (
            id_specialist, id_work_place, service_id,
            booking_start_datetime, booking_end_datetime,
            client_full_name, client_phone_nr,
            received_through, received_call_date, client_transcript_conversation,
            day_of_creation
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
        )
    ");
    
    $insert_success = $stmt->execute([
        $id_specialist,
        $id_work_place,
        $service_id,
        $booking_start_datetime,
        $booking_end_datetime,
        $client_full_name,
        $clean_phone,
        $received_through,
        date('Y-m-d H:i:s'), // received_call_date - same as day_of_creation
        $client_transcript_conversation // client_transcript_conversation is restored
    ]);
    
    if (!$insert_success) {
        throw new Exception('Failed to insert booking into database');
    }
    
    // Get the inserted booking ID
    $booking_id = $pdo->lastInsertId();
    
    // Fetch the complete booking record to get any auto-generated fields
    $stmt = $pdo->prepare("SELECT * FROM booking WHERE unic_id = ?");
    $stmt->execute([$booking_id]);
    $booking_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Queue Google Calendar sync (non-blocking)
    try {
        queue_google_calendar_sync($pdo, 'created', (int)$booking_id, (int)$id_specialist, build_google_booking_payload($booking_record ?: []));
    } catch (Throwable $t) {
        // do not block webhook
    }
    
    // Build success response
    $response = [
        'status' => 'success',
        'message' => 'Booking created successfully',
        'booking_details' => [
            'booking_id' => $booking_id,
            'booking_unique_id' => $booking_record['unic_id'],
            'specialist_name' => $specialist['name'],
            'working_point_name' => $working_point['name_of_the_place'],
            'service_name' => $service['name_of_service'],
            'client_name' => $client_full_name,
            'client_phone' => $clean_phone,
            'booking_start' => $booking_start_datetime,
            'booking_end' => $booking_end_datetime,
            'duration_minutes' => $service['duration'],
            'received_through' => $received_through,
            'created_at' => $booking_record['day_of_creation']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Log successful booking creation
    $logger->logSuccess($response, null, [
        'booking_id' => $booking_id,
        'specialist_id' => $id_specialist,
        'working_point_id' => $id_work_place,
        'organisation_id' => $working_point['organisation_id'],
        'additional_data' => [
            'booking_unique_id' => $booking_record['unic_id'],
            'service_id' => $service_id,
            'service_name' => $service['name_of_service'],
            'service_duration' => $service['duration'],
            'client_name' => $client_full_name,
            'client_phone' => $clean_phone,
            'booking_start' => $booking_start_datetime,
            'booking_end' => $booking_end_datetime,
            'received_through' => $received_through,
            'has_transcript' => !empty($client_transcript_conversation),
            'transcript_length' => strlen($client_transcript_conversation ?? ''),
            'booking_duration_minutes' => $end_time->diff($start_time)->format('%i')
        ]
    ]);
    
    // Return success response
    http_response_code(201);
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Database error
    $errorResponse = [
        'error' => 'Database error occurred while creating booking',
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($errorResponse);
    
    // Log the database error
    $logger->logError(
        'Database error during booking creation: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500,
        [
            'specialist_id' => $id_specialist ?? null,
            'working_point_id' => $id_work_place ?? null,
            'additional_data' => [
                'error_code' => $e->getCode(),
                'sql_state' => $e->errorInfo[0] ?? null
            ]
        ]
    );
    
} catch (Exception $e) {
    // General error
    $errorResponse = [
        'error' => 'An unexpected error occurred while creating booking',
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($errorResponse);
    
    // Log the general error
    $logger->logError(
        'Unexpected error during booking creation: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500,
        [
            'specialist_id' => $id_specialist ?? null,
            'working_point_id' => $id_work_place ?? null,
            'additional_data' => [
                'error_type' => get_class($e)
            ]
        ]
    );
}
?> 