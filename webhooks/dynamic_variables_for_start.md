# Dynamic Variables for Start Webhook Documentation

## Overview

The `dynamic_variables_for_start` webhook is designed to provide dynamic variables to Telnyx for usage at the introductory stage of the answering process. It accepts a phone number and returns the associated place name, address, and organization alias for use in call flows.

**IMPORTANT**: This webhook returns successful response **only if** the assigned phone number is **unique** in the database when matching the `booking_phone_nr`. If multiple working points share the same phone number, the webhook will return an error (HTTP 409 Conflict) listing all conflicting working points to prevent ambiguous results.

## Phone Number Matching

The webhook uses a configurable phone number matching system that matches the last N digits of phone numbers, regardless of country code or formatting. This allows the webhook to work with phone numbers in various formats:

- `+123456789` (with country code)
- `123456789` (without country code)
- `+44 1234 56789` (with spaces)
- `123456789` (plain digits)

### Configuration

At the top of the webhook file, you can configure the number of digits to match:

```php
$PHONE_MATCH_DIGITS = 9;  // Change to 8 or 10 as needed
```

### Matching Logic

1. **Clean the input phone number**: Remove all non-digit characters
2. **Extract last N digits**: Take the last `$PHONE_MATCH_DIGITS` digits
3. **Match against database**: Compare with the last N digits of stored phone numbers

**Example**: If `$PHONE_MATCH_DIGITS = 9`:
- Input: `+123456789` → Cleaned: `123456789` → Matched: `123456789`
- Input: `+44 1234 56789` → Cleaned: `44123456789` → Matched: `123456789`
- Database: `+123456789` → Cleaned: `123456789` → Matched: `123456789`

## Endpoint

### URL
```
/webhooks/dynamic_variables_for_start.php
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
| `assigned_phone_nr` | string | The booking phone number to look up | `+123456789` |

### Parameter Details

- **`assigned_phone_nr`**: The phone number that matches the `booking_phone_nr` field in the `working_points` table. This parameter is used to identify which working point and organization to return dynamic variables for.

## Usage Examples

### GET Request Example

**URL:**
```
GET /webhooks/dynamic_variables_for_start.php?assigned_phone_nr=+123456789
%2B encoding fot "+"
GET /webhooks/dynamic_variables_for_start.php?assigned_phone_nr=%2B123456789
```

**cURL Command:**
```bash
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php?assigned_phone_nr=%2B123456789"
```

**Expected Response:**
```json
{
    "status": "success",
    "timestamp": "2025-01-15 14:30:25",
    "assigned_phone_nr": "+123456789",
    "dynamic_variables": {
        "name_of_the_place": "Central Branch",
        "address": "Main St 1, City",
        "alias_name": "BeautyCo"
    }
}
```

**Alternative Phone Number Formats:**
```bash
# With country code
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php?assigned_phone_nr=%2B44123456789"

# Without country code  
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php?assigned_phone_nr=123456789"

# With spaces
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php?assigned_phone_nr=%2B44%201234%2056789"
```

All these formats will match the same working point if the last 9 digits match.

### POST Request Example

**URL:**
```
POST /webhooks/dynamic_variables_for_start.php
```

**Request Body:**
```json
{
    "assigned_phone_nr": "+123456789"
}
```

**cURL Command:**
```bash
curl -X POST "http://your-domain.com/webhooks/dynamic_variables_for_start.php" \
     -H "Content-Type: application/json" \
     -d '{"assigned_phone_nr": "+123456789"}'
```

**Expected Response:**
```json
{
    "status": "success",
    "timestamp": "2025-01-15 14:30:25",
    "assigned_phone_nr": "+123456789",
    "dynamic_variables": {
        "name_of_the_place": "Central Branch",
        "address": "Main St 1, City",
        "alias_name": "BeautyCo"
    }
}
```

### Error Examples

#### Missing Parameter (400 Bad Request)
```bash
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php"
```

**Response:**
```json
{
    "error": "Missing required parameter: assigned_phone_nr",
    "status": "error",
    "timestamp": "2025-01-15 14:30:25"
}
```

#### Invalid Phone Number (404 Not Found)
```bash
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php?assigned_phone_nr=+999999999"
```

**Response:**
```json
{
    "error": "No working point found for phone number: +999999999 (matched last 9 digits: 999999999)",
    "status": "error",
    "timestamp": "2025-01-15 14:30:25"
}
```

#### Multiple Working Points with Same Phone Number (409 Conflict)
```bash
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php?assigned_phone_nr=+123456789"
```

**Response:**
```json
{
    "error": "Multiple working points using the same booking_phone_nr: [1][Central Branch], [5][North Branch]",
    "status": "error",
    "timestamp": "2025-01-15 14:30:25",
    "duplicate_count": 2,
    "working_points": [
        {
            "working_point_id": 1,
            "name_of_the_place": "Central Branch",
            "organisation_id": 10,
            "alias_name": "Company A"
        },
        {
            "working_point_id": 5,
            "name_of_the_place": "North Branch",
            "organisation_id": 15,
            "alias_name": "Company B"
        }
    ]
}
```

## Response Format

### Success Response (200 OK)

```json
{
    "status": "success",
    "timestamp": "2025-01-15 14:30:25",
    "assigned_phone_nr": "+123456789",
    "dynamic_variables": {
        "name_of_the_place": "Central Branch",
        "address": "Main St 1, City",
        "alias_name": "BeautyCo"
    }
}
```

### Error Responses

#### Missing Parameter (400 Bad Request)
```json
{
    "error": "Missing required parameter: assigned_phone_nr",
    "status": "error",
    "timestamp": "2025-01-15 14:30:25"
}
```

#### Not Found (404 Not Found)
```json
{
    "error": "No working point found for phone number: +999999999",
    "status": "error",
    "timestamp": "2025-01-15 14:30:25"
}
```

#### Multiple Working Points (409 Conflict)
```json
{
    "error": "Multiple working points using the same booking_phone_nr: [1][Central Branch], [5][North Branch]",
    "status": "error",
    "timestamp": "2025-01-15 14:30:25",
    "duplicate_count": 2,
    "working_points": [
        {
            "working_point_id": 1,
            "name_of_the_place": "Central Branch",
            "organisation_id": 10,
            "alias_name": "Company A"
        },
        {
            "working_point_id": 5,
            "name_of_the_place": "North Branch",
            "organisation_id": 15,
            "alias_name": "Company B"
        }
    ]
}
```

#### Server Error (500 Internal Server Error)
```json
{
    "error": "Database error occurred",
    "status": "error",
    "timestamp": "2025-01-15 14:30:25"
}
```

## Database Schema

The webhook queries the following database tables:

### working_points Table
- `unic_id` (Primary Key)
- `name_of_the_place` (VARCHAR) - Name of the working location
- `address` (TEXT) - Physical address of the working point
- `booking_phone_nr` (VARCHAR) - Phone number for bookings
- `organisation_id` (INT) - Foreign key to organisations table

### organisations Table
- `unic_id` (Primary Key)
- `alias_name` (VARCHAR) - Organization alias name

## Business Logic

1. **Input Validation**: Validates that `assigned_phone_nr` parameter is provided
2. **Database Query**: Joins `working_points` and `organisations` tables to find matching records
3. **Duplicate Detection**: Checks if multiple working points share the same phone number
4. **Data Extraction**: Extracts `name_of_the_place`, `address`, and `alias_name` from the result
5. **Response Building**: Constructs JSON response with dynamic variables
6. **Logging**: Logs all requests and responses using WebhookLogger

### Duplicate Phone Number Handling

If multiple working points share the same `booking_phone_nr`:
- Returns HTTP 409 (Conflict) status code
- Error message lists all conflicting working points in format: `[id][name_of_the_place]`
- Provides structured `working_points` array with full details of each duplicate
- Logs the conflict with all working point IDs for administrative review

This prevents ambiguous responses and alerts administrators to configuration issues that need resolution.

## Usage in Telnyx

This webhook is intended to be called by Telnyx during call setup to retrieve dynamic variables that can be used in:

- **Call Greetings**: "Welcome to [name_of_the_place]"
- **Organization Identification**: "Thank you for calling [alias_name]"
- **Call Routing**: Based on the working point information
- **Call Scripts**: Dynamic content based on the location

## Logging and Monitoring

The webhook uses the WebhookLogger class to track:

- **Request Details**: Method, URL, parameters, headers
- **Response Data**: Status codes, response body
- **Performance Metrics**: Processing time
- **Error Tracking**: Error messages and stack traces
- **Related Entities**: Working point and organization IDs

### Log Structure
```json
{
    "webhook_name": "dynamic_variables_for_start",
    "request_method": "GET",
    "request_params": {"assigned_phone_nr": "+123456789"},
    "response_status_code": 200,
    "response_body": "...",
    "processing_time_ms": 45,
    "related_working_point_id": 1,
    "related_organisation_id": 1,
    "additional_data": {
        "phone_number_provided": "+123456789",
        "phone_suffix_matched": "123456789",
        "match_digits_used": 9,
        "place_name": "Central Branch",
        "place_address": "Main St 1, City",
        "organisation_alias": "BeautyCo"
    }
}
```

## Error Handling

### Database Errors
- Catches PDOException for database connection issues
- Logs detailed error information
- Returns generic error message to client

### Validation Errors
- Validates required parameters
- Returns appropriate HTTP status codes
- Logs validation failures

### General Errors
- Catches all unexpected exceptions
- Ensures graceful error handling
- Maintains system stability

## Security Considerations

1. **Input Sanitization**: All input parameters are validated
2. **SQL Injection Prevention**: Uses prepared statements
3. **Error Information**: Limits sensitive data in error responses
4. **Access Control**: No authentication required (public endpoint)

## Performance Considerations

1. **Database Optimization**: Uses indexed queries on `booking_phone_nr`
2. **Response Caching**: Consider implementing caching for frequently accessed data
3. **Connection Pooling**: Uses existing database connection
4. **Minimal Processing**: Optimized for speed

## Testing

### Test Cases

1. **Valid Phone Number**
   - Input: `assigned_phone_nr=+123456789`
   - Expected: Success response with dynamic variables

2. **Invalid Phone Number**
   - Input: `assigned_phone_nr=+999999999`
   - Expected: 404 Not Found

3. **Missing Parameter**
   - Input: No `assigned_phone_nr`
   - Expected: 400 Bad Request

4. **Empty Parameter**
   - Input: `assigned_phone_nr=`
   - Expected: 400 Bad Request

5. **Duplicate Phone Numbers**
   - Input: `assigned_phone_nr=+123456789` (where multiple working points share this number)
   - Expected: 409 Conflict with list of all matching working points

### Test Commands

```bash
# Test with valid phone number
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php?assigned_phone_nr=+123456789"

# Test with invalid phone number
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php?assigned_phone_nr=+999999999"

# Test with missing parameter
curl "http://your-domain.com/webhooks/dynamic_variables_for_start.php"
```

## Integration Examples

### Telnyx Call Flow Integration

```javascript
// Example Telnyx call flow
{
  "name": "Dynamic Variables Call Flow",
  "steps": [
    {
      "type": "webhook",
      "url": "https://your-domain.com/webhooks/dynamic_variables_for_start.php",
      "method": "GET",
      "parameters": {
        "assigned_phone_nr": "{{call.from}}"
      },
      "variable_name": "dynamic_vars"
    },
    {
      "type": "say",
      "text": "Welcome to {{dynamic_vars.dynamic_variables.name_of_the_place}}"
    },
    {
      "type": "say", 
      "text": "Thank you for calling {{dynamic_vars.dynamic_variables.alias_name}}"
    }
  ]
}
```

## Version History

- **v1.0** (2025-01-15): Initial release with basic functionality

## Support

For issues or questions regarding this webhook:

1. Check the webhook logs in the database
2. Verify the phone number exists in the working_points table
3. Ensure the database connection is working
4. Review error messages in the logs

## Related Files

- `webhooks/dynamic_variables_for_start.php` - Main webhook file
- `includes/webhook_logger.php` - Logging functionality
- `includes/db.php` - Database connection
- `docs/webhook_logger_documentation.md` - Logger documentation 