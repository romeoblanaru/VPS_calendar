# Find Booking Webhook Documentation

## Overview
The `find_booking.php` webhook is designed to retrieve the last two most recent active bookings for a specific client based on their full name and phone number. This webhook supports both GET and POST methods and returns comprehensive booking information including all related details from the database.

**Key Features**: 
- **Dual Method Support**: Accepts both GET and POST requests
- **Comprehensive Data**: Returns complete booking details including specialist, workplace, and service information
- **Smart Phone Number Matching**: Uses last 8 digits matching for flexible phone number recognition
- **Intelligent Name Scoring**: Advanced name matching with scoring system (10, 6, 3, 0)
- **Fuzzy Matching**: Handles diacritics, typos, and partial name matches
- **Active Bookings Only**: By default, returns only bookings where `booking_start_datetime > now`
- **Direct Lookup by ID**: If `booking_id` is provided, finds that booking only if it is active
- **Recent Bookings**: Returns the last 2 most recent valid matches ordered by score and date
- **Full Logging**: All operations are logged using WebhookLogger
- **CORS Support**: Includes proper CORS headers for web applications
- **Error Handling**: Comprehensive error handling with detailed error messages

## Base URL

```
GET /webhooks/find_booking.php
POST /webhooks/find_booking.php
```

## Authentication

Currently, this webhook does not require authentication. However, it's recommended to implement proper authentication in production environments.

## Parameters

### For Both GET and POST Methods (Default Mode)
- **`full_name`**: Full name of the client (exact/fuzzy match)
- **`caler_phone_nr`**: Phone number of the client (will be cleaned and matched by last 8 digits)

### Optional Parameter (Overrides Default Mode)
- **`booking_id`**: If provided, the webhook will ignore `full_name` and `caler_phone_nr` and return the booking with this ID only if `booking_start_datetime > now`. If no active booking is found for that ID, an error is returned.

## Request Methods

### 1. GET Method
**URL Format (Default Mode):**
```
/webhooks/find_booking.php?full_name=John%20Doe&caler_phone_nr=%2B37060012345
```

**URL Format (By Booking ID):**
```
/webhooks/find_booking.php?booking_id=123
```

**Note:** Parameters are automatically URL-decoded by the webhook.

### 2. POST Method
**Content-Type:** `application/json` or `application/x-www-form-urlencoded`

**JSON Example (Default Mode):**
```json
{
    "full_name": "John Doe",
    "caler_phone_nr": "+370 600 12345"
}
```

**JSON Example (By Booking ID):**
```json
{
    "booking_id": 123
}
```

**Form Data Example:**
```
full_name=John Doe&caler_phone_nr=+370 600 12345
```

## Response Format

### Success Response (HTTP 200)
```json
{
    "status": "success",
    "timestamp": "2025-01-13 15:30:00",
    "search_criteria": {
        "client_full_name": "John Doe",
        "caler_phone_nr": "+370 600 12345",
        "phone_number_cleaned": "37060012345",
        "last_8_digits_matched": "60012345",
        "active_only": true
    },
    "match_analysis": {
        "total_phone_matches": 5,
        "valid_name_matches": 2,
        "highest_match_score": 10
    },
    "bookings_found": 2,
    "bookings": [
        {
            "match_score": 10,
            "booking_id": 123,
            "booking_details": {
                "day_of_creation": "2025-01-10 14:30:00",
                "booking_start_datetime": "2025-01-15 09:00:00",
                "booking_end_datetime": "2025-01-15 10:00:00"
            },
            "client_info": {
                "full_name": "John Doe",
                "phone_number": "+37060012345"
            },
            "specialist_info": {
                "id": 1,
                "name": "Dana Zahareviciene",
                "speciality": "Hair Stylist",
                "email": "dana@beautyco.com",
                "phone_number": "37062012395"
            },
            "workplace_info": {
                "id": 1,
                "name": "Central Branch",
                "address": "Main St 1, City",
                "lead_person_name": "Alice Manager",
                "lead_person_phone": "44111222335",
                "workplace_phone": "44111222336",
                "booking_phone": "+123456789",
                "email": "central@beautyco.com"
            },
            "service_info": {
                "id": 1,
                "name": "Haircut - Women",
                "duration_minutes": 60,
                "price": "50.00",
                "vat_percentage": "21.00"
            }
        }
    ]
}
```

### Success Response (By Booking ID)
```json
{
    "status": "success",
    "timestamp": "2025-01-13 15:30:00",
    "search_criteria": {
        "booking_id": 123,
        "active_only": true
    },
    "match_analysis": {
        "mode": "by_booking_id",
        "total_phone_matches": 0,
        "valid_name_matches": 0,
        "highest_match_score": null
    },
    "bookings_found": 1,
    "bookings": [
        {
            "match_score": 10,
            "booking_id": 123,
            "booking_details": {
                "day_of_creation": "2025-01-10 14:30:00",
                "booking_start_datetime": "2025-01-15 09:00:00",
                "booking_end_datetime": "2025-01-15 10:00:00"
            },
            "client_info": {
                "full_name": "John Doe",
                "phone_number": "+37060012345"
            }
        }
    ]
}
```

### Error Response (HTTP 400)
```json
{
    "status": "error",
    "message": "Missing required parameters: full_name, caler_phone_nr",
    "timestamp": "2025-01-13 15:30:00"
}
```

```json
{
    "status": "error",
    "message": "No active bookings found with phone number ending in: 60012345",
    "timestamp": "2025-01-13 15:30:00"
}
```

```json
{
    "status": "error",
    "message": "No active booking found with the provided booking_id",
    "timestamp": "2025-01-13 15:30:00"
}
```

## Response Fields Description

### Top Level
- `status`: Operation status ("success" or "error")
- `timestamp`: Current server timestamp
- `search_criteria`: The search parameters used for the query
- `match_analysis`: Analysis of the matching process and results
- `bookings_found`: Number of valid bookings found (0-2)
- `bookings`: Array of booking objects (maximum 2)

### Search Criteria
- `client_full_name`: The full name used in the search
- `caler_phone_nr`: The original phone number provided
- `phone_number_cleaned`: The cleaned phone number used for database matching
- `last_8_digits_matched`: The last 8 digits used for phone number matching
- `active_only`: Boolean flag indicating that only active bookings are returned
- `booking_id`: Only present in booking-id mode

### Match Analysis
- `total_phone_matches`: Total number of bookings found with matching phone number (after active filter)
- `valid_name_matches`: Number of bookings with valid name matches (score >= 3)
- `highest_match_score`: The highest match score achieved among valid matches
- `mode`: Present only when searching by booking ID

### Booking Object Structure
Each booking contains the following sections:

#### Match Score
- `match_score`: Integer score (10, 6, 3) indicating the quality of the name match

#### Booking Details
- `booking_id`: Unique identifier for the booking
- `day_of_creation`: When the booking was created
- `booking_start_datetime`: Scheduled start time of the service
- `booking_end_datetime`: Scheduled end time of the service

#### Client Information
- `full_name`: Client's full name
- `phone_number`: Client's phone number

#### Specialist Information
- `id`: Specialist's unique identifier
- `name`: Specialist's full name
- `speciality`: Specialist's area of expertise
- `email`: Specialist's email address
- `phone_number`: Specialist's phone number

#### Workplace Information
- `id`: Workplace unique identifier
- `name`: Name of the workplace/branch
- `address`: Physical address of the workplace
- `lead_person_name`: Name of the lead person/manager
- `lead_person_phone`: Lead person's phone number
- `workplace_phone`: General workplace phone number
- `booking_phone`: Phone number used for bookings
- `email`: Workplace email address

#### Service Information
- `id`: Service unique identifier
- `name`: Name of the service
- `duration_minutes`: Service duration in minutes
- `price`: Service price
- `vat_percentage`: VAT percentage applied to the service

## Phone Number Processing

### Cleaning Process
The webhook automatically cleans phone numbers by:
1. Removing all spaces
2. Removing all dots (.)
3. Removing all plus signs (+)

### Last 8 Digits Matching
- Phone numbers are matched using only the last 8 digits
- This provides flexibility for different country codes and prefixes
- The cleaned phone number is processed to extract the last 8 digits for database matching

### Examples
- Input: `+370 600 12345` → Cleaned: `37060012345` → Last 8: `60012345`
- Input: `370.600.123.45` → Cleaned: `37060012345` → Last 8: `60012345`
- Input: `370 600 12345` → Cleaned: `37060012345` → Last 8: `60012345`

## Name Matching and Scoring System

### Scoring Logic
The webhook uses an intelligent scoring system to evaluate name matches:

- **Score 10 (Perfect Match)**: All words in the incoming name exactly match words in the client name
- **Score 6 (Good Match)**: Most words match exactly, with some partial matches
- **Score 3 (Partial Match)**: Words match partially due to diacritics, typos, or fuzzy matching
- **Score 0 (No Match)**: No meaningful matches found

### Matching Process
1. **Phone Number Filter**: First finds all bookings with matching last 8 digits
2. **Active Filter**: Keeps only bookings where `booking_start_datetime > now`
3. **Name Splitting**: Splits both incoming and stored names into individual words
4. **Word Matching**: Compares each word using multiple strategies:
   - Exact match (case-insensitive)
   - Partial match (one word contains the other)
   - Fuzzy match (similarity > 80% for diacritics and typos)
5. **Score Calculation**: Computes overall match score based on word matches
6. **Result Filtering**: Only returns bookings with score >= 3 (valid matches)

### Examples
- **Input**: "John Doe" vs **Database**: "John Doe" → **Score**: 10 (perfect)
- **Input**: "John Doe" vs **Database**: "John Doee" → **Score**: 6 (one exact, one partial)
- **Input**: "Jhon Doe" vs **Database**: "John Doe" → **Score**: 3 (fuzzy match for typo)
- **Input**: "Jane Smith" vs **Database**: "John Doe" → **Score**: 0 (no match)

## Database Queries

### Main Query (Default Mode)
The webhook performs a comprehensive JOIN query across multiple tables and filters by active bookings:

```sql
SELECT 
    b.unic_id as booking_id,
    b.id_specialist,
    b.id_work_place,
    b.day_of_creation,
    b.service_id,
    b.booking_start_datetime,
    b.booking_end_datetime,
    b.client_full_name,
    b.client_phone_nr,
    s.name as specialist_name,
    s.speciality as specialist_speciality,
    s.email as specialist_email,
    s.phone_nr as specialist_phone,
    wp.name_of_the_place as workplace_name,
    wp.address as workplace_address,
    wp.lead_person_name as workplace_lead_person,
    wp.lead_person_phone_nr as workplace_lead_phone,
    wp.workplace_phone_nr as workplace_phone,
    wp.booking_phone_nr as workplace_booking_phone,
    wp.email as workplace_email,
    sv.name_of_service as service_name,
    sv.duration as service_duration,
    sv.price_of_service as service_price,
    sv.procent_vat as service_vat
FROM booking b
LEFT JOIN specialists s ON b.id_specialist = s.unic_id
LEFT JOIN working_points wp ON b.id_work_place = wp.unic_id
LEFT JOIN services sv ON b.service_id = sv.unic_id
WHERE RIGHT(REPLACE(REPLACE(REPLACE(b.client_phone_nr, ' ', ''), '.', ''), '+', ''), 8) = ?
  AND b.booking_start_datetime > ?
ORDER BY b.booking_start_datetime DESC
```

### Query (By Booking ID)
```sql
SELECT ...
FROM booking b
LEFT JOIN specialists s ON b.id_specialist = s.unic_id
LEFT JOIN working_points wp ON b.id_work_place = wp.unic_id
LEFT JOIN services sv ON b.service_id = sv.unic_id
WHERE b.unic_id = ? AND b.booking_start_datetime > ?
```

### Table Relationships
- **`booking`**: Main table containing booking information
- **`specialists`**: Specialist details (LEFT JOIN)
- **`working_points`**: Workplace information (LEFT JOIN)
- **`services`**: Service details (LEFT JOIN)

## Error Handling

### Common Error Messages
- **"Missing required parameters: full_name, caler_phone_nr"**: No parameters provided at all (default mode)
- **"Missing required parameters: [parameter_names]"**: Required parameters are missing or empty (default mode)
- **"Phone number must have at least 8 digits after cleaning"**: Phone number validation failed
- **"No active bookings found with phone number ending in: [last_8_digits]"**: No active phone number matches found
- **"Name does not match any of our active booking records for this phone number"**: Active phone matches found but name matching failed
- **"No active booking found with the provided booking_id"**: Booking ID provided but either not found or not active
- **"Invalid booking_id. It must be a positive integer"**: Validation error for booking_id
- **"Only GET and POST methods are allowed for this webhook"**: Invalid HTTP method used

### Error Response Format
All errors return a 400 HTTP status code with:
- `status`: Always "error"
- `message`: Descriptive error message
- `timestamp`: Current server timestamp

## Usage Examples

### cURL Examples

#### GET Request (Default)
```bash
curl -X GET "http://your-domain.com/webhooks/find_booking.php?full_name=John%20Doe&caler_phone_nr=%2B37060012345"
```

#### GET Request (By Booking ID)
```bash
curl -X GET "http://your-domain.com/webhooks/find_booking.php?booking_id=123"
```

#### POST Request (JSON Default)
```bash
curl -X POST http://your-domain.com/webhooks/find_booking.php \
  -H "Content-Type: application/json" \
  -d '{
    "full_name": "John Doe",
    "caler_phone_nr": "+370 600 12345"
  }'
```

#### POST Request (JSON By ID)
```bash
curl -X POST http://your-domain.com/webhooks/find_booking.php \
  -H "Content-Type: application/json" \
  -d '{
    "booking_id": 123
  }'
```

#### POST Request (Form Data)
```bash
curl -X POST http://your-domain.com/webhooks/find_booking.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "full_name=John%20Doe&caler_phone_nr=%2B37060012345"
```

## Webhook Logger Integration

This webhook includes comprehensive logging functionality using the WebhookLogger class:

### Logged Information
- **Request Details**: Method, URL, parameters, headers, client IP
- **Response Data**: Status codes, response body, processing time
- **Related Entities**: Client name, client phone number, and when applicable booking_id
- **Additional Data**: Operation type, phone number processing details, search results

### Log Structure
```json
{
    "webhook_name": "find_booking",
    "request_method": "POST",
    "request_params": {
        "full_name": "John Doe",
        "caler_phone_nr": "+370 600 12345"
    },
    "response_status_code": 200,
    "response_body": "{\"status\":\"success\",...}",
    "processing_time_ms": 45,
    "is_successful": 1,
    "additional_data": {
        "operation_type": "find_bookings",
        "phone_number_cleaned": "37060012345",
        "last_8_digits": "60012345",
        "bookings_found": 2,
        "total_phone_matches": 5,
        "highest_match_score": 10
    }
}
```

## Security Considerations

1. **Input Validation**: All input parameters are validated and sanitized
2. **SQL Injection Protection**: Uses prepared statements for all database queries
3. **Phone Number Sanitization**: Automatically cleans phone numbers before storage and comparison
4. **Error Logging**: Comprehensive error logging for debugging and monitoring
5. **CORS Configuration**: Proper CORS headers for web application integration

## Rate Limiting

Currently, no rate limiting is implemented. Consider implementing rate limiting for production use.

## Testing

You can test the webhook using tools like:
- cURL
- Postman
- Insomnia
- Any HTTP client
- Web browsers (for GET requests)

## Database Dependencies

This webhook depends on the following database tables:

1. **`booking`**: The main table for storing booking records
2. **`specialists`**: Contains specialist information
3. **`working_points`**: Contains workplace information
4. **`services`**: Contains service details

### Required Table Structure
```sql
-- Main booking table
CREATE TABLE booking (
    unic_id INT PRIMARY KEY AUTO_INCREMENT,
    id_specialist INT,
    id_work_place INT,
    day_of_creation DATETIME,
    service_id INT,
    booking_start_datetime DATETIME,
    booking_end_datetime DATETIME,
    client_full_name VARCHAR(100),
    client_phone_nr VARCHAR(20),
    received_through VARCHAR(20),
    received_call_date DATETIME,
    client_transcript_conversation TEXT
);

-- Related tables with proper foreign key relationships
-- specialists, working_points, services
```

## Support

For issues or questions regarding this webhook, check the webhook logs or contact the development team.

## Maintenance

Regular maintenance should include:
- Monitoring webhook response times through logs
- Reviewing search performance and query optimization
- Checking error logs for common validation issues
- Analyzing webhook usage patterns from logs
- Cleaning old log entries using `WebhookLogger::cleanOldLogs()`
- Monitoring phone number matching accuracy
- Reviewing database query performance
