# Booking Cancel Webhook Documentation

## Overview
The `booking_cancel.php` webhook handles the cancellation/removal of existing bookings in the calendar system. It accepts a booking ID and permanently removes the corresponding entry from the bookings table.

**Key Features**: 
- **Complete Booking Cancellation**: Handles booking removal with full audit trail
- **Data Backup**: Automatically backs up cancelled bookings to `booking_canceled` table
- **Cancellation Tracking**: Records who initiated the cancellation and when
- **Comprehensive Validation**: Ensures booking exists before cancellation
- **Full Logging**: All operations are logged using WebhookLogger
- **Multiple Request Methods**: Supports both GET and POST requests
- **JSON Response Format**: Structured responses for both success and error cases
- **Audit Compliance**: Maintains complete record of what was cancelled

## Endpoint

### URL
```
GET/POST: /webhooks/booking_cancel.php
```

### Method
- **GET** or **POST**

### Content-Type
```
application/json
```

### Authentication
None required

## Parameters

### Required Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `booking_id` | int | ID of the booking to cancel/remove | `123` |

### Optional Parameters

| Parameter | Type | Description | Example | Default |
|-----------|------|-------------|---------|---------|
| `made_by` | string | Who or what system cancelled the booking | `John Smith` | `NULL` |

## Parameter Details

### `booking_id` Field

- **Purpose**: Identifies the specific booking to be cancelled
- **Format**: Numeric integer value
- **Validation**: 
  - Must be provided and non-empty
  - Must be a numeric value
  - Will be converted to integer type
- **Database Field**: Matches against `booking.id` field

### `made_by` Field

- **Purpose**: Tracks who or what system initiated the cancellation
- **Format**: String value (up to 50 characters)
- **Examples**: 
  - Staff names: "John Smith", "Maria Garcia"
  - System names: "Admin Panel", "Mobile App", "API Integration"
  - User IDs: "user123", "admin001"
- **Validation**: Optional parameter, no length validation errors
- **Database Field**: Stored in `booking_canceled.made_by` field

## Usage Examples

### Basic Request (GET)
```
GET /webhooks/booking_cancel.php?booking_id=123
```

### Request with Cancellation Tracking (POST)
```
POST /webhooks/booking_cancel.php
Content-Type: application/x-www-form-urlencoded

booking_id=123&made_by=John Smith
```

### cURL Example
```bash
curl -X POST "http://yourdomain.com/webhooks/booking_cancel.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "booking_id=123&made_by=John Smith"
```

## Response Format

### Success Response (HTTP 200)
```json
{
    "status": "success",
    "message": "Booking cancelled successfully",
    "data": {
        "booking_id": 123,
        "rows_deleted": 1,
        "backup_id": 456,
        "cancelled_booking": {
            "id": "123",
            "unic_id": "BK20250120001",
            "specialist_name": "Dr. Smith",
            "working_point_name": "Downtown Office",
            "service_name": "Consultation",
            "client_full_name": "John Doe",
            "client_phone_nr": "37060012345",
            "booking_start_datetime": "2025-01-20 14:00:00",
            "booking_end_datetime": "2025-01-20 15:00:00",
            "received_through": "PHONE",
            "day_of_creation": "2025-01-15 16:30:00"
        },
        "cancellation_time": "2025-01-15 17:45:00",
        "made_by": "John Smith"
    },
    "timestamp": "2025-01-15 17:45:00"
}
```

### Error Response (HTTP 400)
```json
{
    "status": "error",
    "message": "Missing required parameter: booking_id",
    "timestamp": "2025-01-15 17:45:00"
}
```

### Error Response (HTTP 400 - Invalid ID)
```json
{
    "status": "error",
    "message": "Invalid booking_id. Must be a numeric value",
    "timestamp": "2025-01-15 17:45:00"
}
```

### Error Response (HTTP 400 - Not Found)
```json
{
    "status": "error",
    "message": "Booking not found with ID: 999",
    "timestamp": "2025-01-15 17:45:00"
}
```

### Error Response (HTTP 500)
```json
{
    "status": "error",
    "message": "Database error occurred while cancelling booking",
    "timestamp": "2025-01-15 17:45:00"
}
```

## Response Fields Explanation

### Main Response Structure
- **`status`**: Always "success" for successful operations
- **`message`**: Human-readable success message
- **`data`**: Contains all the cancellation details
- **`timestamp`**: When the response was generated

### Data Fields
- **`booking_id`**: The ID of the cancelled booking
- **`rows_deleted`**: Number of database rows affected (should be 1)
- **`backup_id`**: ID of the backup record in `booking_canceled` table
- **`cancelled_booking`**: Complete details of the cancelled booking
- **`cancellation_time`**: When the cancellation occurred
- **`made_by`**: Who or what system initiated the cancellation

### Cancelled Booking Fields
The `cancelled_booking` object contains:
- **`id`**: Original booking ID
- **`unic_id`**: Unique identifier (if available)
- **`specialist_name`**: Name of the specialist
- **`working_point_name`**: Name of the working point
- **`service_name`**: Name of the service
- **`client_full_name`**: Client's full name
- **`client_phone_nr`**: Client's phone number
- **`booking_start_datetime`**: Original start time
- **`booking_end_datetime`**: Original end time
- **`received_through`**: Source of the original booking
- **`day_of_creation`**: When the booking was originally created

## Business Logic

### Pre-Deletion Validation
1. **Parameter Validation**: Ensures `booking_id` is provided and numeric
2. **Existence Check**: Verifies the booking exists before attempting deletion
3. **Data Retrieval**: Fetches complete booking details for logging and response

### Backup Process
1. **Data Backup**: Creates a complete copy of the booking in `booking_canceled` table
2. **Cancellation Tracking**: Adds `cancellation_time` and `made_by` fields to backup
3. **Backup Verification**: Ensures backup was successful before proceeding with deletion

### Deletion Process
1. **Database Deletion**: Executes DELETE query on the booking table
2. **Row Count Verification**: Confirms that exactly one row was affected
3. **Response Generation**: Returns detailed information about the cancelled booking

### Post-Deletion Actions
1. **Success Logging**: Logs successful cancellation with all booking details
2. **Response Delivery**: Returns comprehensive cancellation information
3. **Audit Trail**: Maintains complete record of what was cancelled

## Error Handling

### HTTP Status Codes
- **200**: Booking cancelled successfully
- **400**: Bad request (missing/invalid parameters, booking not found)
- **500**: Internal server error (database issues)

### Error Response Structure
All errors include:
- `status`: Always "error"
- `message`: Human-readable error description
- `timestamp`: When the error occurred

### Common Error Scenarios
1. **Missing Parameter**: `booking_id` not provided
2. **Invalid ID**: `booking_id` is not numeric
3. **Booking Not Found**: No booking exists with the specified ID
4. **Backup Failure**: Failed to create backup in `booking_canceled` table
5. **Database Errors**: Connection or query failures
6. **Already Deleted**: Booking was already removed

## Webhook Logger Integration

This webhook includes comprehensive logging functionality using the WebhookLogger class:

### Logged Information
- **Request Details**: Method, URL, parameters, headers, client IP
- **Response Data**: Status codes, response body, processing time
- **Related Entities**: Booking ID, specialist ID, working point ID
- **Additional Data**: Backup record ID, cancellation details, who initiated

### Log Structure
```json
{
    "webhook_name": "booking_cancel",
    "request_method": "POST",
    "request_params": {
        "booking_id": 123,
        "made_by": "John Smith"
    },
    "response_status_code": 200,
    "related_booking_id": 123,
    "related_specialist_id": 5,
    "related_working_point_id": 3,
    "additional_data": {
        "booking_unique_id": "BK20250120001",
        "client_name": "John Doe",
        "client_phone": "37060012345",
        "booking_start": "2025-01-20 14:00:00",
        "booking_end": "2025-01-20 15:00:00",
        "received_through": "PHONE",
        "cancellation_time": "2025-01-15 17:45:00",
        "made_by": "John Smith",
        "backup_id": 456
    }
}
```

### Error Logging
- **Validation Errors**: Missing parameters, invalid booking IDs
- **Database Errors**: Connection issues, query failures
- **General Errors**: Unexpected exceptions with stack traces

### Monitoring
- All webhook calls are logged to the `webhook_logs` table
- Performance metrics are tracked for optimization
- Error patterns can be analyzed for system improvements

## Security Considerations

1. **Input Validation**: All parameters are validated before processing
2. **SQL Injection Protection**: Uses prepared statements for all database queries
3. **Existence Verification**: Confirms booking exists before deletion
4. **Error Logging**: Comprehensive error logging for debugging and monitoring
5. **Audit Trail**: Maintains complete record of cancelled bookings

## Performance Considerations

- **Efficient Queries**: Single SELECT to verify existence and get details
- **Prepared Statements**: Secure and efficient database operations
- **Minimal Database Calls**: Only two queries per cancellation (SELECT + DELETE)
- **Indexed Lookup**: Uses primary key for fast booking retrieval

## Testing

### Test Scenarios
1. **Valid Cancellation**: Test with existing booking ID
2. **Missing Parameter**: Test without booking_id
3. **Invalid ID**: Test with non-numeric booking_id
4. **Non-existent ID**: Test with booking ID that doesn't exist
5. **Already Deleted**: Test cancelling the same booking twice

### Example Test Cases
```bash
# Test 1: Valid cancellation
curl -X POST http://your-domain.com/webhooks/booking_cancel.php \
  -H "Content-Type: application/json" \
  -d '{"booking_id": 123}'

# Test 2: Missing parameter
curl -X POST http://your-domain.com/webhooks/booking_cancel.php \
  -H "Content-Type: application/json" \
  -d '{}'

# Test 3: Invalid ID
curl -X POST http://your-domain.com/webhooks/booking_cancel.php \
  -H "Content-Type: application/json" \
  -d '{"booking_id": "invalid"}'

# Test 4: Non-existent ID
curl -X POST http://your-domain.com/webhooks/booking_cancel.php \
  -H "Content-Type: application/json" \
  -d '{"booking_id": 99999}'
```

## Database Dependencies

This webhook depends on the following database tables:

1. **`booking`**: Main booking table (target for deletion)
2. **`booking_canceled`**: Backup table for cancelled bookings
3. **`specialists`**: Contains specialist information
4. **`working_points`**: Contains working point information
5. **`services`**: Contains service information

### Required Table Structure
```sql
-- Main booking table
CREATE TABLE booking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_specialist INT,
    id_work_place INT,
    service_id INT,
    client_full_name VARCHAR(255),
    client_phone_nr VARCHAR(20),
    -- other fields...
);

-- Backup table for cancelled bookings
CREATE TABLE booking_canceled (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_specialist INT,
    id_work_place INT,
    service_id INT,
    booking_start_datetime DATETIME,
    booking_end_datetime DATETIME,
    client_full_name VARCHAR(255),
    client_phone_nr VARCHAR(20),
    received_through VARCHAR(20),
    received_call_date DATETIME,
    client_transcript_conversation TEXT,
    day_of_creation DATETIME,
    unic_id VARCHAR(50),
    organisation_id INT,
    cancellation_time DATETIME NOT NULL,
    made_by VARCHAR(50),
    -- indexes and constraints...
);

-- Related tables for information retrieval
CREATE TABLE specialists (id INT, name VARCHAR(255), ...);
CREATE TABLE working_points (id INT, name_of_the_place VARCHAR(255), ...);
CREATE TABLE services (id INT, name_of_service VARCHAR(255), ...);
```

## Use Cases

### 1. Client Cancellation
- Client calls to cancel their appointment
- Staff uses webhook to remove the booking
- System logs the cancellation for audit purposes

### 2. Administrative Cancellation
- Staff needs to cancel a booking due to specialist unavailability
- Webhook provides complete audit trail
- All related information is preserved in logs

### 3. System Integration
- External systems can cancel bookings programmatically
- Consistent API response format
- Comprehensive error handling for integration scenarios

### 4. Audit and Compliance
- Complete record of what was cancelled
- Timestamp of cancellation
- Original booking details preserved in backup table
- Tracking of who initiated the cancellation
- Full audit trail for compliance requirements

## Integration Examples

### JavaScript/AJAX
```javascript
// Cancel a booking with tracking
fetch('/webhooks/booking_cancel.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
        booking_id: '123',
        made_by: 'John Smith'
    })
})
.then(response => response.json())
.then(data => {
    if (data.status === 'success') {
        console.log('Booking cancelled:', data.data.cancelled_booking.client_full_name);
        console.log('Backup ID:', data.data.backup_id);
        console.log('Cancelled by:', data.data.made_by);
    } else {
        console.error('Cancellation failed:', data.message);
    }
});
```

### PHP cURL
```php
// Cancel a booking with tracking
$data = [
    'booking_id' => '123',
    'made_by' => 'John Smith'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://yourdomain.com/webhooks/booking_cancel.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['status'] === 'success') {
    echo "Booking cancelled successfully";
    echo "Backup ID: " . $result['data']['backup_id'];
    echo "Cancelled by: " . $result['data']['made_by'];
} else {
    echo "Error: " . $result['message'];
}
```

## Support

For issues or questions regarding this webhook:
1. Check the webhook logs for detailed error information
2. Verify the booking_id parameter is provided and numeric
3. Ensure the booking exists in the database
4. Contact the development team for additional assistance

## Related Webhooks

- **`booking.php`**: Create new bookings
- **`update_conversation_memory.php`**: CRUD operations for conversation memory records
- **`get_past_conversation_memory.php`**: Retrieve recent conversation history

## Maintenance

Regular maintenance should include:
- Monitoring webhook response times through logs
- Reviewing backup table performance and storage
- Checking error logs for common validation issues
- Analyzing webhook usage patterns from logs
- Cleaning old log entries using `WebhookLogger::cleanOldLogs()`
- Monitoring `booking_canceled` table size and performance

## Important Notes

### Permanent Deletion
- **Warning**: This webhook permanently removes bookings from the database
- **No Recovery**: Cancelled bookings cannot be restored through this system
- **Backup Recommendation**: Ensure proper database backups before production use

### Audit Requirements
- **Complete Logging**: All cancellations are logged with full details
- **Audit Trail**: Maintains record of what was cancelled and when
- **Compliance**: Suitable for systems requiring cancellation audit trails

### Business Logic
- **Validation**: Ensures booking exists before cancellation
- **Information Preservation**: Captures all booking details before deletion
- **Error Handling**: Comprehensive error handling for all scenarios
