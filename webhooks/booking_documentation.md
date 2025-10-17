# Booking Webhook Documentation

## Overview
The `booking.php` webhook handles the creation of new bookings in the calendar system. It accepts booking details and creates a new entry in the bookings table with comprehensive validation and logging.

**Key Features**: 
- **Complete Booking Creation**: Handles all required and optional booking parameters
- **Phone Number Cleaning**: Automatically removes all non-numeric characters from phone numbers
- **Field Validation**: Comprehensive validation for all input parameters
- **Automatic Field Population**: Sets creation timestamps and generates unique IDs
- **Field Truncation**: Automatically truncates long values without errors
- **Full Logging**: All operations are logged using WebhookLogger
- **Multiple Request Methods**: Supports both GET and POST requests
- **JSON Response Format**: Structured responses for both success and error cases

## Endpoint

### URL
```
GET/POST: /webhooks/booking.php
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
| `id_specialist` | int | ID of the specialist handling the booking | `5` |
| `id_work_place` | int | ID of the working point/place | `3` |
| `service_id` | int | ID of the service being booked | `12` |
| `booking_start_datetime` | string | Start date/time of the booking (Y-m-d H:i:s format) | `2025-01-20 14:00:00` |
| `booking_end_datetime` | string | End date/time of the booking (Y-m-d H:i:s format) | `2025-01-20 15:00:00` |
| `client_full_name` | string | Full name of the client | `John Doe` |
| `client_phone_nr` | string | Phone number of the client | `+370 600 12345` |

### Optional Parameters

| Parameter | Type | Description | Example | Default |
|-----------|------|-------------|---------|---------|
| `received_through` | string | Source through which the booking was received (PHONE, SMS, Facebook, Email, Whatsapp, etc.) | `PHONE` | `NULL` |
| `client_transcript_conversation` | string | Transcript of client conversation | `Client called to schedule appointment` | `NULL` |

## Parameter Details

### `received_through` Field

This field tracks the source/origin of how the booking was initiated:

- **Purpose**: Identify the communication channel used for the booking
- **Examples**: PHONE, SMS, Facebook, Email, Whatsapp, Website, Walk-in
- **Behavior**: 
  - If longer than 20 characters, automatically truncated to 20 characters
  - No error messages for length violations
  - Optional parameter - can be omitted
- **Database Field**: Stored in `received_through` column (VARCHAR(20))

### `received_call_date` Field

- **Purpose**: Automatically set to the same value as `day_of_creation`
- **Value**: Current timestamp when the booking is created
- **Behavior**: Not configurable - always matches creation time

### Phone Number Processing

- **Complete Cleaning**: ALL non-numeric characters are removed (spaces, dots, plus signs, dashes, parentheses, etc.)
- **Result**: Only digits (0-9) remain in the stored phone number
- **Examples**:
  - Input: `+370 600.123-45` → Stored: `37060012345`
  - Input: `(555) 123-4567` → Stored: `5551234567`
  - Input: `+1 800.555-0123` → Stored: `18005550123`

## Usage Examples

### Basic Request (GET)
```
GET /webhooks/booking.php?id_specialist=5&id_work_place=3&service_id=12&booking_start_datetime=2025-01-20 14:00:00&booking_end_datetime=2025-01-20 15:00:00&client_full_name=John Doe&client_phone_nr=+370 600 12345
```

### Request with Optional Fields (POST)
```
POST /webhooks/booking.php
Content-Type: application/x-www-form-urlencoded

id_specialist=5&id_work_place=3&service_id=12&booking_start_datetime=2025-01-20 14:00:00&booking_end_datetime=2025-01-20 15:00:00&client_full_name=John Doe&client_phone_nr=+370 600 12345&received_through=PHONE&client_transcript_conversation=Client called to schedule appointment for next week.
```

### cURL Example
```bash
curl -X POST "http://yourdomain.com/webhooks/booking.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "id_specialist=5&id_work_place=3&service_id=12&booking_start_datetime=2025-01-20 14:00:00&booking_end_datetime=2025-01-20 15:00:00&client_full_name=John Doe&client_phone_nr=+370 600 12345&received_through=PHONE&client_transcript_conversation=Client called to schedule appointment for next week."
```



## Response Format

### Success Response (HTTP 201)
```json
{
    "status": "success",
    "message": "Booking created successfully",
    "booking_details": {
        "booking_id": 123,
        "booking_unique_id": "BK20250120001",
        "specialist_name": "Dr. Smith",
        "working_point_name": "Downtown Office",
        "service_name": "Consultation",
        "client_name": "John Doe",
        "client_phone": "+37060012345",
        "booking_start": "2025-01-20 14:00:00",
        "booking_end": "2025-01-20 15:00:00",
        "duration_minutes": 60,
        "received_through": "PHONE",
        "created_at": "2025-01-15 16:30:00"
    },
    "timestamp": "2025-01-15 16:30:00"
}
```

### Error Response (HTTP 400)
```json
{
    "error": "Missing required parameters: id_specialist, id_work_place",
    "status": "error",
    "required_fields": ["id_specialist", "id_work_place", "service_id", "booking_start_datetime", "booking_end_datetime", "client_full_name", "client_phone_nr"],
    "missing_fields": ["id_specialist", "id_work_place"],
    "timestamp": "2025-01-15 16:30:00"
}
```

## Response Fields Description

### Main Response Structure
- `status`: Always "success" for successful operations
- `message`: Human-readable success message
- `booking_details`: Contains all the booking details
- `timestamp`: When the response was generated

### Booking Details Fields
- `booking_id`: The ID of the created booking
- `booking_unique_id`: Auto-generated unique identifier
- `specialist_name`: Name of the specialist handling the booking
- `working_point_name`: Name of the working point/location
- `service_name`: Name of the service being booked
- `client_name`: Full name of the client
- `client_phone`: Client's phone number (cleaned)
- `booking_start`: Start date/time of the booking
- `booking_end`: End date/time of the booking
- `duration_minutes`: Duration of the booking in minutes
- `received_through`: Source through which the booking was received
- `created_at`: When the booking was created

## Field Validation

### Required Field Validation
- All required fields must be provided and non-empty
- Missing fields result in 400 error with detailed field information

### DateTime Validation
- `booking_start_datetime` and `booking_end_datetime` must be in Y-m-d H:i:s format
- Invalid datetime formats result in 400 error

### Phone Number Processing
- **Complete Cleaning**: ALL non-numeric characters are removed (spaces, dots, plus signs, dashes, parentheses, etc.)
- **Result**: Only digits (0-9) remain in the stored phone number
- **Examples**:
  - Input: `+370 600.123-45` → Stored: `37060012345`
  - Input: `(555) 123-4567` → Stored: `5551234567`
  - Input: `+1 800.555-0123` → Stored: `18005550123`
- No validation errors for phone number format

### `received_through` Processing
- **Length Limit**: Maximum 20 characters
- **Truncation**: Values longer than 20 characters are automatically truncated
- **No Errors**: No validation errors for length violations
- **Optional**: Can be omitted entirely

## Database Fields

The webhook inserts data into the following fields:

| Field | Type | Description | Source |
|-------|------|-------------|---------|
| `id_specialist` | INT | Specialist ID | Required parameter |
| `id_work_place` | INT | Working point ID | Required parameter |
| `service_id` | INT | Service ID | Required parameter |
| `booking_start_datetime` | DATETIME | Booking start time | Required parameter |
| `booking_end_datetime` | DATETIME | Booking end time | Required parameter |
| `client_full_name` | VARCHAR | Client's full name | Required parameter |
| `client_phone_nr` | VARCHAR | Client's phone number (cleaned) | Required parameter |
| `received_through` | VARCHAR(20) | Source of the booking | Optional parameter |
| `received_call_date` | DATETIME | Call date (auto-set to creation time) | Auto-generated |
| `client_transcript_conversation` | TEXT | Conversation transcript | Optional parameter |
| `day_of_creation` | DATETIME | Creation timestamp | Auto-generated (NOW()) |

## Business Logic

### Automatic Field Population
- **`received_call_date`**: Automatically set to current timestamp (same as `day_of_creation`)
- **`day_of_creation`**: Automatically set to current timestamp
- **`unic_id`**: Auto-generated unique identifier

### Phone Number Cleaning
- **Complete Cleaning**: Removes ALL non-numeric characters (spaces, dots, plus signs, dashes, parentheses, etc.)
- **Result**: Only digits (0-9) remain for consistent storage format
- **Examples**:
  - Input: `+370 600.123-45` → Stored: `37060012345`
  - Input: `(555) 123-4567` → Stored: `5551234567`
  - Input: `+1 800.555-0123` → Stored: `18005550123`

### Field Truncation
- `received_through` field is automatically truncated to 20 characters if longer
- No error messages for length violations
- Silent truncation ensures data integrity

## Error Handling

### HTTP Status Codes
- **201**: Booking created successfully
- **400**: Bad request (missing/invalid parameters)
- **500**: Internal server error

### Error Response Structure
All errors include:
- `error`: Human-readable error description
- `status`: Always "error"
- `timestamp`: When the error occurred
- Additional context-specific fields

### Common Error Scenarios
1. **Missing Required Fields**: Lists all missing fields
2. **Invalid DateTime Format**: Shows expected format
3. **Database Errors**: Generic database error message
4. **Validation Failures**: Field-specific validation errors

## Webhook Logger Integration

This webhook includes comprehensive logging functionality using the WebhookLogger class:

### Logged Information
- **Request Details**: Method, URL, parameters, headers, client IP
- **Response Data**: Status codes, response body, processing time
- **Related Entities**: Specialist ID, working point ID, service ID
- **Additional Data**: Client details, received_through, transcript length

### Log Structure
```json
{
    "webhook_name": "booking",
    "request_method": "POST",
    "request_params": {
        "id_specialist": "5",
        "id_work_place": "3",
        "service_id": "12",
        "client_full_name": "John Doe",
        "client_phone_nr": "+370 600 12345"
    },
    "response_status_code": 201,
    "related_specialist_id": 5,
    "related_working_point_id": 3,
    "related_service_id": 12,
    "additional_data": {
        "client_name": "John Doe",
        "client_phone": "37060012345",
        "received_through": "PHONE",
        "transcript_length": 45
    }
}
```

### Error Logging
- **Validation Errors**: Missing parameters, invalid datetime formats
- **Database Errors**: Connection issues, query failures
- **General Errors**: Unexpected exceptions with stack traces

### Monitoring
- All webhook calls are logged to the `webhook_logs` table
- Performance metrics are tracked for optimization
- Error patterns can be analyzed for system improvements

## Security Considerations

1. **Input Validation**: All parameters are validated before processing
2. **SQL Injection Protection**: Uses prepared statements for all database queries
3. **Phone Number Sanitization**: Automatically cleans phone numbers
4. **Error Logging**: Comprehensive error logging for debugging
5. **Field Length Limits**: Automatic truncation prevents buffer overflow

## Performance Considerations

- **Database Indexes**: `received_through` field is indexed for better query performance
- **Prepared Statements**: Efficient database query execution
- **Field Validation**: Early validation prevents unnecessary database operations

## Integration Examples

### JavaScript/AJAX
```javascript
// Create a new booking
fetch('/webhooks/booking.php', {
    method: 'POST',
    headers: {
        'Content-Type: 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
        id_specialist: '5',
        id_work_place: '3',
        service_id: '12',
        booking_start_datetime: '2025-01-20 14:00:00',
        booking_end_datetime: '2025-01-20 15:00:00',
        client_full_name: 'John Doe',
        client_phone_nr: '+370 600 12345',
        received_through: 'PHONE',
        client_transcript_conversation: 'Client called to schedule appointment'
    })
})
.then(response => response.json())
.then(data => {
    if (data.status === 'success') {
        console.log('Booking created:', data.booking_details.booking_id);
        console.log('Unique ID:', data.booking_details.booking_unique_id);
    } else {
        console.error('Booking failed:', data.error);
    }
});
```

### PHP cURL
```php
// Create a new booking
$data = [
    'id_specialist' => '5',
    'id_work_place' => '3',
    'service_id' => '12',
    'booking_start_datetime' => '2025-01-20 14:00:00',
    'booking_end_datetime' => '2025-01-20 15:00:00',
    'client_full_name' => 'John Doe',
    'client_phone_nr' => '+370 600 12345',
    'received_through' => 'PHONE',
    'client_transcript_conversation' => 'Client called to schedule appointment'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://yourdomain.com/webhooks/booking.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['status'] === 'success') {
    echo "Booking created successfully with ID: " . $result['booking_details']['booking_id'];
} else {
    echo "Error: " . $result['error'];
}
```

## Testing

### Test Scenarios
1. **Valid Request**: Test with all required fields
2. **Missing Fields**: Test with missing required parameters
3. **Invalid DateTime**: Test with wrong datetime format
4. **Long received_through**: Test with values longer than 20 characters
5. **Optional Fields**: Test with and without optional parameters

### Example Test Cases
```bash
# Test 1: Valid request with received_through
curl -X POST http://your-domain.com/webhooks/booking.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "id_specialist=5&id_work_place=3&service_id=12&booking_start_datetime=2025-01-20 14:00:00&booking_end_datetime=2025-01-20 15:00:00&client_full_name=Test User&client_phone_nr=+370 600 12345&received_through=PHONE"

# Test 2: Long received_through value (should be truncated)
curl -X POST http://your-domain.com/webhooks/booking.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "id_specialist=5&id_work_place=3&service_id=12&booking_start_datetime=2025-01-20 14:00:00&booking_end_datetime=2025-01-20 15:00:00&client_full_name=Test User&client_phone_nr=+370 600 12345&received_through=VeryLongSourceNameThatExceedsTwentyCharacters"
```

## Database Migration

To update your database structure, run the following SQL:

```sql
-- Rename column and update structure
ALTER TABLE `booking` 
CHANGE COLUMN `received_call_phone_nr` `received_through` VARCHAR(20) DEFAULT NULL;

-- Add index for performance
ALTER TABLE `booking` 
ADD INDEX `idx_received_through` (`received_through`);
```

## Support

For issues or questions regarding this webhook:
1. Check the webhook logs for detailed error information
2. Verify all required parameters are provided
3. Ensure datetime formats are correct (Y-m-d H:i:s)
4. Contact the development team for additional assistance

## Related Webhooks

- **`update_conversation_memory.php`**: CRUD operations for conversation memory records
- **`get_past_conversation_memory.php`**: Retrieve recent conversation history

## Maintenance

Regular maintenance should include:
- Monitoring webhook response times through logs
- Reviewing phone number cleaning logic if business rules change
- Checking error logs for common validation issues
- Analyzing webhook usage patterns from logs
- Cleaning old log entries using `WebhookLogger::cleanOldLogs()` 