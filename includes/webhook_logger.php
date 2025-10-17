<?php
/**
 * Webhook Logger Class
 * 
 * This class provides functionality to log all webhook calls to the database
 * with comprehensive information including request details, response data, and error handling.
 */

class WebhookLogger {
    private $pdo;
    private $startTime;
    private $webhookName;
    private $requestData;
    
    public function __construct($pdo, $webhookName) {
        $this->pdo = $pdo;
        $this->webhookName = $webhookName;
        $this->startTime = microtime(true);
        $this->requestData = $this->captureRequestData();
    }
    
    /**
     * Capture all request data
     */
    private function captureRequestData() {
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'url' => $this->getFullUrl(),
            'headers' => $this->getRequestHeaders(),
            'body' => $this->getRequestBody(),
            'params' => $this->getRequestParams(),
            'client_ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
    }
    
    /**
     * Get the full request URL
     */
    private function getFullUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * Get request headers
     */
    private function getRequestHeaders() {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('HTTP_', '', $key);
                $headerName = str_replace('_', '-', strtolower($headerName));
                $headers[$headerName] = $value;
            }
        }
        return json_encode($headers);
    }
    
    /**
     * Get request body
     */
    private function getRequestBody() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $input = file_get_contents('php://input');
            return $input ?: json_encode($_POST);
        }
        return null;
    }
    
    /**
     * Get request parameters
     */
    private function getRequestParams() {
        $params = array_merge($_GET, $_POST);
        return json_encode($params);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Log a successful webhook call
     */
    public function logSuccess($responseBody, $responseHeaders = null, $relatedIds = []) {
        $processingTime = round((microtime(true) - $this->startTime) * 1000);
        
        $this->insertLog([
            'response_status_code' => http_response_code() ?: 200,
            'response_body' => is_string($responseBody) ? $responseBody : json_encode($responseBody),
            'response_headers' => is_array($responseHeaders) ? json_encode($responseHeaders) : null,
            'processing_time_ms' => $processingTime,
            'is_successful' => 1,
            'processed_at' => date('Y-m-d H:i:s'),
            'related_booking_id' => $relatedIds['booking_id'] ?? null,
            'related_specialist_id' => $relatedIds['specialist_id'] ?? null,
            'related_organisation_id' => $relatedIds['organisation_id'] ?? null,
            'related_working_point_id' => $relatedIds['working_point_id'] ?? null,
            'additional_data' => isset($relatedIds['additional_data']) ? json_encode($relatedIds['additional_data']) : null
        ]);
    }
    
    /**
     * Log a failed webhook call
     */
    public function logError($errorMessage, $errorTrace = null, $responseStatusCode = 500, $relatedIds = []) {
        $processingTime = round((microtime(true) - $this->startTime) * 1000);
        
        $this->insertLog([
            'response_status_code' => $responseStatusCode,
            'response_body' => json_encode(['error' => $errorMessage]),
            'processing_time_ms' => $processingTime,
            'error_message' => $errorMessage,
            'error_trace' => $errorTrace,
            'is_successful' => 0,
            'processed_at' => date('Y-m-d H:i:s'),
            'related_booking_id' => $relatedIds['booking_id'] ?? null,
            'related_specialist_id' => $relatedIds['specialist_id'] ?? null,
            'related_organisation_id' => $relatedIds['organisation_id'] ?? null,
            'related_working_point_id' => $relatedIds['working_point_id'] ?? null,
            'additional_data' => isset($relatedIds['additional_data']) ? json_encode($relatedIds['additional_data']) : null
        ]);
    }
    
    /**
     * Insert log record into database
     */
    private function insertLog($data) {
        try {
            $sql = "INSERT INTO webhook_logs (
                webhook_name, request_method, request_url, request_headers, 
                request_body, request_params, client_ip, user_agent,
                response_status_code, response_body, response_headers,
                processing_time_ms, error_message, error_trace, created_at,
                processed_at, is_successful, related_booking_id, related_specialist_id,
                related_organisation_id, related_working_point_id, additional_data
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->webhookName,
                $this->requestData['method'],
                $this->requestData['url'],
                $this->requestData['headers'],
                $this->requestData['body'],
                $this->requestData['params'],
                $this->requestData['client_ip'],
                $this->requestData['user_agent'],
                $data['response_status_code'],
                $data['response_body'],
                $data['response_headers'] ?? null,
                $data['processing_time_ms'],
                $data['error_message'] ?? null,
                $data['error_trace'] ?? null,
                $data['processed_at'],
                $data['is_successful'],
                $data['related_booking_id'],
                $data['related_specialist_id'],
                $data['related_organisation_id'],
                $data['related_working_point_id'],
                $data['additional_data']
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            // Log to error log if database logging fails
            error_log("Failed to log webhook call: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get webhook statistics
     */
    public static function getStatistics($pdo, $webhookName = null, $days = 30) {
        $params = [];
        $days = (int)$days;
        if ($webhookName) {
            $whereClause = "WHERE webhook_name = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $params[] = $webhookName;
            $params[] = $days;
        } else {
            $whereClause = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $params[] = $days;
        }
        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN is_successful = 1 THEN 1 ELSE 0 END) as successful_calls,
                    SUM(CASE WHEN is_successful = 0 THEN 1 ELSE 0 END) as failed_calls,
                    AVG(processing_time_ms) as avg_processing_time,
                    MIN(created_at) as first_call,
                    MAX(created_at) as last_call
                FROM webhook_logs 
                $whereClause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent webhook calls
     */
    public static function getRecentCalls($pdo, $limit = 50, $webhookName = null) {
        $whereClause = $webhookName ? "WHERE webhook_name = ?" : "";
        $params = $webhookName ? [$webhookName] : [];
        $limit = (int)$limit;
        $sql = "SELECT * FROM webhook_logs 
                $whereClause 
                ORDER BY created_at DESC 
                LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clean old webhook logs (older than specified days)
     */
    public static function cleanOldLogs($pdo, $days = 90) {
        $sql = "DELETE FROM webhook_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([(int)$days]);
    }
}
?> 