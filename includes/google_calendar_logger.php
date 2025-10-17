<?php
/**
 * Enhanced Google Calendar Logger
 * Provides detailed logging for Google Calendar operations
 */

class GoogleCalendarLogger {
    private $logFile;
    private $errorLogFile;
    private $verbose;
    
    public function __construct($verbose = false) {
        $this->logFile = '/srv/project_1/calendar/logs/google-calendar-worker.log';
        $this->errorLogFile = '/srv/project_1/calendar/logs/google-calendar-worker-error.log';
        $this->verbose = $verbose;
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }
    
    /**
     * Log a message with timestamp and category
     */
    public function log($message, $category = 'INFO', $isError = false) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$category] $message\n";
        
        // Write to appropriate log file
        $targetFile = $isError ? $this->errorLogFile : $this->logFile;
        file_put_contents($targetFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also output to console if in verbose mode
        if ($this->verbose) {
            echo $logEntry;
        }
    }
    
    /**
     * Log detailed operation info
     */
    public function logOperation($operation, $bookingId, $details = []) {
        $message = "Operation: $operation | Booking ID: $bookingId";
        
        if (!empty($details)) {
            $detailsStr = [];
            foreach ($details as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_PRETTY_PRINT);
                }
                $detailsStr[] = "$key: $value";
            }
            $message .= " | " . implode(' | ', $detailsStr);
        }
        
        $this->log($message, 'OPERATION');
    }
    
    /**
     * Log API request details
     */
    public function logApiRequest($method, $url, $data = null, $headers = null) {
        $message = "API Request: $method $url";
        
        if ($data) {
            $message .= "\n  Request Body: " . json_encode($data, JSON_PRETTY_PRINT);
        }
        
        if ($headers) {
            $message .= "\n  Headers: " . json_encode($headers, JSON_PRETTY_PRINT);
        }
        
        $this->log($message, 'API_REQUEST');
    }
    
    /**
     * Log API response details
     */
    public function logApiResponse($statusCode, $response, $error = null) {
        $message = "API Response: Status $statusCode";
        
        if ($error) {
            $message .= "\n  Error: $error";
            $this->log($message, 'API_ERROR', true);
        } else {
            if (is_string($response)) {
                $decoded = json_decode($response, true);
                if ($decoded) {
                    $response = $decoded;
                }
            }
            
            if (is_array($response)) {
                $message .= "\n  Response Data: " . json_encode($response, JSON_PRETTY_PRINT);
            } else {
                $message .= "\n  Response: $response";
            }
            
            $this->log($message, 'API_RESPONSE');
        }
    }
    
    /**
     * Log webhook activity
     */
    public function logWebhook($event, $bookingId, $payload, $response = null) {
        $message = "Webhook Event: $event | Booking ID: $bookingId";
        $message .= "\n  Payload: " . json_encode($payload, JSON_PRETTY_PRINT);
        
        if ($response) {
            $message .= "\n  Response: " . (is_array($response) ? json_encode($response, JSON_PRETTY_PRINT) : $response);
        }
        
        $this->log($message, 'WEBHOOK');
    }
    
    /**
     * Log queue processing
     */
    public function logQueue($action, $details = []) {
        $message = "Queue: $action";
        
        if (!empty($details)) {
            foreach ($details as $key => $value) {
                $message .= " | $key: $value";
            }
        }
        
        $this->log($message, 'QUEUE');
    }
    
    /**
     * Log deletion operations with extra detail
     */
    public function logDeletion($bookingId, $eventId, $status, $details = []) {
        $message = "DELETION: Booking $bookingId | Google Event ID: $eventId | Status: $status";
        
        if (!empty($details)) {
            foreach ($details as $key => $value) {
                $message .= "\n  $key: $value";
            }
        }
        
        $this->log($message, 'DELETE');
    }
    
    /**
     * Log error with context
     */
    public function logError($operation, $error, $context = []) {
        $message = "ERROR in $operation: $error";
        
        if (!empty($context)) {
            $message .= "\n  Context: " . json_encode($context, JSON_PRETTY_PRINT);
        }
        
        $this->log($message, 'ERROR', true);
    }
    
    /**
     * Log success with details
     */
    public function logSuccess($operation, $details = []) {
        $message = "SUCCESS: $operation";
        
        if (!empty($details)) {
            foreach ($details as $key => $value) {
                $message .= " | $key: $value";
            }
        }
        
        $this->log($message, 'SUCCESS');
    }
}