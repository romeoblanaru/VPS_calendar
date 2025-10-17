<?php
/**
 * Insert VPN IP Address Webhook
 * 
 * This webhook inserts new VPN IP address records into the ip_address table.
 * It accepts POST requests with the required VPN configuration data.
 * 
 * Required POST Parameters:
 * - 'ip_address': The IP address to insert (must be unique)
 * - 'phone_number': The phone number associated with this IP
 * - 'vpn_public_key': The VPN public key for this configuration
 * - 'notes': Optional notes about this configuration
 * 
 * Returns:
 * - If successful: Success message with inserted record details
 * - If error: Error message with details
 * - If IP already exists: Error message indicating duplicate IP
 */

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection
require_once '../includes/db.php';

// Include webhook logger
require_once '../includes/webhook_logger.php';

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'insert_vpn_ip');

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
    $requiredParams = ['ip_address', 'phone_number', 'vpn_public_key'];
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
    $ipAddress = trim($postData['ip_address']);
    $phoneNumber = trim($postData['phone_number']);
    $vpnPublicKey = trim($postData['vpn_public_key']);
    $notes = isset($postData['notes']) ? trim($postData['notes']) : '';
    
    // Clean phone number by removing dots, plus signs, and spaces before storing
    $phoneNumber = str_replace(['.', '+', ' '], '', $phoneNumber);
    
    // Remove whitespace from IP address
    $ipAddress = preg_replace('/[\s\t\n\r]+/', '', $ipAddress);
    
    // URL decode the IP address
    $ipAddress = urldecode($ipAddress);
    
    // Validate IP address format
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        throw new Exception("Invalid IP address format: $ipAddress");
    }
    
    // Check if IP address already exists
    $stmt = $pdo->prepare("SELECT id, phone_number FROM ip_address WHERE ip_address = ?");
    $stmt->execute([$ipAddress]);
    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingRecord) {
        throw new Exception("IP address $ipAddress already exists and is associated with phone number: " . $existingRecord['phone_number']);
    }
    
    // Clean phone number for validation (remove dots, plus signs, etc.)
    $cleanPhoneNumber = preg_replace('/[^0-9]/', '', str_replace(['.', '+'], '', $phoneNumber));
    
    // Validate phone number has at least 8 digits
    if (strlen($cleanPhoneNumber) < 8) {
        throw new Exception("Phone number must have at least 8 digits after cleaning");
    }
    
    // Check if phone number already exists (optional - you can remove this if you want to allow multiple IPs per phone)
    $stmt = $pdo->prepare("SELECT id, ip_address FROM ip_address WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone_number, ' ', ''), '.', ''), '+', ''), 8) = ?");
    $last8Digits = substr($cleanPhoneNumber, -8);
    $stmt->execute([$last8Digits]);
    $existingPhone = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingPhone) {
        throw new Exception("Phone number ending with $last8Digits already exists and is associated with IP: " . $existingPhone['ip_address']);
    }
    
    // Validate VPN public key is not empty
    if (empty($vpnPublicKey)) {
        throw new Exception("VPN public key cannot be empty");
    }
    
    // Insert the new record
    $stmt = $pdo->prepare("INSERT INTO ip_address (ip_address, phone_number, vpn_private_key, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$ipAddress, $phoneNumber, $vpnPublicKey, $notes]);
    
    $insertedId = $pdo->lastInsertId();
    
    // Prepare additional data for logging
    $additionalData = [
        'ip_address' => $ipAddress,
        'phone_number' => $phoneNumber,
        'vpn_public_key_length' => strlen($vpnPublicKey),
        'notes' => $notes,
        'inserted_id' => $insertedId
    ];
    
    // Return success response
    $response = [
        'status' => 'success',
        'message' => 'VPN IP address record inserted successfully',
        'data' => [
            'id' => $insertedId,
            'ip_address' => $ipAddress,
            'phone_number' => $phoneNumber,
            'notes' => $notes,
            'date_inserted' => date('Y-m-d H:i:s')
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
    // Log successful insertion
    $logger->logSuccess(json_encode($response), null, ['additional_data' => $additionalData]);
    
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
