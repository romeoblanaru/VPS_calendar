<?php
/**
 * Check IP Address Webhook
 * 
 * This webhook checks IP addresses and phone numbers against the ip_address table.
 * It can be used to verify if an IP address or phone number is already in use.
 * 
 * Parameters:
 * - 'ip': Check if IP address exists and return associated phone number
 * - 'nr': Check if phone number exists and return associated IP address
 * 
 * Returns:
 * - If found: The associated data (phone number or IP address)
 * - If not found: null (indicating the value is unique and available)
 * - If error: Error message with details
 */

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection
require_once '../includes/db.php';

// Include webhook logger
require_once '../includes/webhook_logger.php';

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'check_ip');

try {
    // Get request method
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Get parameters based on method
    if ($method === 'GET') {
        $params = $_GET;
    } elseif ($method === 'POST') {
        // Handle both form data and JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $params = json_decode($input, true) ?: [];
        } else {
            $params = $_POST;
        }
    } else {
        throw new Exception("Unsupported method: $method");
    }
    
    // Log the incoming request (using additional_data for request details)
    $requestData = [
        'method' => $method,
        'parameters' => $params,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // Check parameters
    $paramCount = count($params);
    
    // Handle list all functionality
    if ($paramCount === 0 || (isset($params['list']) && $params['list'] === 'all')) {
        // Query all IP addresses with phone numbers and client names
        $stmt = $pdo->prepare("SELECT ip_address, phone_number, notes AS client_name FROM ip_address ORDER BY ip_address");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format phone numbers in results
        foreach ($results as &$result) {
            if (!empty($result['phone_number'])) {
                // Remove + sign and any non-numeric characters
                $cleanPhone = preg_replace('/[^0-9]/', '', $result['phone_number']);
                
                // Format from right to left: last 3 digits, then 4 digits, then rest
                $phoneLen = strlen($cleanPhone);
                if ($phoneLen >= 7) {
                    $lastPart = substr($cleanPhone, -3);           // last 3 digits
                    $middlePart = substr($cleanPhone, -7, 4);      // 4 digits before the last 3
                    $firstPart = substr($cleanPhone, 0, $phoneLen - 7);  // remaining digits
                    
                    $formatted = $firstPart . ' ' . $middlePart . ' ' . $lastPart;
                    $formatted = trim($formatted); // Remove leading space if firstPart is empty
                    $result['phone_number'] = $formatted;
                }
            }
        }
        
        // Prepare additional data for logging
        $additionalData = [
            'action' => 'list_all',
            'count' => count($results)
        ];
        
        $response = [
            'status' => 'success',
            'action' => 'list_all',
            'count' => count($results),
            'results' => $results,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response);
        
        // Log successful response
        $logger->logSuccess(json_encode($response), null, ['additional_data' => $additionalData]);
        exit;
    }
    
    // For individual lookups, we need exactly one parameter (ip or nr)
    if ($paramCount !== 1 || (!isset($params['ip']) && !isset($params['nr']))) {
        throw new Exception("For individual lookups, exactly one parameter required. Use either 'ip' or 'nr'. For full list, use no parameters or 'list=all'.");
    }
    
    // Check if parameter is 'ip'
    if (isset($params['ip'])) {
        $ip = trim($params['ip']);
        
        // Additional trim to remove any remaining whitespace (including tabs, newlines, etc.)
        $ip = preg_replace('/[\s\t\n\r]+/', '', $ip);
        
        // URL decode the IP address in case it's encoded
        $ip = urldecode($ip);
        
        // Validate IP address format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new Exception("Invalid IP address format: $ip");
        }
        
        // Query database for IP address
        $stmt = $pdo->prepare("SELECT phone_number, notes AS client_name FROM ip_address WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Prepare additional data for logging
        $additionalData = [
            'ip' => $ip,
            'found' => $result ? true : false,
            'phone_number' => $result['phone_number'] ?? null
        ];
        
        // Return result
        if ($result) {
            $response = [
                'status' => 'success',
                'parameter' => 'ip',
                'value' => $ip,
                'result' => $result['phone_number'],
                'found' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            echo json_encode($response);
            // Log successful response
            $logger->logSuccess(json_encode($response), null, ['additional_data' => $additionalData]);
        } else {
            $response = [
                'status' => 'success',
                'parameter' => 'ip',
                'value' => $ip,
                'result' => null,
                'found' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            echo json_encode($response);
            // Log successful response (null result means not found, which is valid)
            $logger->logSuccess(json_encode($response), null, ['additional_data' => $additionalData]);
        }
        
    }
    // Check if parameter is 'nr'
    elseif (isset($params['nr'])) {
        $phoneNumber = trim($params['nr']);
        
        // URL decode the phone number in case it's encoded
        $phoneNumber = urldecode($phoneNumber);
        
        // Remove dots and plus signs from phone number (e.g., +123.456.7890 -> 1234567890)
        $phoneNumber = str_replace(['.', '+'], '', $phoneNumber);
        
        // Validate phone number (basic validation)
        if (empty($phoneNumber)) {
            throw new Exception("Phone number cannot be empty");
        }
        
        // Clean phone number to get last 9 digits (remove all non-numeric characters)
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (strlen($cleanNumber) < 9) {
            throw new Exception("Phone number must have at least 9 digits");
        }
        
        $last9Digits = substr($cleanNumber, -9);
        
        // Query database for phone number (matching last 9 digits)
        // Remove spaces, dots, and plus signs from stored phone numbers before matching
        $stmt = $pdo->prepare("SELECT ip_address, phone_number, notes AS client_name FROM ip_address WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone_number, ' ', ''), '.', ''), '+', ''), 9) = ?");
        $stmt->execute([$last9Digits]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Prepare additional data for logging
        $additionalData = [
            'phone_number' => $phoneNumber,
            'last_9_digits' => $last9Digits,
            'found' => $result ? true : false,
            'ip_address' => $result['ip_address'] ?? null
        ];
        
        // Return result
        if ($result) {
            $response = [
                'status' => 'success',
                'parameter' => 'nr',
                'value' => $phoneNumber,
                'last_9_digits' => $last9Digits,
                'result' => $result['ip_address'],
                'found' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            echo json_encode($response);
            // Log successful response
            $logger->logSuccess(json_encode($response), null, ['additional_data' => $additionalData]);
        } else {
            $response = [
                'status' => 'success',
                'parameter' => 'nr',
                'value' => $phoneNumber,
                'last_9_digits' => $last9Digits,
                'result' => null,
                'found' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            echo json_encode($response);
            // Log successful response (null result means not found, which is valid)
            $logger->logSuccess(json_encode($response), null, ['additional_data' => $additionalData]);
        }
        
    } else {
        throw new Exception("Invalid parameter. Use either 'ip' or 'nr'.");
    }
    
} catch (Exception $e) {
    // Log the error using the logger
    $errorData = [
        'error' => $e->getMessage(),
        'parameters' => $params ?? [],
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
