# Update Services Name Webhook Documentation

## Overview
The `update_services_name.php` webhook is designed to update the English translation of service names in the calendar system. This webhook accepts service identification and updates the `name_of_service_in_english` field in the services table.

**Key Features**:
- Supports both single service update and batch mode for multiple services
- Validates service existence and name matching before updates
- Provides partial success capability in batch mode (some services can succeed while others fail)
- Automatically trims English names to 100 characters maximum
- Prevents updates to deleted or suspended services
- Comprehensive error handling with detailed feedback

## Endpoint
```
POST: /webhooks/update_services_name.php
Content-Type: application/json
```

## Parameters

### Single Service Mode

When updating a single service, provide these fields directly in the JSON body:

- **service_id** (required, integer): ID of the service to update (maps to `unic_id` in services table)
- **service_name** (required, string): Current service name for validation (must match `name_of_service` in database)
- **name_of_service_in_english** (required, string): English translation of the service name (max 100 characters)

### Batch Mode

When updating multiple services, use the `services` array:

- **services** (required, array): Array of service objects, each containing:
  - **service_id** (required, integer): ID of the service to update
  - **service_name** (required, string): Current service name for validation
  - **name_of_service_in_english** (required, string): English translation of the service name

## Usage Examples

### Single Service Update (POST)
```bash
curl -X POST "http://yourdomain.com/webhooks/update_services_name.php" \
  -H "Content-Type: application/json" \
  -d '{
    "service_id": 101,
    "service_name": "Haircut - Women",
    "name_of_service_in_english": "Women Haircut"
  }'
```

### Batch Update (POST)
```bash
curl -X POST "http://yourdomain.com/webhooks/update_services_name.php" \
  -H "Content-Type: application/json" \
  -d '{
    "services": [
      {
        "service_id": 101,
        "service_name": "Haircut - Women",
        "name_of_service_in_english": "Women Haircut"
      },
      {
        "service_id": 102,
        "service_name": "Manicure",
        "name_of_service_in_english": "Manicure Service"
      },
      {
        "service_id": 103,
        "service_name": "Facial Treatment",
        "name_of_service_in_english": "Facial Treatment"
      }
    ]
  }'
```

### JavaScript Example (Single Service)
```javascript
fetch('http://yourdomain.com/webhooks/update_services_name.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    service_id: 101,
    service_name: 'Haircut - Women',
    name_of_service_in_english: 'Women Haircut'
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

### JavaScript Example (Batch Mode)
```javascript
fetch('http://yourdomain.com/webhooks/update_services_name.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    services: [
      {
        service_id: 101,
        service_name: 'Haircut - Women',
        name_of_service_in_english: 'Women Haircut'
      },
      {
        service_id: 102,
        service_name: 'Manicure',
        name_of_service_in_english: 'Manicure Service'
      }
    ]
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

## Response Format

### Single Service Mode - Success Response (HTTP 200)
```json
{
  "status": "success",
  "message": "Service English name updated successfully",
  "service_details": {
    "service_id": 101,
    "name_of_service": "Haircut - Women",
    "name_of_service_in_english": "Women Haircut",
    "previous_english_name": "not set",
    "specialist_id": 5,
    "working_point_id": 2,
    "organisation_id": 1
  },
  "rows_affected": 1,
  "timestamp": "2025-01-09 14:30:00"
}
```

### Single Service Mode - Error Response (HTTP 400)
```json
{
  "error": "Missing required parameters: service_id",
  "status": "error",
  "service_id": null,
  "timestamp": "2025-01-09 14:30:00"
}
```

### Single Service Mode - Service Not Found (HTTP 400)
```json
{
  "error": "Service not found",
  "status": "error",
  "service_id": 999,
  "timestamp": "2025-01-09 14:30:00"
}
```

### Single Service Mode - Name Mismatch (HTTP 400)
```json
{
  "error": "Service name mismatch: provided \"Wrong Name\" but actual is \"Haircut - Women\"",
  "status": "error",
  "service_id": 101,
  "timestamp": "2025-01-09 14:30:00"
}
```

### Single Service Mode - Deleted/Suspended Service (HTTP 400)
```json
{
  "error": "Service is deleted or suspended and cannot be updated",
  "status": "error",
  "service_id": 101,
  "timestamp": "2025-01-09 14:30:00"
}
```

### Batch Mode - All Success (HTTP 200)
```json
{
  "status": "success",
  "message": "All services updated successfully",
  "mode": "batch",
  "summary": {
    "total_requested": 3,
    "successful": 3,
    "failed": 0,
    "total_rows_affected": 3
  },
  "successful_updates": [
    {
      "index": 0,
      "service_id": 101,
      "name_of_service": "Haircut - Women",
      "name_of_service_in_english": "Women Haircut",
      "previous_english_name": "not set",
      "specialist_id": 5,
      "working_point_id": 2,
      "organisation_id": 1,
      "rows_affected": 1
    },
    {
      "index": 1,
      "service_id": 102,
      "name_of_service": "Manicure",
      "name_of_service_in_english": "Manicure Service",
      "previous_english_name": "Manicure",
      "specialist_id": 6,
      "working_point_id": 2,
      "organisation_id": 1,
      "rows_affected": 1
    },
    {
      "index": 2,
      "service_id": 103,
      "name_of_service": "Facial Treatment",
      "name_of_service_in_english": "Facial Treatment",
      "previous_english_name": "Face Treatment",
      "specialist_id": 7,
      "working_point_id": 2,
      "organisation_id": 1,
      "rows_affected": 1
    }
  ],
  "failed_updates": [],
  "timestamp": "2025-01-09 14:30:00"
}
```

### Batch Mode - Partial Success (HTTP 207)
```json
{
  "status": "partial_success",
  "message": "Some services updated successfully, others failed",
  "mode": "batch",
  "summary": {
    "total_requested": 3,
    "successful": 2,
    "failed": 1,
    "total_rows_affected": 2
  },
  "successful_updates": [
    {
      "index": 0,
      "service_id": 101,
      "name_of_service": "Haircut - Women",
      "name_of_service_in_english": "Women Haircut",
      "previous_english_name": "not set",
      "specialist_id": 5,
      "working_point_id": 2,
      "organisation_id": 1,
      "rows_affected": 1
    },
    {
      "index": 2,
      "service_id": 103,
      "name_of_service": "Facial Treatment",
      "name_of_service_in_english": "Facial Treatment",
      "previous_english_name": "Face Treatment",
      "specialist_id": 7,
      "working_point_id": 2,
      "organisation_id": 1,
      "rows_affected": 1
    }
  ],
  "failed_updates": [
    {
      "index": 1,
      "service_id": 102,
      "service_name": "Manicure",
      "error": "Service not found",
      "timestamp": "2025-01-09 14:30:00"
    }
  ],
  "timestamp": "2025-01-09 14:30:00"
}
```

### Batch Mode - All Failed (HTTP 400)
```json
{
  "status": "error",
  "message": "All services failed to update",
  "mode": "batch",
  "summary": {
    "total_requested": 2,
    "successful": 0,
    "failed": 2,
    "total_rows_affected": 0
  },
  "successful_updates": [],
  "failed_updates": [
    {
      "index": 0,
      "service_id": 999,
      "service_name": "Non-existent Service",
      "error": "Service not found",
      "timestamp": "2025-01-09 14:30:00"
    },
    {
      "index": 1,
      "service_id": 101,
      "service_name": "Wrong Name",
      "error": "Service name mismatch: provided \"Wrong Name\" but actual is \"Haircut - Women\"",
      "timestamp": "2025-01-09 14:30:00"
    }
  ],
  "timestamp": "2025-01-09 14:30:00"
}
```

### Invalid JSON (HTTP 400)
```json
{
  "error": "Invalid JSON format: Syntax error",
  "status": "error",
  "timestamp": "2025-01-09 14:30:00"
}
```

### Database Error (HTTP 500)
```json
{
  "error": "Database error occurred while updating service name",
  "status": "error",
  "timestamp": "2025-01-09 14:30:00"
}
```

## Batch Mode Behavior

The webhook supports batch processing with intelligent handling:

### Processing Logic
1. **Independent Processing**: Each service in the batch is processed independently
2. **Continue on Failure**: If one service fails, processing continues for remaining services
3. **Detailed Results**: Response includes both successful and failed updates with complete details
4. **HTTP Status Codes**:
   - `200`: All services updated successfully
   - `207`: Partial success (some succeeded, some failed)
   - `400`: All services failed to update

### Benefits of Batch Mode
- **Efficiency**: Update multiple services in a single API call
- **Resilience**: One failure doesn't stop the entire batch
- **Transparency**: Clear reporting of which services succeeded and which failed
- **Easy Retry**: Failed updates are clearly identified for retry

### Example Scenario
If you submit 10 services:
- 8 services update successfully
- 2 services fail (e.g., one not found, one name mismatch)
- Response includes HTTP 207 (Multi-Status)
- `successful_updates` array contains 8 items with full details
- `failed_updates` array contains 2 items with error reasons
- You can easily retry only the 2 failed updates

## Validation Rules

The webhook performs comprehensive validation before updating services:

### 1. Parameter Validation
- **service_id**: Must be present and a positive integer
- **service_name**: Must be present and non-empty string
- **name_of_service_in_english**: Must be present and non-empty string

### 2. Service Existence Check
- Service must exist in the database (matched by `unic_id`)
- Returns error if service not found

### 3. Service Name Verification
- Provided `service_name` must exactly match `name_of_service` in database
- This prevents accidental updates to wrong services
- Case-sensitive comparison

### 4. Service Status Check
- Service must not be deleted (`deleted` = 0)
- Service must not be suspended (`suspended` = 0)
- Deleted or suspended services cannot be updated

### 5. English Name Processing
- Automatically truncated to 100 characters maximum
- No HTML or special character filtering (allows international characters)

## Database Tables Used

- **services**: Main table containing service information
  - `unic_id`: Primary key, matched against `service_id` parameter
  - `name_of_service`: Service name in original language, used for validation
  - `name_of_service_in_english`: Target field being updated
  - `id_specialist`: Reference to specialist providing the service
  - `id_work_place`: Reference to working point location
  - `id_organisation`: Reference to organization
  - `deleted`: Flag indicating if service is deleted
  - `suspended`: Flag indicating if service is suspended

## Response Fields Description

### Single Service Mode Response

#### Success Response Fields
- `status`: Always "success" for successful updates
- `message`: Human-readable success message
- `service_details`: Object containing:
  - `service_id`: The updated service ID
  - `name_of_service`: The service name in original language
  - `name_of_service_in_english`: The new English translation
  - `previous_english_name`: The old English name (or "not set" if empty)
  - `specialist_id`: ID of the specialist providing this service
  - `working_point_id`: ID of the working point where service is offered
  - `organisation_id`: ID of the organization
- `rows_affected`: Number of database rows updated (usually 1)
- `timestamp`: Server timestamp of the update

#### Error Response Fields
- `error`: Error message describing what went wrong
- `status`: Always "error" for failed updates
- `service_id`: The service ID that failed (null if not provided)
- `timestamp`: Server timestamp of the error

### Batch Mode Response

#### Summary Object
- `total_requested`: Total number of services in the batch
- `successful`: Count of successfully updated services
- `failed`: Count of failed service updates
- `total_rows_affected`: Total database rows affected across all updates

#### Successful Updates Array
Each item contains:
- `index`: Position in the original services array (0-based)
- `service_id`: The updated service ID
- `name_of_service`: The service name
- `name_of_service_in_english`: The new English translation
- `previous_english_name`: The old English name
- `specialist_id`: Related specialist ID
- `working_point_id`: Related working point ID
- `organisation_id`: Related organization ID
- `rows_affected`: Rows affected for this specific update

#### Failed Updates Array
Each item contains:
- `index`: Position in the original services array (0-based)
- `service_id`: The service ID that failed
- `service_name`: The service name provided
- `error`: Detailed error message
- `timestamp`: When the error occurred

## Error Handling

The webhook includes comprehensive error handling for various scenarios:

### JSON Parsing Errors
- **Trigger**: Invalid JSON syntax in request body
- **HTTP Code**: 400
- **Response**: Includes JSON error message
- **Logged**: First 500 characters of raw input for debugging

### Missing Parameters
- **Trigger**: Required fields not provided or empty
- **HTTP Code**: 400
- **Response**: Lists all missing field names
- **Example**: "Missing required parameters: service_id, service_name"

### Invalid Service ID
- **Trigger**: service_id is not numeric or <= 0
- **HTTP Code**: 400
- **Response**: "Invalid service_id: must be a positive integer"

### Service Not Found
- **Trigger**: No service exists with the provided service_id
- **HTTP Code**: 400
- **Response**: "Service not found"

### Service Name Mismatch
- **Trigger**: Provided service_name doesn't match database
- **HTTP Code**: 400
- **Response**: Shows both provided and actual names
- **Purpose**: Prevents accidental updates to wrong services

### Deleted/Suspended Service
- **Trigger**: Attempting to update a deleted or suspended service
- **HTTP Code**: 400
- **Response**: "Service is deleted or suspended and cannot be updated"

### Database Errors
- **Trigger**: PDO exceptions during database operations
- **HTTP Code**: 500
- **Response**: Generic error message (doesn't expose database details)
- **Logged**: Full exception details including SQL state

### General Exceptions
- **Trigger**: Unexpected errors during processing
- **HTTP Code**: 500
- **Response**: Exception message
- **Logged**: Full stack trace for debugging

## CORS Support

The webhook includes CORS (Cross-Origin Resource Sharing) headers to support cross-origin requests:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, OPTIONS
Access-Control-Allow-Headers: Content-Type
```

### OPTIONS Preflight
- Handles OPTIONS requests for CORS preflight checks
- Returns HTTP 200 with appropriate headers
- No authentication required for OPTIONS

## Security Considerations

### 1. Input Validation
- All parameters validated before use
- Type checking for service_id (must be numeric)
- Empty string detection for required fields

### 2. SQL Injection Prevention
- Uses PDO prepared statements for all database queries
- Parameters bound separately from SQL statements
- No direct string concatenation in queries

### 3. Service Name Verification
- Requires exact match of service_name as safety check
- Prevents accidental updates to wrong services
- Acts as a "confirm" mechanism

### 4. Status Checking
- Prevents updates to deleted services
- Prevents updates to suspended services
- Maintains data integrity

### 5. Error Information
- Error messages don't expose sensitive database details
- Database errors show generic messages to clients
- Full details logged server-side only

### 6. Length Limitation
- English names automatically truncated to 100 characters
- Prevents database column overflow
- No user error thrown, silently truncated

## Webhook Logger Integration

This webhook includes comprehensive logging functionality using the WebhookLogger class:

### Logged Information

#### Success Logging (Single Mode)
```json
{
  "webhook_name": "update_services_name",
  "request_method": "POST",
  "response_status_code": 200,
  "processing_time_ms": 45,
  "service_id": 101,
  "specialist_id": 5,
  "working_point_id": 2,
  "organisation_id": 1
}
```

#### Success Logging (Batch Mode - Full Success)
```json
{
  "webhook_name": "update_services_name",
  "request_method": "POST",
  "response_status_code": 200,
  "processing_time_ms": 120,
  "mode": "batch",
  "services_count": 5,
  "total_rows_affected": 5
}
```

#### Success Logging (Batch Mode - Partial Success)
```json
{
  "webhook_name": "update_services_name",
  "request_method": "POST",
  "response_status_code": 207,
  "processing_time_ms": 150,
  "mode": "batch_partial",
  "successful_count": 3,
  "failed_count": 2,
  "total_rows_affected": 3,
  "additional_data": {
    "failed_services": [102, 105]
  }
}
```

#### Error Logging
- **Invalid JSON**: Logs first 500 characters of raw input
- **Validation Errors**: Logs service_id and error details
- **Database Errors**: Logs error code, SQL state, and full exception
- **General Errors**: Logs error type and stack trace

### Monitoring Capabilities
- Track update success rates (single vs batch mode)
- Identify frequently failing services
- Monitor performance metrics
- Analyze batch processing efficiency
- Detect patterns in validation failures

## Integration Notes

This webhook is designed for:

### Use Cases
1. **Multilingual Support**: Add English translations to services originally in other languages
2. **Bulk Import**: Import service translations from external systems in batches
3. **Data Migration**: Update service names during system migrations
4. **Content Management**: Allow administrators to manage service translations
5. **API Integration**: Enable third-party systems to update service information

### Best Practices
1. **Always Verify service_name**: Use the exact name from your database to prevent mistakes
2. **Use Batch Mode for Multiple Updates**: More efficient than multiple single calls
3. **Handle Partial Success**: Check both successful and failed arrays in batch responses
4. **Retry Failed Items**: In batch mode, easily identify and retry only failed items
5. **Check rows_affected**: Verify expected number of rows were actually updated
6. **Log Response Details**: Keep track of previous_english_name for audit trails

### Integration Checklist
- [ ] Verify JSON content-type header is set
- [ ] Implement error handling for all HTTP status codes
- [ ] In batch mode, process both successful and failed arrays
- [ ] Store previous_english_name for rollback capability
- [ ] Monitor webhook_logs table for issues
- [ ] Implement retry logic for partial failures
- [ ] Validate service_id and service_name before calling webhook

## Testing

### Test Scenarios

#### Single Service Mode Tests
1. **Success Case**: Update a valid service
2. **Missing Parameters**: Omit each required parameter
3. **Invalid service_id**: Use non-numeric or negative values
4. **Non-existent Service**: Use service_id that doesn't exist
5. **Name Mismatch**: Provide incorrect service_name
6. **Deleted Service**: Attempt to update a deleted service
7. **Suspended Service**: Attempt to update a suspended service
8. **Long English Name**: Test with string > 100 characters

#### Batch Mode Tests
1. **All Success**: Submit multiple valid services
2. **All Fail**: Submit multiple invalid services
3. **Partial Success**: Mix of valid and invalid services
4. **Empty Array**: Submit services array with no items
5. **Large Batch**: Test with 50+ services
6. **Duplicate IDs**: Test with same service_id multiple times

#### Edge Cases
1. **Special Characters**: Test with Unicode characters in names
2. **Empty Strings**: Test with empty name_of_service_in_english
3. **SQL Injection Attempts**: Verify prepared statements work correctly
4. **Invalid JSON**: Send malformed JSON
5. **Large Payload**: Test maximum batch size

### Testing Tools

#### Postman Collection Example
```json
{
  "name": "Update Services Name - Single",
  "request": {
    "method": "POST",
    "header": [
      {
        "key": "Content-Type",
        "value": "application/json"
      }
    ],
    "body": {
      "mode": "raw",
      "raw": "{\n  \"service_id\": 101,\n  \"service_name\": \"Haircut - Women\",\n  \"name_of_service_in_english\": \"Women Haircut\"\n}"
    },
    "url": "http://yourdomain.com/webhooks/update_services_name.php"
  }
}
```

#### PHP Test Script
```php
<?php
// Test single service update
$data = [
    'service_id' => 101,
    'service_name' => 'Haircut - Women',
    'name_of_service_in_english' => 'Women Haircut'
];

$ch = curl_init('http://yourdomain.com/webhooks/update_services_name.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
?>
```

## Maintenance

Regular maintenance should include:

### Monitoring
- **Success Rates**: Track percentage of successful vs failed updates
- **Response Times**: Monitor processing time, especially for large batches
- **Error Patterns**: Identify common validation failures
- **Batch Efficiency**: Compare batch vs single update performance
- **Failed Services**: Track which services frequently fail updates

### Database Maintenance
- **Log Cleanup**: Regularly clean old webhook logs using `WebhookLogger::cleanOldLogs()`
- **Index Optimization**: Ensure services table has proper indexes on unic_id
- **Query Performance**: Monitor slow query logs for optimization opportunities

### Code Maintenance
- **Validation Rules**: Update validation logic if business rules change
- **Error Messages**: Keep error messages clear and helpful
- **Documentation**: Update this file when webhook behavior changes
- **API Versioning**: Consider versioning if breaking changes needed

### Security Audits
- **SQL Injection**: Verify prepared statements are used consistently
- **Input Validation**: Review validation rules for completeness
- **Access Control**: Consider adding authentication if needed
- **Rate Limiting**: Monitor for abuse and implement rate limiting if necessary

### Performance Optimization
- **Batch Size Limits**: Consider limiting maximum batch size
- **Transaction Management**: Optimize database transaction handling
- **Response Size**: Monitor response payload sizes for large batches
- **Database Connections**: Ensure proper connection pooling

## Version History

**Version 2.1** (2025-01-15)
- Initial documented version
- Supports single and batch modes
- Comprehensive validation and error handling
- Webhook logger integration
- CORS support enabled

## Related Webhooks

This webhook works alongside other service management webhooks:
- Service creation webhooks
- Service deletion webhooks
- Service information retrieval webhooks
- Specialist-service assignment webhooks

## Support and Troubleshooting

### Common Issues

#### Issue: "Service name mismatch" error
**Solution**: Query the database to get the exact service_name value and use it exactly as stored

#### Issue: Batch mode returns all failures
**Solution**: Check that each service object in the array has all required fields

#### Issue: "Invalid JSON format" error
**Solution**: Validate JSON syntax using a JSON validator before sending

#### Issue: Updates not reflecting in database
**Solution**: Check rows_affected field - if 0, the value may already be the same

### Debug Checklist
1. ✓ Is Content-Type header set to application/json?
2. ✓ Is the JSON properly formatted?
3. ✓ Are all required fields present for each service?
4. ✓ Does the service_id exist in the database?
5. ✓ Does the service_name exactly match the database value?
6. ✓ Is the service active (not deleted or suspended)?
7. ✓ Check webhook_logs table for detailed error information
