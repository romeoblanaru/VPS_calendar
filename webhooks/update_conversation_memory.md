# Update Conversation Memory Webhook Documentation

## Overview
The `update_conversation_memory.php` webhook provides a RESTful API for managing conversation memory records in the `conversation_memory` table. This webhook supports CRUD operations (Create, Read, Update, Delete) for conversation memory entries.

**Key Features**: 
- **Complete CRUD Operations**: Create, Read, Update, Delete conversation memory records
- **Automatic Workplace Resolution**: Resolves workplace_id and workplace_name from calee_phone_nr
- **Smart Phone Number Matching**: Uses last 8 digits matching to avoid country code issues
- **Source Field Validation**: Enforces allowed source values (SMS, phone, facebook, whatsapp, email, other)
- **Full Logging**: All operations are logged using WebhookLogger
- **Multiple Request Methods**: Supports both GET and POST requests
- **JSON Response Format**: Structured responses for both success and error cases
- **Phone Number Cleaning**: Automatically cleans and standardizes phone numbers
- **Dual Phone Number Support**: Supports both client_phone_nr and booked_phone_nr for different use cases

**Important:** The webhook automatically resolves `workplace_id` and `workplace_name` from the `working_points` table using the `calee_phone_nr` parameter, so you don't need to provide these values directly.

## Base URL

```
POST /webhooks/update_conversation_memory.php
```

## Authentication

Currently, this webhook does not require authentication. However, it's recommended to implement proper authentication in production environments.

## Required Parameters

### For INSERT Operations:
- **`action`**: Must be "insert"
- **`client_full_name`**: Full name of the client
- **`client_phone_nr`**: Phone number of the client
- **`conversation`**: Content of the conversation
- **`calee_phone_nr`**: Phone number of the working point (used to resolve workplace_id and workplace_name)
- **`source`**: Source of the conversation (must be one of: SMS, phone, facebook, whatsapp, email, other)

### For UPDATE Operations:
- **`action`**: Must be "update"
- **`id`**: ID of the record to update
- **At least one field to update** (any of the optional fields below)

### For DELETE Operations:
- **`action`**: Must be "delete"
- **`id`**: ID of the record to delete

### For GET Operations:
- **`action`**: Must be "get"
- **`id`**: ID of the record to retrieve

## Optional Parameters

- **`dat_time`**: Custom timestamp (defaults to current time)
- **`finalized_action`**: Action that was finalized from the conversation
- **`lenght`**: Length of the conversation (word count, duration, etc.)
- **`conversation_summary`**: AI-generated summary of the conversation for quick reference
- **`booked_phone_nr`**: Phone number provided by client for booking (may differ from client_phone_nr)
- **`source`**: Source of the conversation (for UPDATE operations, must be one of: SMS, phone, facebook, whatsapp, email, other)

**Example Request:**
```json
{
    "action": "insert",
    "client_full_name": "John Doe",
    "client_phone_nr": "+370 600 12345",
    "conversation": "Client called to schedule an appointment for next week.",
    "conversation_summary": "Client requested appointment scheduling for next week",
    "calee_phone_nr": "+370 500 98765",
    "source": "phone",
    "finalized_action": "Appointment scheduled",
    "booked_phone_nr": "+370 600 99999"
}
```

**Example Response:**
```json
{
    "status": "success",
    "message": "Conversation memory insert operation completed successfully",
    "data": {
        "id": 123,
        "client_full_name": "John Doe",
        "client_phone_nr": "+37060012345",
        "conversation": "Client called to schedule an appointment for next week.",
        "conversation_summary": "Client requested appointment scheduling for next week",
        "dat_time": "2024-01-15 14:30:00",
        "finalized_action": "Appointment scheduled",
        "lenght": null,
        "calee_phone_nr": "+370 500 98765",
        "worplace_id": 5,
        "workplace_name": "Downtown Office",
        "source": "phone",
        "booked_phone_nr": "37060099999"
    },
    "timestamp": "2024-01-15 14:30:00"
}
```

### 2. UPDATE - Modify Existing Conversation Memory Record

**Action:** `update`

**Required Parameters:**
- `action`: Must be "update"
- `id`: ID of the record to update

**Optional Parameters (at least one must be provided):**
- `client_full_name`: Full name of the client
- `client_phone_nr`: Phone number of the client
- `conversation`: Content of the conversation
- `conversation_summary`: AI-generated summary of the conversation
- `dat_time`: Custom timestamp
- `finalized_action`: Action that was finalized from the conversation
- `lenght`: Length of the conversation
- `booked_phone_nr`: Phone number provided by client for booking
- `calee_phone_nr`: Phone number of the working point (will update both workplace_id and workplace_name)
- `source`: Source of the conversation (must be one of: SMS, phone, facebook, whatsapp, email, other)

**Example Request:**
```json
{
    "action": "update",
    "id": 123,
    "finalized_action": "Appointment confirmed and reminder sent",
    "lenght": 45,
    "booked_phone_nr": "+370 600 88888",
    "calee_phone_nr": "+370 500 98765"
}
```

**Example Response:**
```json
{
    "status": "success",
    "message": "Conversation memory update operation completed successfully",
    "data": {
        "id": 123,
        "updated_fields": ["finalized_action", "lenght", "booked_phone_nr", "calee_phone_nr"],
        "rows_affected": 1,
        "updated_record": {
            "id": "123",
            "client_full_name": "John Doe",
            "client_phone_nr": "+37060012345",
            "conversation": "Client called to schedule an appointment for next week.",
            "conversation_summary": "Client requested appointment scheduling for next week",
            "dat_time": "2024-01-15 14:30:00",
            "finalized_action": "Appointment confirmed and reminder sent",
            "lenght": "45",
            "worplace_id": "5",
            "workplace_name": "Downtown Office",
            "source": "phone",
            "booked_phone_nr": "37060088888"
        }
    },
    "timestamp": "2024-01-15 14:35:00"
}
```

### 3. DELETE - Remove Conversation Memory Record

**Action:** `delete`

**Required Parameters:**
- `action`: Must be "delete"
- `id`: ID of the record to delete

**Example Request:**
```json
{
    "action": "delete",
    "id": 123
}
```

**Example Response:**
```json
{
    "status": "success",
    "message": "Conversation memory delete operation completed successfully",
    "data": {
        "id": 123,
        "rows_deleted": 1,
        "message": "Deleted conversation memory record with id: 123"
    },
    "timestamp": "2024-01-15 14:40:00"
}
```

### 4. GET - Retrieve Conversation Memory Records

**Action:** `get`

**Optional Parameters:**
- `limit`: Maximum number of records to return (default: 100)
- `offset`: Number of records to skip for pagination (default: 0)
- `id`: Filter by specific record ID (exact match)
- `client_full_name`: Filter by client name (partial match)
- `client_phone_nr`: Filter by client phone number (partial match)
- `conversation`: Filter by conversation content (partial match)
- `conversation_summary`: Filter by conversation summary (partial match)
- `finalized_action`: Filter by finalized action (partial match)
- `worplace_id`: Filter by workplace ID (exact match)
- `workplace_name`: Filter by workplace name (partial match)
- `booked_phone_nr`: Filter by booked phone number (exact match)
- `calee_phone_nr`: Filter by working point phone number (converts to workplace_id lookup)
- `source`: Filter by conversation source (exact match: SMS, phone, facebook, whatsapp, email, other)

**Example Request:**
```json
{
    "action": "get",
    "client_full_name": "John",
    "calee_phone_nr": "+370 500 98765",
    "limit": 10,
    "offset": 0
}
```

**Example Response:**
```json
{
    "status": "success",
    "message": "Conversation memory get operation completed successfully",
    "data": {
        "records": [
            {
                "id": "123",
                "client_full_name": "John Doe",
                "client_phone_nr": "+37060012345",
                "conversation": "Client called to schedule an appointment for next week.",
                "conversation_summary": "Client requested appointment scheduling for next week",
                "dat_time": "2024-01-15 14:30:00",
                "finalized_action": "Appointment confirmed and reminder sent",
                "lenght": "45",
                "worplace_id": "5",
                "workplace_name": "Downtown Office",
                "source": "phone",
                "booked_phone_nr": "37060099999"
            }
        ],
        "total_records": 1,
        "limit": 10,
        "offset": 0,
        "filters_applied": ["client_full_name", "calee_phone_nr"]
    },
    "timestamp": "2024-01-15 14:45:00"
}
```

## Workplace Resolution

The webhook automatically resolves workplace information from the `working_points` table:

1. **Input Parameter**: `calee_phone_nr` (the phone number of the working point)
2. **Database Lookup**: Matches against `booking_phone_nr` in the `working_points` table
3. **Automatic Resolution**: Sets `worplace_id` and `workplace_name` based on the lookup
4. **Phone Number Cleaning**: Both input and database phone numbers are cleaned (removes dots, plus signs, spaces) for comparison
5. **Last 8 Digits Matching**: **Important**: Phone numbers are matched by comparing only the last 8 digits to avoid country code or prefix mismatches

**Important Notes:**
- The `calee_phone_nr` must exist in the `working_points` table
- If no matching working point is found, the operation will fail with an appropriate error message
- Phone number cleaning ensures consistent matching regardless of formatting
- **Phone matching uses only the last 8 digits** - this means `+370 500 12345` will match `500 12345` or `37050012345` as long as the last 8 digits (`0012345`) are the same

## Error Handling

All errors return a 400 HTTP status code with the following JSON structure:

```json
{
    "status": "error",
    "message": "Error description",
    "timestamp": "2024-01-15 14:30:00"
}
```

**Common Error Messages:**
- "Missing required parameters: [parameter_names]"
- "Invalid action. Must be one of: insert, update, delete, get"
- "No conversation memory record found with id: [id]"
- "Client phone number must have at least 8 digits after cleaning"
- "No fields provided for update"
- "No working point found with booking phone number ending in: [last_8_digits]"

## Filtering Behavior

### GET Operation Filters
The GET operation supports the following filtering options:

**Exact Match Filters:**
- `id`: Record ID (integer)
- `worplace_id`: Workplace ID (integer)
- `booked_phone_nr`: Booked phone number (exact match after cleaning)
- `source`: Conversation source (exact match from allowed values)

**Partial Match Filters (LIKE queries):**
- `client_full_name`: Client name (searches within the name)
- `client_phone_nr`: Client phone number (searches within the number)
- `conversation`: Conversation content (searches within the text)
- `conversation_summary`: Conversation summary (searches within the summary)
- `finalized_action`: Finalized action (searches within the action text)
- `workplace_name`: Workplace name (searches within the name)

**Special Filters:**
- `calee_phone_nr`: Converts to workplace_id lookup using the working_points table
- `limit` and `offset`: Control pagination

**Note:** All filters are combined with AND logic, so multiple filters will narrow down the results.

## Data Validation
- **Client Phone Numbers**: Automatically cleaned by removing dots, plus signs, and spaces before storing
- **Booked Phone Numbers**: Automatically cleaned by removing dots, plus signs, and spaces before storing
- **Calee Phone Numbers**: Used for workplace lookup, cleaned for comparison with `working_points.booking_phone_nr`
- **Validation**: Both client and booked phone numbers must contain at least 8 digits after cleaning

### Source Field Validation
- **Required for INSERT**: The `source` field is mandatory when creating new conversation records
- **Allowed Values**: Must be one of: SMS, phone, facebook, whatsapp, email, other
- **Case Sensitivity**: Values are case-sensitive and must match exactly
- **Default Value**: Existing records will be updated with 'phone' as the default source

### Phone Number Matching Logic
The webhook uses a sophisticated phone number matching system to avoid country code and prefix mismatches:

1. **Input Cleaning**: Removes dots (.), plus signs (+), and spaces from both input and database phone numbers
2. **Last 8 Digits Extraction**: Extracts only the last 8 digits from the cleaned phone number
3. **Database Comparison**: Uses MySQL `RIGHT()` function to compare the last 8 digits of both numbers

**Examples of Matching:**
- Input: `+370 500 12345` → Last 8 digits: `0012345`
- Database: `500 12345` → Last 8 digits: `0012345` ✅ **MATCH**
- Database: `37050012345` → Last 8 digits: `0012345` ✅ **MATCH**
- Database: `+1 500 12345` → Last 8 digits: `0012345` ✅ **MATCH**
- Database: `500 98765` → Last 8 digits: `0098765` ❌ **NO MATCH**

**Benefits:**
- Eliminates country code mismatches (e.g., +370 vs +1)
- Handles different prefix formats (e.g., 500 vs 370500)
- Provides consistent matching regardless of input format
- Reduces configuration errors in working point setup

### Timestamp Handling
- `dat_time` field defaults to current timestamp if not provided
- Accepts MySQL datetime format (YYYY-MM-DD HH:MM:SS)

### Field Types
- `id`: Auto-incrementing bigint
- `client_full_name`: VARCHAR(255)
- `client_phone_nr`: VARCHAR(20)
- `conversation`: TEXT
- `dat_time`: TIMESTAMP
- `finalized_action`: VARCHAR(255), nullable
- `lenght`: INT, nullable
- `booked_phone_nr`: VARCHAR(20), nullable
- `worplace_id`: INT, nullable (automatically resolved from `calee_phone_nr`)
- `workplace_name`: VARCHAR(255), nullable (automatically resolved from `calee_phone_nr`)

## Webhook Logger Integration

This webhook includes comprehensive logging functionality using the WebhookLogger class:

### Logged Information
- **Request Details**: Method, URL, parameters, headers, client IP
- **Response Data**: Status codes, response body, processing time
- **Related Entities**: Conversation ID, workplace ID, client phone number
- **Additional Data**: Operation type, phone number processing details, workplace resolution

### Log Structure
```json
{
    "webhook_name": "update_conversation_memory",
    "request_method": "POST",
    "request_params": {
        "action": "insert",
        "client_full_name": "John Doe",
        "client_phone_nr": "+370 600 12345",
        "calee_phone_nr": "+370 500 98765"
    },
    "response_status_code": 200,
    "related_conversation_id": 123,
    "related_workplace_id": 5,
    "additional_data": {
        "operation_type": "insert",
        "phone_number_cleaned": "37060012345",
        "workplace_resolved": "Downtown Office",
        "source": "phone"
    }
}
```

### Error Logging
- **Validation Errors**: Missing parameters, invalid actions, phone number issues
- **Database Errors**: Connection issues, query failures, workplace resolution failures
- **General Errors**: Unexpected exceptions with stack traces

### Monitoring
- All webhook calls are logged to the `webhook_logs` table
- Performance metrics are tracked for optimization
- Error patterns can be analyzed for system improvements

## Security Considerations

1. **Input Validation**: All input parameters are validated and sanitized
2. **SQL Injection Protection**: Uses prepared statements for all database queries
3. **Phone Number Sanitization**: Automatically cleans phone numbers before storage and comparison
4. **Error Logging**: Comprehensive error logging for debugging and monitoring
5. **Database Lookup Validation**: Ensures `calee_phone_nr` exists in `working_points` table before proceeding

## Rate Limiting

Currently, no rate limiting is implemented. Consider implementing rate limiting for production use.

## Testing

You can test the webhook using tools like:
- cURL
- Postman
- Insomnia
- Any HTTP client

**Example cURL command:**
```bash
curl -X POST http://your-domain.com/webhooks/update_conversation_memory.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "insert",
    "client_full_name": "Test User",
    "client_phone_nr": "+370 600 12345",
    "conversation": "Test conversation content",
    "conversation_summary": "Test conversation summary",
    "calee_phone_nr": "+370 500 98765"
  }'
```

## Database Dependencies

This webhook depends on the following database tables:

1. **`conversation_memory`**: The main table for storing conversation records
2. **`working_points`**: Contains working point information with `id`, `name`, and `booking_phone_nr` fields

**Required `working_points` table structure:**
```sql
CREATE TABLE working_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    booking_phone_nr VARCHAR(50),
    -- other fields...
);
```

## New Fields: conversation_summary and booked_phone_nr

**Added in:** Latest update (2025-01-13)

### conversation_summary Field
The `conversation_summary` field has been added to the `conversation_memory` table to store AI-generated summaries of conversations. This field provides several benefits:

### Purpose
- **Quick Reference**: Provides a concise summary of conversation content for quick scanning
- **AI Processing**: Enables better AI analysis and pattern recognition
- **Search Optimization**: Improves search capabilities when looking for specific conversation types
- **Performance**: Faster retrieval of conversation context without reading full conversation text

### Field Specifications
- **Type**: TEXT (nullable)
- **Default**: NULL
- **Index**: Added index on first 100 characters for better search performance
- **Position**: Added after the `conversation` field in the table structure

### Usage Examples
```json
{
    "conversation_summary": "Client requested appointment rescheduling from Monday to Wednesday"
}
```

```json
{
    "conversation_summary": "Customer inquiry about service pricing and availability"
}
```

### booked_phone_nr Field
The `booked_phone_nr` field has been added to support scenarios where the client provides a different phone number for booking purposes than their main contact number. This field provides several benefits:

#### Purpose
- **Dual Phone Support**: Allows storing both client contact number and booking-specific phone number
- **Flexible Booking**: Supports cases where clients want to receive booking confirmations on a different number
- **Better Organization**: Separates contact information from booking preferences
- **Enhanced Filtering**: Enables searching by booking phone number specifically

#### Field Specifications
- **Type**: VARCHAR(20) (nullable)
- **Default**: NULL
- **Validation**: Must contain at least 8 digits after cleaning
- **Cleaning**: Automatically removes dots, plus signs, and spaces
- **Position**: Added after the `source` field in the table structure

#### Usage Examples
```json
{
    "client_phone_nr": "+370 600 12345",
    "booked_phone_nr": "+370 600 99999"
}
```

```json
{
    "client_phone_nr": "+370 600 12345",
    "booked_phone_nr": "+370 600 88888"
}
```

### Database Migration
The fields were added using the following SQL:
```sql
-- Add conversation_summary field
ALTER TABLE conversation_memory 
ADD COLUMN conversation_summary TEXT NULL 
COMMENT 'AI-generated summary of the conversation for quick reference' 
AFTER conversation;

ALTER TABLE conversation_memory 
ADD INDEX idx_conversation_summary (conversation_summary(100));

-- Add booked_phone_nr field
ALTER TABLE conversation_memory 
ADD COLUMN booked_phone_nr VARCHAR(20) DEFAULT NULL 
COMMENT 'Phone number provided by client for booking (may differ from client_phone_nr)' 
AFTER source;
```

## Support

For issues or questions regarding this webhook, check the webhook logs or contact the development team.

## Maintenance

Regular maintenance should include:
- Monitoring webhook response times through logs
- Reviewing workplace resolution performance
- Checking error logs for common validation issues
- Analyzing webhook usage patterns from logs
- Cleaning old log entries using `WebhookLogger::cleanOldLogs()`
- Monitoring phone number matching accuracy
- Reviewing source field validation rules
