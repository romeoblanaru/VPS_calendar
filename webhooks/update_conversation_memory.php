<?php
/**
 * Update Conversation Memory Webhook
 * 
 * This webhook updates records in the conversation_memory table.
 * It accepts POST requests with conversation memory data and can perform
 * insert, update, or delete operations based on the action parameter.
 * 
 * Required POST Parameters:
 * - 'action': The action to perform ('insert', 'update', 'delete', 'get')
 * 
 * For INSERT actions, also required:
 * - 'client_full_name': Full name of the client
 * - 'client_phone_nr': Phone number of the client
 * - 'conversation': Content of the conversation
 * - 'calee_phone_nr': Phone number of the working point (used to resolve workplace_id and workplace_name)
 * - 'source': Source of the conversation ('SMS', 'phone', 'facebook', 'whatsapp', etc.)
 * 
 * Optional Parameters:
 * - 'dat_time': Custom timestamp (defaults to current time)
 * - 'finalized_action': Action that was finalized from the conversation
 * - 'lenght': Length of the conversation (word count, duration, etc.)
 * - 'conversation_summary': AI-generated summary of the conversation for quick reference
 * - 'booked_phone_nr': Phone number provided by client for booking (may differ from client_phone_nr)
 * 
 * For UPDATE actions, also required:
 * - 'id': ID of the record to update
 * - At least one field to update
 * 
 * For GET actions, also required:
 * - 'id': ID of the record to retrieve
 * 
 * Returns:
 * - If successful: JSON payload with operation result
 * - If error: Error message with details
 */

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection
require_once '../includes/db.php';

// Include webhook logger
require_once '../includes/webhook_logger.php';

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'update_conversation_memory');

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
    $requiredParams = ['action'];
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
    $action = strtolower(trim($postData['action']));
    
    // Validate action
    $validActions = ['insert', 'update', 'delete', 'get'];
    if (!in_array($action, $validActions)) {
        throw new Exception("Invalid action. Must be one of: " . implode(', ', $validActions));
    }
    
    // Perform the requested action
    switch ($action) {
        case 'insert':
            $result = performInsert($pdo, $postData, $logger);
            break;
            
        case 'update':
            $result = performUpdate($pdo, $postData, $logger);
            break;
            
        case 'delete':
            $result = performDelete($pdo, $postData, $logger);
            break;
            
        case 'get':
            $result = performGet($pdo, $postData, $logger);
            break;
            
        default:
            throw new Exception("Unknown action: $action");
    }
    
    // Return success response
    $response = [
        'status' => 'success',
        'message' => "Conversation memory $action operation completed successfully",
        'data' => $result,
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

/**
 * Resolve workplace information from working_points table using calee_phone_nr
 */
function resolveWorkplaceInfo($pdo, $caleePhoneNr) {
    // Clean the phone number for comparison
    $cleanCaleePhone = str_replace(['.', '+', ' '], '', $caleePhoneNr);
    
    // Extract only the last 8 digits for matching
    $last8Digits = substr($cleanCaleePhone, -8);
    
    // Query working_points table to get workplace information
    // Match by comparing the last 8 digits of both phone numbers
    $stmt = $pdo->prepare("
        SELECT unic_id, name_of_the_place 
        FROM working_points 
        WHERE RIGHT(REPLACE(REPLACE(REPLACE(booking_phone_nr, ' ', ''), '.', ''), '+', ''), 8) = ?
    ");
    $stmt->execute([$last8Digits]);
    $workplace = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workplace) {
        throw new Exception("No working point found with booking phone number ending in: $last8Digits");
    }
    
    return [
        'worplace_id' => (int)$workplace['unic_id'],
        'workplace_name' => $workplace['name_of_the_place']
    ];
}

/**
 * Perform INSERT operation
 */
function performInsert($pdo, $postData, $logger) {
    // Validate required parameters for insert
    $requiredParams = ['client_full_name', 'client_phone_nr', 'conversation', 'calee_phone_nr', 'source'];
    $missingParams = [];
    
    foreach ($requiredParams as $param) {
        if (!isset($postData[$param]) || trim($postData[$param]) === '') {
            $missingParams[] = $param;
        }
    }
    
    if (!empty($missingParams)) {
        throw new Exception("Missing required parameters for insert: " . implode(', ', $missingParams));
    }
    
    // Extract parameters
    $clientFullName = trim($postData['client_full_name']);
    $clientPhoneNr = trim($postData['client_phone_nr']);
    $conversation = trim($postData['conversation']);
    $caleePhoneNr = trim($postData['calee_phone_nr']);
    $source = trim($postData['source']);
    $datTime = isset($postData['dat_time']) ? trim($postData['dat_time']) : date('Y-m-d H:i:s');
    $finalizedAction = isset($postData['finalized_action']) ? trim($postData['finalized_action']) : null;
    $lenght = isset($postData['lenght']) ? (int)$postData['lenght'] : null;
    $conversationSummary = isset($postData['conversation_summary']) ? trim($postData['conversation_summary']) : null;
    $bookedPhoneNr = isset($postData['booked_phone_nr']) ? trim($postData['booked_phone_nr']) : null;
    
    // Clean client phone number by removing dots, plus signs, and spaces before storing
    $clientPhoneNr = str_replace(['.', '+', ' '], '', $clientPhoneNr);
    
    // Validate client phone number has at least 8 digits
    $cleanPhoneNumber = preg_replace('/[^0-9]/', '', $clientPhoneNr);
    if (strlen($cleanPhoneNumber) < 8) {
        throw new Exception("Client phone number must have at least 8 digits after cleaning");
    }
    
    // Clean booked phone number if provided
    if ($bookedPhoneNr !== null && $bookedPhoneNr !== '') {
        $bookedPhoneNr = str_replace(['.', '+', ' '], '', $bookedPhoneNr);
        
        // Validate booked phone number has at least 8 digits if provided
        $cleanBookedPhoneNumber = preg_replace('/[^0-9]/', '', $bookedPhoneNr);
        if (strlen($cleanBookedPhoneNumber) < 8) {
            throw new Exception("Booked phone number must have at least 8 digits after cleaning");
        }
    }

    // Truncate source to fit DB field (varchar(50))
    $source = substr($source, 0, 50);
    
    // Resolve workplace information from calee_phone_nr
    $workplaceInfo = resolveWorkplaceInfo($pdo, $caleePhoneNr);
    
    // Insert the new record
    $stmt = $pdo->prepare("
        INSERT INTO conversation_memory 
        (client_full_name, client_phone_nr, conversation, conversation_summary, dat_time, finalized_action, lenght, worplace_id, workplace_name, source, booked_phone_nr) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $clientFullName,
        $clientPhoneNr,
        $conversation,
        $conversationSummary,
        $datTime,
        $finalizedAction,
        $lenght,
        $workplaceInfo['worplace_id'],
        $workplaceInfo['workplace_name'],
        $source,
        $bookedPhoneNr
    ]);
    
    $insertedId = $pdo->lastInsertId();
    
    return [
        'id' => $insertedId,
        'client_full_name' => $clientFullName,
        'client_phone_nr' => $clientPhoneNr,
        'conversation' => $conversation,
        'conversation_summary' => $conversationSummary,
        'dat_time' => $datTime,
        'finalized_action' => $finalizedAction,
        'lenght' => $lenght,
        'calee_phone_nr' => $caleePhoneNr,
        'worplace_id' => $workplaceInfo['worplace_id'],
        'workplace_name' => $workplaceInfo['workplace_name'],
        'source' => $source,
        'booked_phone_nr' => $bookedPhoneNr
    ];
}

/**
 * Perform UPDATE operation
 */
function performUpdate($pdo, $postData, $logger) {
    // Validate required parameters for update
    $requiredParams = ['id'];
    $missingParams = [];
    
    foreach ($requiredParams as $param) {
        if (!isset($postData[$param]) || trim($postData[$param]) === '') {
            $missingParams[] = $param;
        }
    }
    
    if (!empty($missingParams)) {
        throw new Exception("Missing required parameters for update: " . implode(', ', $missingParams));
    }
    
    $id = (int)$postData['id'];
    
    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM conversation_memory WHERE id = ?");
    $stmt->execute([$id]);
    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingRecord) {
        throw new Exception("No conversation memory record found with id: $id");
    }
    
    // Build dynamic UPDATE query based on provided fields
    $updateFields = [];
    $updateValues = [];
    
    $fieldsToUpdate = [
        'client_full_name', 'client_phone_nr', 'conversation', 'conversation_summary', 'dat_time', 
        'finalized_action', 'lenght', 'booked_phone_nr'
    ];
    
    foreach ($fieldsToUpdate as $field) {
        if (isset($postData[$field])) {
            $updateFields[] = "$field = ?";
            $value = trim($postData[$field]);
            
            // Special handling for booked_phone_nr field
            if ($field === 'booked_phone_nr') {
                if ($value !== '' && $value !== null) {
                    // Clean booked phone number
                    $value = str_replace(['.', '+', ' '], '', $value);
                    
                    // Validate booked phone number has at least 8 digits if provided
                    $cleanBookedPhoneNumber = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($cleanBookedPhoneNumber) < 8) {
                        throw new Exception("Booked phone number must have at least 8 digits after cleaning");
                    }
                } else {
                    $value = null; // Set to null if empty string
                }
            }
            
            $updateValues[] = $value;
        }
    }
    
    // Handle calee_phone_nr update - this will update both worplace_id and workplace_name
    if (isset($postData['calee_phone_nr']) && trim($postData['calee_phone_nr']) !== '') {
        $caleePhoneNr = trim($postData['calee_phone_nr']);
        $workplaceInfo = resolveWorkplaceInfo($pdo, $caleePhoneNr);
        
        $updateFields[] = "worplace_id = ?";
        $updateFields[] = "workplace_name = ?";
        $updateValues[] = $workplaceInfo['worplace_id'];
        $updateValues[] = $workplaceInfo['workplace_name'];
    }

    // Handle source update
    if (isset($postData['source']) && trim($postData['source']) !== '') {
        $source = trim($postData['source']);
        // Truncate source to fit DB field (varchar(50))
        $source = substr($source, 0, 50);
        $updateFields[] = "source = ?";
        $updateValues[] = $source;
    }
    
    if (empty($updateFields)) {
        throw new Exception("No fields provided for update");
    }
    
    // Add ID to the end for WHERE clause
    $updateValues[] = $id;
    
    // Build and execute UPDATE query
    $updateQuery = "UPDATE conversation_memory SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $stmt->execute($updateValues);
    
    // Get updated record
    $stmt = $pdo->prepare("SELECT * FROM conversation_memory WHERE id = ?");
    $stmt->execute([$id]);
    $updatedRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'id' => $id,
        'updated_fields' => array_keys(array_filter($postData, function($key) use ($fieldsToUpdate) {
            return in_array($key, $fieldsToUpdate) || $key === 'calee_phone_nr' || $key === 'source';
        }, ARRAY_FILTER_USE_KEY)),
        'rows_affected' => $stmt->rowCount(),
        'updated_record' => $updatedRecord
    ];
}

/**
 * Perform DELETE operation
 */
function performDelete($pdo, $postData, $logger) {
    if (!isset($postData['id']) || trim($postData['id']) === '') {
        throw new Exception("Missing required parameter 'id' for delete operation");
    }
    
    $id = (int)$postData['id'];
    
    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM conversation_memory WHERE id = ?");
    $stmt->execute([$id]);
    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingRecord) {
        throw new Exception("No conversation memory record found with id: $id");
    }
    
    // Delete the record
    $stmt = $pdo->prepare("DELETE FROM conversation_memory WHERE id = ?");
    $stmt->execute([$id]);
    
    $rowsAffected = $stmt->rowCount();
    
    return [
        'id' => $id,
        'rows_deleted' => $rowsAffected,
        'message' => "Deleted conversation memory record with id: $id"
    ];
}

/**
 * Perform GET operation
 */
function performGet($pdo, $postData, $logger) {
    $limit = isset($postData['limit']) ? (int)$postData['limit'] : 100;
    $offset = isset($postData['offset']) ? (int)$postData['offset'] : 0;
    
    // Build WHERE clause based on filters
    $whereConditions = [];
    $whereValues = [];
    
    $possibleFilters = [
        'id', 'client_full_name', 'client_phone_nr', 'conversation_summary', 'finalized_action', 
        'worplace_id', 'workplace_name', 'source', 'booked_phone_nr'
    ];
    
    // Handle calee_phone_nr filter - convert to workplace_id lookup
    if (isset($postData['calee_phone_nr']) && trim($postData['calee_phone_nr']) !== '') {
        try {
            $caleePhoneNr = trim($postData['calee_phone_nr']);
            $workplaceInfo = resolveWorkplaceInfo($pdo, $caleePhoneNr);
            $whereConditions[] = "worplace_id = ?";
            $whereValues[] = $workplaceInfo['worplace_id'];
        } catch (Exception $e) {
            // If no workplace found, return empty result
            return [
                'records' => [],
                'total_records' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'filters_applied' => ['calee_phone_nr'],
                'note' => "No working point found for calee_phone_nr: " . $postData['calee_phone_nr']
            ];
        }
    }

    // Handle source filter
    if (isset($postData['source']) && trim($postData['source']) !== '') {
        $source = trim($postData['source']);
        // Truncate source to fit DB field (varchar(50)) for filtering
        $source = substr($source, 0, 50);
        $whereConditions[] = "source = ?";
        $whereValues[] = $source;
    }
    
    // Handle booked_phone_nr filter - clean the phone number for searching
    if (isset($postData['booked_phone_nr']) && trim($postData['booked_phone_nr']) !== '') {
        $bookedPhoneNr = trim($postData['booked_phone_nr']);
        // Clean the phone number for comparison
        $bookedPhoneNr = str_replace(['.', '+', ' '], '', $bookedPhoneNr);
        $whereConditions[] = "booked_phone_nr = ?";
        $whereValues[] = $bookedPhoneNr;
    }
    
    foreach ($possibleFilters as $filter) {
        // Skip filters that are handled specially
        if ($filter === 'source' || $filter === 'booked_phone_nr') {
            continue;
        }
        
        if (isset($postData[$filter]) && trim($postData[$filter]) !== '') {
            if ($filter === 'id' || $filter === 'worplace_id') {
                $whereConditions[] = "$filter = ?";
                $whereValues[] = (int)$postData[$filter];
            } else {
                $whereConditions[] = "$filter LIKE ?";
                $whereValues[] = '%' . trim($postData[$filter]) . '%';
            }
        }
    }
    
    // Build the query
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    $query = "
        SELECT * FROM conversation_memory 
        $whereClause
        ORDER BY dat_time DESC 
        LIMIT ? OFFSET ?
    ";
    
    // Add limit and offset to values
    $whereValues[] = $limit;
    $whereValues[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($whereValues);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM conversation_memory $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute(array_slice($whereValues, 0, -2)); // Remove limit and offset
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Build filters applied list
    $filtersApplied = array_keys(array_filter($postData, function($key) use ($possibleFilters) {
        return in_array($key, $possibleFilters) && trim($postData[$key]) !== '';
    }));
    
    // Add calee_phone_nr to filters if it was used
    if (isset($postData['calee_phone_nr']) && trim($postData['calee_phone_nr']) !== '') {
        $filtersApplied[] = 'calee_phone_nr';
    }

    // Add source to filters if it was used
    if (isset($postData['source']) && trim($postData['source']) !== '') {
        $filtersApplied[] = 'source';
    }

    // Add booked_phone_nr to filters if it was used
    if (isset($postData['booked_phone_nr']) && trim($postData['booked_phone_nr']) !== '') {
        $filtersApplied[] = 'booked_phone_nr';
    }
    
    return [
        'records' => $records,
        'total_records' => (int)$totalCount,
        'limit' => $limit,
        'offset' => $offset,
        'filters_applied' => $filtersApplied
    ];
}
?>
