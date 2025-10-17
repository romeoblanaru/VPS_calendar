<?php
/**
 * Get Past Conversation Memory Webhook
 * 
 * This webhook retrieves the last 3 conversation memory records for a specific client
 * based on their phone number and full name. Phone number matching uses the last 8 digits
 * to avoid country code and prefix mismatches.
 * 
 * Required POST Parameters:
 * - 'client_phone_nr': Phone number of the client (will match by last 8 digits)
 * - 'client_full_name': Full name of the client (exact match)
 * 
 * Returns:
 * - If successful: JSON payload with the last 3 most recent conversation records
 * - If error: Error message with details
 */

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection
require_once '../includes/db.php';

// Include webhook logger
require_once '../includes/webhook_logger.php';

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'get_past_conversation_memory');

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST method is allowed for this webhook");
    }
    
    // Get POST data
    $postData = $_POST;
    
    // If no POST data, try to get JSON input
    if (empty($postData)) {
        $input = file_get_contents('php://input');
        if ($input) {
            $postData = json_decode($input, true) ?: [];
        }
    }
    
    // Log the incoming request
    $requestData = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'parameters' => $postData,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // Validate required parameters
    $requiredParams = ['client_phone_nr', 'client_full_name'];
    $missingParams = [];
    
    foreach ($requiredParams as $param) {
        if (!isset($postData[$param]) || trim($postData[$param]) === '') {
            $missingParams[] = $param;
        }
    }
    
    if (!empty($missingParams)) {
        throw new Exception("Missing required parameters: " . implode(', ', $missingParams));
    }
    
    // Extract and clean parameters
    $clientPhoneNr = trim($postData['client_phone_nr']);
    $clientFullName = trim($postData['client_full_name']);
    
    // Clean phone number by removing dots, plus signs, and spaces
    $cleanPhoneNumber = str_replace(['.', '+', ' '], '', $clientPhoneNr);
    
    // Validate phone number has at least 8 digits
    $digitsOnly = preg_replace('/[^0-9]/', '', $cleanPhoneNumber);
    if (strlen($digitsOnly) < 8) {
        throw new Exception("Client phone number must have at least 8 digits after cleaning");
    }
    
    // Extract only the last 8 digits for matching
    $last8Digits = substr($digitsOnly, -8);
    
    // Query conversation_memory table to get the last 3 records for this client
    $stmt = $pdo->prepare("
        SELECT * FROM conversation_memory 
        WHERE RIGHT(REPLACE(REPLACE(REPLACE(client_phone_nr, ' ', ''), '.', ''), '+', ''), 8) = ?
        AND client_full_name = ?
        ORDER BY dat_time DESC 
        LIMIT 3
    ");
    
    $stmt->execute([$last8Digits, $clientFullName]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count of records for this client (for informational purposes)
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM conversation_memory 
        WHERE RIGHT(REPLACE(REPLACE(REPLACE(client_phone_nr, ' ', ''), '.', ''), '+', ''), 8) = ?
        AND client_full_name = ?
    ");
    
    $countStmt->execute([$last8Digits, $clientFullName]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Prepare response data
    $responseData = [
        'client_phone_nr' => $clientPhoneNr,
        'client_full_name' => $clientFullName,
        'last_8_digits_matched' => $last8Digits,
        'total_records_found' => (int)$totalCount,
        'records_returned' => count($records),
        'last_3_records' => $records,
        'search_criteria' => [
            'phone_number_cleaned' => $cleanPhoneNumber,
            'last_8_digits' => $last8Digits,
            'full_name_exact' => $clientFullName
        ]
    ];
    
    // Return success response
    $response = [
        'status' => 'success',
        'message' => 'Past conversation memory retrieved successfully',
        'data' => $responseData,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
    // Log successful operation
    $logger->logSuccess(json_encode($response), null, ['additional_data' => $requestData]);
    
} catch (Exception $e) {
    // Log the error
    $errorData = [
        'error' => $e->getMessage(),
        'parameters' => $postData ?? [],
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
}
?>
