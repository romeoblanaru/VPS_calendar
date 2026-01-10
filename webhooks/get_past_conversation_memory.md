# Get Past Conversation Memory Webhook Documentation

## Overview
The `get_past_conversation_memory.php` webhook retrieves the last 3 conversation memory records for a specific client based on their phone number, and also fetches related booking information. Phone number matching uses the last 10 digits to avoid country code and prefix mismatches.

**Key Features**: 
- **Single Parameter Input**: Uses only `phone_nr_identification` parameter
- **Conversation Memory Retrieval**: Gets last 3 conversation records (excluding specified fields)
- **Unique Phone Number Extraction**: Extracts unique `booked_phone_nr` from conversation records
- **Booking Data Integration**: Fetches past and future bookings from booking table
- **Smart Phone Number Matching**: Uses last 10 digits matching to avoid country code issues
- **Organized Response**: Separates bookings into past and future categories
- **Full Logging**: All operations are logged using WebhookLogger
- **Multiple Request Methods**: Supports both GET and POST requests

## Base URL

```
GET/POST /webhooks/get_past_conversation_memory.php
```

## Authentication

Currently, this webhook does not require authentication. However, it's recommended to implement proper authentication in production environments.

## Required Parameters

### For All Operations:
- **`phone_nr_identification`**: Phone number for identification (will match by last 10 digits)

## Optional Parameters

- **`debug`**: Set to 'true', true, or '1' to include debug information in response (default: false)

## Request Examples

### GET Request
```
GET /webhooks/get_past_conversation_memory.php?phone_nr_identification=+370%20600%2012345
```

### GET Request with Debug
```
GET /webhooks/get_past_conversation_memory.php?phone_nr_identification=+370%20600%2012345&debug=true
```

### POST JSON Request
```json
{
    "phone_nr_identification": "+370 600 12345"
}
```

### POST JSON Request with Debug
```json
{
    "phone_nr_identification": "+370 600 12345",
    "debug": true
}
```

### POST Form Data Request
```
phone_nr_identification=+370 600 12345
```

### POST Form Data Request with Debug
```
phone_nr_identification=+370 600 12345&debug=true
```

### cURL Examples

#### GET Request
```bash
curl -X GET "http://your-domain.com/webhooks/get_past_conversation_memory.php?phone_nr_identification=%2B370%20600%2012345"
```

#### GET Request with Debug
```bash
curl -X GET "http://your-domain.com/webhooks/get_past_conversation_memory.php?phone_nr_identification=%2B370%20600%2012345&debug=true"
```

#### POST Request
```bash
curl -X POST http://your-domain.com/webhooks/get_past_conversation_memory.php \
  -H "Content-Type: application/json" \
  -d '{"phone_nr_identification": "+370 600 12345"}'
```

#### POST Request with Debug
```bash
curl -X POST http://your-domain.com/webhooks/get_past_conversation_memory.php \
  -H "Content-Type: application/json" \
  -d '{"phone_nr_identification": "+370 600 12345", "debug": true}'
```

#### POST Form Data Request
```bash
curl -X POST http://your-domain.com/webhooks/get_past_conversation_memory.php \
  -d "phone_nr_identification=+370 600 12345"
```

#### POST Form Data Request with Debug
```bash
curl -X POST http://your-domain.com/webhooks/get_past_conversation_memory.php \
  -d "phone_nr_identification=+370 600 12345&debug=true"
```

## Response Format

### Success Response (HTTP 200)
```json
{
    "status": "success",
    "message": "Past conversation memory and booking data retrieved successfully",
    "data": {
        "phone_nr_identification": "+370 600 12345",
        "last_10_digits_matched": "0600123456",
        "total_conversation_records": 5,
        "total_booking_records_found": 2,
        "conversation_records_returned": 3,
        "last_3_conversation_records": [
            {
                "client_full_name": "John Doe",
                "conversation_summary": "Client requested appointment scheduling for next week",
                "dat_time": "2024-01-15 14:30:00",
                "finalized_action": "Appointment scheduled",
                "worplace_id": 5,
                "workplace_name": "Downtown Office",
                "source": "phone",
                "booked_phone_nr": "37060099999"
            },
            {
                "client_full_name": "John Doe",
                "conversation_summary": "Client called to reschedule appointment",
                "dat_time": "2024-01-14 10:15:00",
                "finalized_action": "Appointment rescheduled",
                "worplace_id": 5,
                "workplace_name": "Downtown Office",
                "source": "SMS",
                "booked_phone_nr": "37060099999"
            },
            {
                "client_full_name": "John Doe",
                "conversation_summary": "Initial consultation request",
                "dat_time": "2024-01-13 16:45:00",
                "finalized_action": "Consultation booked",
                "worplace_id": 3,
                "workplace_name": "Uptown Clinic",
                "source": "phone",
                "booked_phone_nr": "37060088888"
            }
        ],
        "unique_booked_phone_numbers": [
            "37060099999",
            "37060088888"
        ],
        "workplace_ids_found": [5, 3],
        "bookings": {
            "past_bookings": [
                {
                    "unic_id": 123,
                    "id_specialist": 5,
                    "id_work_place": 5,
                    "day_of_creation": "2024-01-10 14:30:00",
                    "service_id": 12,
                    "booking_start_datetime": "2024-01-12 14:00:00",
                    "booking_end_datetime": "2024-01-12 15:00:00",
                    "client_full_name": "John Doe",
                    "client_phone_nr": "37060012345"
                }
            ],
            "future_bookings": [
                {
                    "unic_id": 124,
                    "id_specialist": 5,
                    "id_work_place": 5,
                    "day_of_creation": "2024-01-15 14:30:00",
                    "service_id": 12,
                    "booking_start_datetime": "2024-01-20 14:00:00",
                    "booking_end_datetime": "2024-01-20 15:00:00",
                    "client_full_name": "John Doe",
                    "client_phone_nr": "37060012345"
                }
            ]
        },
        "search_criteria": {
            "phone_number_cleaned": "37060012345",
            "last_8_digits": "60012345",
            "workplace_ids_searched": [5, 3]
        }
    },
    "timestamp": "2024-01-15 16:30:00"
}
```

### Error Response (HTTP 400)
```json
{
    "status": "error",
    "message": "Missing required parameter: phone_nr_identification",
    "timestamp": "2024-01-15 16:30:00"
}
```

## Response Fields Description

### Main Response Structure
- **`status`**: Always "success" for successful requests
- **`message`**: Human-readable success message
- **`data`**: Contains all the retrieved information
- **`timestamp`**: When the response was generated

### Data Section Fields
- **`phone_nr_identification`**: The original phone number provided in the request
- **`last_10_digits_matched`**: The last 10 digits used for matching
- **`total_conversation_records`**: Total number of conversation records found for this phone number
- **`total_booking_records_found`**: Total number of booking records found and returned
- **`conversation_records_returned`**: Number of conversation records returned (max 3)
- **`last_3_conversation_records`**: Array of the last 3 conversation records

### Conversation Records Fields
Each conversation record contains:
- **`client_full_name`**: Full name of the client
- **`conversation_summary`**: AI-generated summary of the conversation
- **`dat_time`**: Date and time of the conversation
- **`finalized_action`**: Action that was finalized from the conversation
- **`worplace_id`**: ID of the workplace
- **`workplace_name`**: Name of the workplace
- **`source`**: Source of the conversation (SMS, phone, facebook, whatsapp, etc.)
- **`booked_phone_nr`**: Phone number provided by client for booking

**Note**: The following fields are excluded from conversation records:
- `id`
- `conversation`
- `lenght`
- `client_phone_nr`

### Booking Information
- **`unique_booked_phone_numbers`**: Array of unique booked phone numbers found in conversation records
- **`workplace_ids_found`**: Array of workplace IDs found in conversation records
- **`bookings`**: Object containing past and future bookings

### Bookings Structure
- **`past_bookings`**: Array of past bookings (booking_start_datetime < current time)
- **`future_bookings`**: Array of future bookings (booking_start_datetime >= current time)

### Booking Record Fields
Each booking record contains:
- **`unic_id`**: Unique booking identifier
- **`id_specialist`**: ID of the specialist
- **`id_work_place`**: ID of the working point/place
- **`day_of_creation`**: When the booking was created
- **`service_id`**: ID of the service
- **`booking_start_datetime`**: Start date/time of the booking
- **`booking_end_datetime`**: End date/time of the booking
- **`client_full_name`**: Full name of the client
- **`client_phone_nr`**: Phone number of the client

**Note**: The following fields are excluded from booking records:
- `google_event_id`
- `client_transcript_conversation`
- `received_call_date`
- `received_call_phone_nr`

### Debug Information (Optional)

When `debug=true` is included in the request, the response will also include a `search_criteria` section with detailed information about the search process:

- **`search_criteria`**: Object containing debug information (only included when debug=true)
  - **`phone_number_cleaned`**: Phone number after cleaning
  - **`last_10_digits`**: Last 10 digits extracted for matching
  - **`booked_phone_numbers_searched`**: Array of last 10 digits from booked phone numbers
  - **`workplace_ids_searched`**: Array of workplace IDs used in booking search
  - **`debug_info`**: Additional debug information including original phone, unique booked phones found, and record counts

## Phone Number Matching Logic

The webhook uses a sophisticated phone number matching system:

1. **Input Cleaning**: Removes dots (.), plus signs (+), and spaces from the input phone number
2. **Last 10 Digits Extraction**: Extracts only the last 10 digits from the cleaned phone number
3. **Database Comparison**: Uses MySQL `RIGHT()` function to compare the last 10 digits

**Examples of Matching:**
- Input: `+370 600 12345` → Last 10 digits: `0370600123`
- Database: `37060012345` → Last 10 digits: `0370600123` ✅ **MATCH**
- Database: `60012345` → Last 10 digits: `0060012345` ❌ **NO MATCH**
- Database: `+1 600 12345` → Last 10 digits: `0160012345` ❌ **NO MATCH**

## Data Flow

1. **Phone Number Processing**: Clean and extract last 10 digits from input
2. **Conversation Query**: Get last 3 conversation records matching phone number
3. **Data Extraction**: Extract unique booked phone numbers and workplace IDs
4. **Booking Query**: Get last 2 bookings matching phone number and workplace IDs
5. **Organization**: Separate bookings into past and future based on current time
6. **Response**: Return organized data with comprehensive information

## Error Handling

All errors return a 400 HTTP status code with the following JSON structure:

```json
{
    "status": "error",
    "message": "Error description",
    "timestamp": "2024-01-15 16:30:00"
}
```

**Common Error Messages:**
- "Missing required parameter: phone_nr_identification"
- "Phone number must have at least 10 digits after cleaning"
- "Only GET and POST methods are allowed for this webhook"

## Database Dependencies

This webhook depends on the following database tables:

1. **`conversation_memory`**: The main table for storing conversation records
2. **`booking`**: Contains booking information with client and workplace details

**Required `conversation_memory` table structure:**
```sql
CREATE TABLE conversation_memory (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    client_full_name varchar(255) NOT NULL,
    client_phone_nr varchar(20) NOT NULL,
    conversation text NOT NULL,
    conversation_summary text DEFAULT NULL,
    dat_time timestamp NULL DEFAULT current_timestamp(),
    finalized_action varchar(255) DEFAULT NULL,
    lenght int(11) DEFAULT NULL,
    worplace_id int(11) DEFAULT NULL,
    workplace_name varchar(255) DEFAULT NULL,
    source varchar(50) DEFAULT 'phone',
    booked_phone_nr varchar(20) DEFAULT NULL,
    PRIMARY KEY (id)
);
```

**Required `booking` table structure:**
```sql
CREATE TABLE booking (
    unic_id int(11) NOT NULL,
    id_specialist int(11) DEFAULT NULL,
    id_work_place int(11) DEFAULT NULL,
    day_of_creation datetime DEFAULT NULL,
    service_id int(11) NOT NULL,
    booking_start_datetime datetime DEFAULT NULL,
    booking_end_datetime datetime DEFAULT NULL,
    client_full_name varchar(100) DEFAULT NULL,
    client_phone_nr varchar(20) NOT NULL,
    received_call_phone_nr varchar(20) DEFAULT NULL,
    received_call_date datetime DEFAULT NULL,
    client_transcript_conversation text DEFAULT NULL,
    PRIMARY KEY (unic_id)
);
```

## Webhook Logger Integration

This webhook includes comprehensive logging functionality using the WebhookLogger class:

### Logged Information
- **Request Details**: Method, URL, parameters, headers, client IP
- **Response Data**: Status codes, response body, processing time
- **Related Entities**: Phone number, conversation count, booking count
- **Additional Data**: Phone number processing details, workplace resolution

### Log Structure
```json
{
    "webhook_name": "get_past_conversation_memory",
    "request_method": "POST",
    "request_params": {
        "phone_nr_identification": "+370 600 12345"
    },
    "response_status_code": 200,
    "related_conversation_count": 3,
    "related_booking_count": 2,
    "additional_data": {
        "phone_number_cleaned": "37060012345",
        "last_8_digits": "60012345",
        "workplace_ids_found": [5, 3],
        "unique_booked_phone_numbers": ["37060099999", "37060088888"]
    }
}
```

## Security Considerations

1. **Input Validation**: Phone number is validated and cleaned before processing
2. **SQL Injection Protection**: Uses prepared statements for all database queries
3. **Phone Number Sanitization**: Automatically cleans phone numbers before storage and comparison
4. **Error Logging**: Comprehensive error logging for debugging and monitoring
5. **Data Privacy**: Excludes sensitive fields like conversation content and transcripts

## Rate Limiting

Currently, no rate limiting is implemented. Consider implementing rate limiting for production use.

## Testing

You can test the webhook using tools like:
- cURL
- Postman
- Insomnia
- Any HTTP client

**Example cURL commands:**

#### GET Request
```bash
curl -X GET "http://your-domain.com/webhooks/get_past_conversation_memory.php?phone_nr_identification=%2B370%20600%2012345"
```

#### POST Request
```bash
curl -X POST http://your-domain.com/webhooks/get_past_conversation_memory.php \
  -H "Content-Type: application/json" \
  -d '{"phone_nr_identification": "+370 600 12345"}'
```

## Integration Examples

### JavaScript/AJAX

#### GET Request
```javascript
const phoneNumber = encodeURIComponent('+370 600 12345');
fetch(`/webhooks/get_past_conversation_memory.php?phone_nr_identification=${phoneNumber}`, {
    method: 'GET'
})
.then(response => response.json())
.then(data => {
    if (data.status === 'success') {
        console.log('Conversation records:', data.data.last_3_conversation_records);
        console.log('Past bookings:', data.data.bookings.past_bookings);
        console.log('Future bookings:', data.data.bookings.future_bookings);
    } else {
        console.error('Error:', data.message);
    }
})
.catch(error => {
    console.error('Network error:', error);
});
```

#### POST Request
```javascript
fetch('/webhooks/get_past_conversation_memory.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        phone_nr_identification: '+370 600 12345'
    })
})
.then(response => response.json())
.then(data => {
    if (data.status === 'success') {
        console.log('Conversation records:', data.data.last_3_conversation_records);
        console.log('Past bookings:', data.data.bookings.past_bookings);
        console.log('Future bookings:', data.data.bookings.future_bookings);
    } else {
        console.error('Error:', data.message);
    }
})
.catch(error => {
    console.error('Network error:', error);
});
```

### PHP Integration

#### GET Request
```php
$phoneNumber = '+370 600 12345';
$encodedPhone = urlencode($phoneNumber);

$url = "http://your-domain.com/webhooks/get_past_conversation_memory.php?phone_nr_identification=$encodedPhone";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($data['status'] === 'success') {
    $conversations = $data['data']['last_3_conversation_records'];
    $pastBookings = $data['data']['bookings']['past_bookings'];
    $futureBookings = $data['data']['bookings']['future_bookings'];
    
    // Process the data
    foreach ($conversations as $conversation) {
        echo "Conversation: " . $conversation['conversation_summary'] . "\n";
    }
} else {
    echo "Error: " . $data['message'];
}
```

#### POST Request
```php
$phoneNumber = '+370 600 12345';

$postData = [
    'phone_nr_identification' => $phoneNumber
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://your-domain.com/webhooks/get_past_conversation_memory.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($data['status'] === 'success') {
    $conversations = $data['data']['last_3_conversation_records'];
    $pastBookings = $data['data']['bookings']['past_bookings'];
    $futureBookings = $data['data']['bookings']['future_bookings'];
    
    // Process the data
    foreach ($conversations as $conversation) {
        echo "Conversation: " . $conversation['conversation_summary'] . "\n";
    }
} else {
    echo "Error: " . $data['message'];
}
```

## Support

For issues or questions regarding this webhook, check the webhook logs or contact the development team.

## Maintenance

Regular maintenance should include:
- Monitoring webhook response times through logs
- Reviewing phone number matching accuracy
- Checking error logs for common validation issues
- Analyzing webhook usage patterns from logs
- Cleaning old log entries using `WebhookLogger::cleanOldLogs()`
- Monitoring conversation and booking data retrieval performance