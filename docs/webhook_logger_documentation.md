# WebhookLogger Class Documentation

## Overview

The `WebhookLogger` class is a comprehensive logging system designed to track and monitor all webhook calls in the nuuitasi_calendar4 system. It provides detailed logging of request/response data, performance metrics, error handling, and statistical analysis capabilities.

**File Location:** `includes/webhook_logger.php`

## Features

- **Comprehensive Request Logging**: Captures all request details including headers, body, parameters, and client information
- **Response Tracking**: Logs response status codes, body content, and processing times
- **Error Handling**: Detailed error logging with stack traces
- **Performance Monitoring**: Tracks processing time and performance metrics
- **Related Entity Tracking**: Links webhook calls to relevant database entities (bookings, specialists, etc.)
- **Statistical Analysis**: Built-in methods for generating webhook statistics
- **Data Cleanup**: Automated cleanup of old log entries

## Class Structure

### Constructor

```php
WebhookLogger($pdo, $webhookName)
```

**Parameters:**
- `$pdo` (PDO): Database connection object
- `$webhookName` (string): Name/identifier of the webhook endpoint

**Example:**
```php
$logger = new WebhookLogger($pdo, 'gathering_initial_information');
```

## Methods

### 1. logSuccess()

Logs a successful webhook call with response data and related entity information.

```php
logSuccess($responseBody, $responseHeaders = null, $relatedIds = [])
```

**Parameters:**
- `$responseBody` (mixed): Response data (string or array/object that will be JSON encoded)
- `$responseHeaders` (array|null): Optional response headers
- `$relatedIds` (array): Array of related entity IDs and additional data

**Related IDs Array Structure:**
```php
$relatedIds = [
    'booking_id' => 123,
    'specialist_id' => 456,
    'organisation_id' => 789,
    'working_point_id' => 101,
    'additional_data' => [
        'custom_field' => 'value',
        'specialists_count' => 5,
        'services_count' => 10
    ]
];
```

**Example:**
```php
$logger->logSuccess($responseData, null, [
    'organisation_id' => $workingPoint['org_id'],
    'working_point_id' => $workingPoint['unic_id'],
    'additional_data' => [
        'specialists_count' => count($specialists),
        'services_count' => count($services),
        'client_phone_provided' => !empty($client_phone_nr)
    ]
]);
```

### 2. logError()

Logs a failed webhook call with error details and stack trace.

```php
logError($errorMessage, $errorTrace = null, $responseStatusCode = 500, $relatedIds = [])
```

**Parameters:**
- `$errorMessage` (string): Human-readable error message
- `$errorTrace` (string|null): Full error stack trace
- `$responseStatusCode` (int): HTTP response status code (default: 500)
- `$relatedIds` (array): Array of related entity IDs (same structure as logSuccess)

**Example:**
```php
try {
    // Webhook logic here
} catch (Exception $e) {
    $logger->logError(
        'Database connection failed: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500,
        ['organisation_id' => $orgId]
    );
}
```

### 3. getStatistics() (Static Method)

Retrieves webhook statistics for analysis and monitoring.

```php
WebhookLogger::getStatistics($pdo, $webhookName = null, $days = 30)
```

**Parameters:**
- `$pdo` (PDO): Database connection object
- `$webhookName` (string|null): Specific webhook name to filter by (null for all)
- `$days` (int): Number of days to look back (default: 30)

**Returns:** Array with statistics
```php
[
    'total_calls' => 150,
    'successful_calls' => 145,
    'failed_calls' => 5,
    'avg_processing_time' => 125.5,
    'first_call' => '2025-07-01 10:00:00',
    'last_call' => '2025-07-15 15:30:00'
]
```

**Example:**
```php
// Get statistics for all webhooks in last 30 days
$stats = WebhookLogger::getStatistics($pdo);

// Get statistics for specific webhook in last 7 days
$stats = WebhookLogger::getStatistics($pdo, 'gathering_initial_information', 7);
```

### 4. getRecentCalls() (Static Method)

Retrieves recent webhook calls for monitoring and debugging.

```php
WebhookLogger::getRecentCalls($pdo, $limit = 50, $webhookName = null)
```

**Parameters:**
- `$pdo` (PDO): Database connection object
- `$limit` (int): Maximum number of records to return (default: 50)
- `$webhookName` (string|null): Specific webhook name to filter by (null for all)

**Returns:** Array of webhook log records

**Example:**
```php
// Get last 20 calls for all webhooks
$recentCalls = WebhookLogger::getRecentCalls($pdo, 20);

// Get last 10 calls for specific webhook
$recentCalls = WebhookLogger::getRecentCalls($pdo, 10, 'gathering_initial_information');
```

### 5. cleanOldLogs() (Static Method)

Removes old webhook logs to maintain database performance.

```php
WebhookLogger::cleanOldLogs($pdo, $days = 90)
```

**Parameters:**
- `$pdo` (PDO): Database connection object
- `$days` (int): Age threshold in days (default: 90)

**Returns:** Boolean indicating success/failure

**Example:**
```php
// Remove logs older than 90 days
$success = WebhookLogger::cleanOldLogs($pdo);

// Remove logs older than 30 days
$success = WebhookLogger::cleanOldLogs($pdo, 30);
```

## Database Schema

The logger uses the `webhook_logs` table with the following structure:

| Field | Type | Description |
|-------|------|-------------|
| `id` | int(11) | Primary key, auto-increment |
| `webhook_name` | varchar(100) | Name of the webhook endpoint |
| `request_method` | varchar(10) | HTTP method (GET, POST, etc.) |
| `request_url` | text | Full request URL |
| `request_headers` | text | JSON encoded request headers |
| `request_body` | text | Request body content |
| `request_params` | text | JSON encoded request parameters |
| `client_ip` | varchar(45) | Client IP address |
| `user_agent` | text | User agent string |
| `response_status_code` | int(3) | HTTP response status code |
| `response_body` | text | Response body content |
| `response_headers` | text | JSON encoded response headers |
| `processing_time_ms` | int(11) | Processing time in milliseconds |
| `error_message` | text | Error message if any |
| `error_trace` | text | Full error stack trace |
| `created_at` | timestamp | When the webhook call was received |
| `processed_at` | timestamp | When processing completed |
| `is_successful` | tinyint(1) | Whether the call was successful |
| `related_booking_id` | int(11) | Related booking ID |
| `related_specialist_id` | int(11) | Related specialist ID |
| `related_organisation_id` | int(11) | Related organisation ID |
| `related_working_point_id` | int(11) | Related working point ID |
| `additional_data` | text | JSON encoded additional data |

## Integration Examples

### Basic Integration

```php
<?php
require_once '../includes/db.php';
require_once '../includes/webhook_logger.php';

// Initialize logger
$logger = new WebhookLogger($pdo, 'my_webhook');

try {
    // Your webhook logic here
    $responseData = ['status' => 'success', 'data' => $result];
    
    // Log successful call
    $logger->logSuccess($responseData);
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    // Log error
    $logger->logError($e->getMessage(), $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
```

### Advanced Integration with Related Entities

```php
<?php
require_once '../includes/db.php';
require_once '../includes/webhook_logger.php';

$logger = new WebhookLogger($pdo, 'booking_webhook');

try {
    // Process booking request
    $bookingId = createBooking($requestData);
    $specialistId = $requestData['specialist_id'];
    $workingPointId = $requestData['working_point_id'];
    
    $responseData = [
        'status' => 'success',
        'booking_id' => $bookingId,
        'message' => 'Booking created successfully'
    ];
    
    // Log with related entity information
    $logger->logSuccess($responseData, null, [
        'booking_id' => $bookingId,
        'specialist_id' => $specialistId,
        'working_point_id' => $workingPointId,
        'additional_data' => [
            'service_duration' => $requestData['duration'],
            'client_phone' => $requestData['client_phone'],
            'booking_date' => $requestData['booking_date']
        ]
    ]);
    
    echo json_encode($responseData);
    
} catch (DatabaseException $e) {
    $logger->logError(
        'Database error: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500,
        ['working_point_id' => $requestData['working_point_id'] ?? null]
    );
    
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
    
} catch (ValidationException $e) {
    $logger->logError(
        'Validation error: ' . $e->getMessage(),
        null,
        400
    );
    
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
```

### Monitoring and Statistics

```php
<?php
// Get webhook statistics for dashboard
$stats = WebhookLogger::getStatistics($pdo, 'gathering_initial_information', 30);

echo "Total calls: " . $stats['total_calls'] . "\n";
echo "Success rate: " . round(($stats['successful_calls'] / $stats['total_calls']) * 100, 2) . "%\n";
echo "Average processing time: " . round($stats['avg_processing_time']) . "ms\n";

// Get recent failed calls for debugging
$recentCalls = WebhookLogger::getRecentCalls($pdo, 10, 'gathering_initial_information');
$failedCalls = array_filter($recentCalls, function($call) {
    return $call['is_successful'] == 0;
});

foreach ($failedCalls as $call) {
    echo "Failed call at " . $call['created_at'] . ": " . $call['error_message'] . "\n";
}
?>
```

## Best Practices

### 1. Error Handling
- Always wrap webhook logic in try-catch blocks
- Log both successful and failed calls
- Include relevant entity IDs for better tracking

### 2. Performance
- The logger automatically captures processing time
- Use the `cleanOldLogs()` method regularly to maintain performance
- Consider implementing log rotation for high-traffic systems

### 3. Security
- Sensitive data in request/response bodies is logged - ensure proper data sanitization
- Consider implementing log encryption for sensitive webhooks
- Regularly review and clean old logs

### 4. Monitoring
- Set up alerts for high error rates
- Monitor average processing times
- Track webhook usage patterns

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Ensure the PDO connection is properly configured
   - Check database permissions for the webhook_logs table

2. **Performance Issues**
   - Regularly clean old logs using `cleanOldLogs()`
   - Monitor table size and index usage
   - Consider archiving old logs to separate tables

3. **Missing Logs**
   - Verify the logger is properly included in webhook files
   - Check that the webhook_logs table exists and has proper permissions
   - Ensure error logging doesn't interfere with webhook execution

### Debug Mode

For debugging, you can temporarily add more detailed logging:

```php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add debug information to logs
$logger->logSuccess($responseData, null, [
    'additional_data' => [
        'debug_info' => [
            'memory_usage' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage(),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ]
    ]
]);
```

## Version History

- **v1.0** - Initial release with basic logging functionality
- **v1.1** - Added statistical analysis methods
- **v1.2** - Enhanced error handling and performance monitoring
- **v1.3** - Added data cleanup functionality and admin interface integration

## Support

For issues or questions regarding the WebhookLogger class:
1. Check the database logs for any connection issues
2. Verify the webhook_logs table structure
3. Review the error messages in the webhook logs
4. Contact the development team for complex issues 