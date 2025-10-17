<?php
/**
 * Dynamic Variables for Start Webhook
 * 
 * This webhook provides dynamic variables to Telnyx for usage at the introductory stage
 * of the answering process. It accepts an assigned_phone_nr parameter and returns
 * the name_of_the_place and alias_name for that phone number.
 * 
 * Endpoint: /webhooks/dynamic_variables_for_start.php
 * Method: GET or POST
 * Input Parameter: assigned_phone_nr
 * Output: JSON with dynamic_variables object
 * 
 * @author Calendar System
 * @version 1.0
 * @since 2025-01-15
 */

// Include required files
require_once '../includes/db.php';
require_once '../includes/webhook_logger.php';

// Set content type to JSON
header('Content-Type: application/json');

// Configuration: Number of digits to match from the end of phone numbers
$PHONE_MATCH_DIGITS = 8;

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'dynamic_variables_for_start');

try {
    // Get parameters from GET or POST
    $assigned_phone_nr = $_GET['assigned_phone_nr'] ?? $_POST['assigned_phone_nr'] ?? null;
    
    // Validate required parameter
    if (!$assigned_phone_nr) {
        $errorResponse = [
            'error' => 'Missing required parameter: assigned_phone_nr',
            'status' => 'error',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(400);
        echo json_encode($errorResponse);
        
        // Log the error
        $logger->logError('Missing required parameter: assigned_phone_nr', null, 400);
        exit();
    }
    
    // Clean phone number to get only digits
    $clean_assigned_phone = preg_replace('/[^0-9]/', '', $assigned_phone_nr);
    
    // Get the last N digits for matching
    $phone_suffix = substr($clean_assigned_phone, -$PHONE_MATCH_DIGITS);
    
    // Find working point and organisation by phone number (matching last N digits)
    $stmt = $pdo->prepare("
        SELECT 
            wp.name_of_the_place,
            wp.address,
            wp.unic_id as working_point_id,
            o.alias_name,
            o.unic_id as organisation_id
        FROM working_points wp 
        JOIN organisations o ON wp.organisation_id = o.unic_id 
        WHERE RIGHT(REPLACE(wp.booking_phone_nr, ' ', ''), ?) = ?
    ");
    $stmt->execute([$PHONE_MATCH_DIGITS, $phone_suffix]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        $errorResponse = [
            'error' => 'No working point found for phone number: ' . $assigned_phone_nr . ' (matched last ' . $PHONE_MATCH_DIGITS . ' digits: ' . $phone_suffix . ')',
            'status' => 'error',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(404);
        echo json_encode($errorResponse);
        
        // Log the error
        $logger->logError('No working point found for phone number: ' . $assigned_phone_nr . ' (matched last ' . $PHONE_MATCH_DIGITS . ' digits: ' . $phone_suffix . ')', null, 404);
        exit();
    }
    
    // Build response structure
    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'assigned_phone_nr' => $assigned_phone_nr,
        'dynamic_variables' => [
            'name_of_the_place' => $result['name_of_the_place'],
            'address' => $result['address'],
            'alias_name' => $result['alias_name']
        ]
    ];
    
    // Log successful call
    $logger->logSuccess($response, null, [
        'working_point_id' => $result['working_point_id'],
        'organisation_id' => $result['organisation_id'],
        'additional_data' => [
            'phone_number_provided' => $assigned_phone_nr,
            'phone_suffix_matched' => $phone_suffix,
            'match_digits_used' => $PHONE_MATCH_DIGITS,
            'place_name' => $result['name_of_the_place'],
            'place_address' => $result['address'],
            'organisation_alias' => $result['alias_name']
        ]
    ]);
    
    // Return JSON response
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Database error
    $errorResponse = [
        'error' => 'Database error occurred',
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($errorResponse);
    
    // Log the database error
    $logger->logError(
        'Database error: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500
    );
    
} catch (Exception $e) {
    // General error
    $errorResponse = [
        'error' => 'An unexpected error occurred',
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($errorResponse);
    
    // Log the general error
    $logger->logError(
        'Unexpected error: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500
    );
}
?> 